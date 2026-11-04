<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MISSION P — Formulaire élève unique : « Ajouter un élève » = « Modifier le dossier élève ».
 *
 * La page d'ajout reste la référence obligatoire : la modification doit rendre les
 * mêmes sections, dans le même ordre, avec les mêmes champs, les mêmes composants,
 * la même grille deux colonnes et les mêmes zones d'upload. La seule différence
 * autorisée est le comportement (champs vides + création contre formulaire
 * prérempli + mise à jour).
 *
 * Les neuf vérifications obligatoires du cahier des charges sont couvertes :
 * 1 comparaison structurelle des deux pages, 2 préremplissage, 3 modification
 * nom/téléphone, 4 conservation photo et pièces sans nouvel envoi, 5 remplacement
 * volontaire de la photo, 6 sélection formation/classe, 7 conservation des
 * inscriptions et paiements, 8 absence de doublon, 9 affichage desktop/mobile
 * (+ FR/AR, clair/obscur et les quatre centres).
 *
 * Note sur les URLs : route() est construit depuis APP_URL alors que le HTML rendu
 * part de l'hôte de la requête ; on compare donc les chemins (pattern des autres
 * tests multi-rôles du projet).
 */
class StudentFormUnificationTest extends TestCase
{
    use RefreshDatabase;

    private Center $berlin;

    private Center $munich;

    private User $superAdmin;

    private User $adminBerlin;

    private User $adminMunich;

    private Formation $santeBerlin;

    private Formation $infoBerlin;

    private Formation $santeMunich;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');

        $this->berlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->munich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin  = User::factory()->superAdmin()->create(['email' => 'sa-unif@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->berlin->id)->create(['email' => 'ab-unif@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->munich->id)->create(['email' => 'am-unif@test.de']);

        // Catalogue de la création : formations générales officielles, par centre.
        $this->santeBerlin = Formation::factory()->forCenter($this->berlin)->forDomain('sante')->official('sante')
            ->create(['name' => 'Santé Berlin', 'annual_price' => 5000, 'price' => 500]);
        $this->infoBerlin = Formation::factory()->forCenter($this->berlin)->forDomain('informatique')->official('informatique')
            ->create(['name' => 'Informatique Berlin', 'annual_price' => 3000, 'price' => 300]);
        $this->santeMunich = Formation::factory()->forCenter($this->munich)->forDomain('sante')->official('sante')
            ->create(['name' => 'Santé Munich', 'annual_price' => 5000, 'price' => 500]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Séquence des noms de champs réellement rendus, dans l'ordre du DOM, hors
     * jeton CSRF et spoofing HTTP : c'est la comparaison structurelle demandée.
     *
     * @return list<string>
     */
    private function fieldSequence(string $html): array
    {
        preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"/s', $html, $matches);

        return array_values(array_filter(
            $matches[1],
            fn (string $name): bool => ! in_array($name, ['_token', '_method'], true)
        ));
    }

    /**
     * En-têtes des sections du formulaire : « 1 Informations personnelles », …
     * Le numéro de section est conservé : l'ordre des cartes est comparé aussi.
     *
     * @return list<string>
     */
    private function sectionSequence(string $html): array
    {
        preg_match_all('#<h2[^>]*>(.*?)</h2>#s', $html, $matches);

        $headers = array_map(
            fn (string $inner): string => trim(preg_replace('/\s+/', ' ', strip_tags($inner))),
            $matches[1]
        );

        return array_values(array_filter(
            $headers,
            fn (string $header): bool => (bool) preg_match('/^\d+\s/u', $header)
        ));
    }

    /**
     * Configuration Alpine réellement rendue (x-data du formulaire).
     */
    private function formConfig(string $html): string
    {
        $this->assertMatchesRegularExpression('/x-data="enrollmentFormApp\((\{.*?\})\)"/s', $html, 'Le formulaire doit partager le composant Alpine enrollmentFormApp().');

        preg_match('/x-data="enrollmentFormApp\((\{.*?\})\)"/s', $html, $matches);

        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    /**
     * Attributs action/id de la balise <form> réellement rendue.
     *
     * @return array{action: string, id: string}
     */
    private function formTag(string $html): array
    {
        $this->assertMatchesRegularExpression('/<form method="POST" action="([^"]+)"[^>]*id="([^"]+)"/s', $html);
        preg_match('/<form method="POST" action="([^"]+)"[^>]*id="([^"]+)"/s', $html, $matches);

        return ['action' => $matches[1], 'id' => $matches[2]];
    }

    /**
     * Texte visible du rendu (entités HTML décodées) : sert aux assertions de
     * libellés, dont plusieurs contiennent une apostrophe échappée dans le HTML.
     */
    private function plainText(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    private function samePath(string $expectedUrl, string $actualUrl, string $message): void
    {
        $this->assertSame(
            parse_url($expectedUrl, PHP_URL_PATH),
            parse_url($actualUrl, PHP_URL_PATH),
            $message
        );
    }

    /**
     * Dossier complet d'un élève déjà inscrit : photo sur le disque privé, pièces
     * déjà déposées, inscription (formation + classe + prix accordé) et deux
     * versements enregistrés. Sert à vérifier le préremplissage et la conservation.
     */
    private function studentWithDossier(Center $center, Formation $formation): Student
    {
        $student = Student::factory()->forCenter($center)->withoutAccount()->create([
            'first_name' => 'Youssef',
            'last_name' => 'El Amrani',
            'date_of_birth' => '2001-03-08',
            'nationality' => 'Marocaine',
            'phone' => '+212 600 112233',
            'whatsapp' => '+212 655 443322',
            'emergency_contact_phone' => '+212 537 112233',
            'status' => 'active',
        ]);

        $class = ClassRoom::factory()->forFormation($formation)->create([
            'name' => 'Groupe A',
            'code' => 'CLS-A-01',
        ]);

        Enrollment::factory()->forStudent($student)->forClass($class)->create([
            'official_price' => 5000,
            'final_price' => 4000,
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);

        $photo = Storage::disk('private')->putFileAs(
            "students/{$center->id}/{$student->id}/profile",
            UploadedFile::fake()->image('avatar.jpg', 160, 160),
            'avatar.jpg'
        );
        $student->update(['profile_photo_path' => $photo]);

        $this->recordDocument($student, $center, 'identity', 'CIN recto', 'cin-recto.jpg', 'image/jpeg');
        $this->recordDocument($student, $center, 'other', 'Attestation', 'attestation.pdf', 'application/pdf');

        $this->recordPayment($student, $formation, 1000);
        $this->recordPayment($student, $formation, 500);

        return $student->fresh();
    }

    private function recordDocument(Student $student, Center $center, string $type, string $title, string $name, string $mime): Document
    {
        $path = Storage::disk('private')->putFileAs("students/{$center->id}/{$student->id}/documents", UploadedFile::fake()->create($name, 20, $mime), $name);

        return Document::create([
            'center_id' => $center->id,
            'student_id' => $student->id,
            'type' => $type,
            'title' => $title,
            'file_path' => $path,
            'original_name' => $name,
            'mime_type' => $mime,
            'size' => 20480,
            'uploaded_by' => $this->adminBerlin->id,
            'status' => 'active',
        ]);
    }

    private function recordPayment(Student $student, Formation $formation, float $amount): Payment
    {
        $enrollment = $student->currentEnrollment();

        return Payment::create([
            'center_id' => $student->center_id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment?->id,
            'formation_id' => $formation->id,
            'school_year' => config('app.school_year', '2026/2027'),
            'domain' => $formation->domain,
            'amount' => $amount,
            'currency' => default_currency(),
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => Payment::generateReference($student->center),
            'status' => 'paid',
            'recorded_by' => $this->adminBerlin->id,
        ]);
    }

    /**
     * Payload fidèle au formulaire commun : les champs du tableur students, la
     * formation et le prix accordé. Aucune option de paiement sauf demande.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formPayload(Student $student, array $overrides = []): array
    {
        $student = $student->fresh();

        return array_merge([
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'date_of_birth' => $student->date_of_birth?->toDateString(),
            'nationality' => $student->nationality,
            'phone' => $student->phone,
            'whatsapp' => $student->whatsapp,
            'emergency_contact_phone' => $student->emergency_contact_phone,
            'formation_id' => $student->currentEnrollment()?->formation_id,
            'final_price' => $student->currentEnrollment()?->final_price,
        ], $overrides);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Vérification 1 — les deux pages sont structurellement identiques
    // ──────────────────────────────────────────────────────────────────────────

    public function test_create_and_edit_render_the_same_sections_in_the_same_order(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $create = $this->actingAs($this->adminBerlin)->get(route('students.create'))->assertOk()->getContent();
        $edit = $this->actingAs($this->adminBerlin)->get(route('students.edit', $student))->assertOk()->getContent();

        // Cinq sections numérotées, dans le même ordre sur les deux pages.
        $expected = [
            '1 Informations personnelles',
            '2 Pièces et fichiers',
            '3 Centre de formation',
            '4 Formation',
            '5 Paiement',
        ];

        $this->assertSame($expected, $this->sectionSequence($create), 'La page d’ajout doit garder ses cinq sections.');
        $this->assertSame($this->sectionSequence($create), $this->sectionSequence($edit), 'Les sections de la modification doivent être identiques et dans le même ordre.');

        // Mêmes cartes, même grille, même conteneur, même composant Alpine.
        foreach (['create' => $create, 'edit' => $edit] as $page => $html) {
            $this->assertSame(5, substr_count($html, 'class="ec-card overflow-hidden'), "Une seule carte par section sur la page {$page}.");
            $this->assertStringContainsString('grid grid-cols-1 gap-6 lg:grid-cols-2', $html, "Grille deux colonnes attendue sur la page {$page}.");
            $this->assertStringContainsString('mx-auto w-full max-w-7xl px-4 py-8 sm:px-6', $html, "Même conteneur de page sur {$page}.");
            $this->assertStringContainsString('x-data="enrollmentFormApp({', $html, "Même composant Alpine sur {$page}.");
            $this->assertSame(1, substr_count($html, 'function enrollmentFormApp(config)'), "Un seul pied de script partagé sur {$page}.");
        }

        // Le formulaire de modification utilise les mêmes en-têtes de carte
        // (mêmes icônes et typographies, reprises de la référence).
        $this->assertStringContainsString('text-2xl font-extrabold tracking-tight text-brand-700', $edit);
        $this->assertStringContainsString('flex h-8 w-8 items-center justify-center rounded-full', $edit);
    }

    public function test_create_and_edit_render_the_same_fields_in_the_same_order(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $create = $this->actingAs($this->adminBerlin)->get(route('students.create'))->assertOk()->getContent();
        $edit = $this->actingAs($this->adminBerlin)->get(route('students.edit', $student))->assertOk()->getContent();

        $expectedFields = [
            'last_name', 'first_name', 'date_of_birth', 'nationality', 'phone',
            'whatsapp', 'emergency_contact_phone',
            'profile_photo', 'cin_files[]', 'other_files[]',
            'formation_id', 'final_price', 'payment_option', 'payment_option', 'avance_amount',
        ];

        $this->assertSame($expectedFields, $this->fieldSequence($create), 'La référence (ajout) doit conserver exactement ses champs.');
        $this->assertSame($this->fieldSequence($create), $this->fieldSequence($edit), 'La modification doit proposer exactement les mêmes champs, dans le même ordre.');
    }

    public function test_edit_does_not_reintroduce_fields_removed_from_creation(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $edit = $this->actingAs($this->adminBerlin)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->getContent();

        // Les anciennes sections de modification (attributs hors périmètre) ne
        // reviennent pas : la page d'ajout fait foi.
        foreach (['gender', 'address', 'postal_code', 'city', 'country', 'notes', 'place_of_birth', 'user_id', 'status', 'emergency_contact_name', 'emergency_contact_relation'] as $field) {
            $this->assertStringNotContainsString('name="'.$field.'"', $edit, "Le champ supprimé « {$field} » ne doit pas réapparaître.");
        }

        $this->assertStringNotContainsString('name="domain"', $edit, 'Aucun champ Domaine : la sélection directe de la formation est conservée.');
        $this->assertStringNotContainsString('Domaine de formation', $edit);
    }

    public function test_super_admin_edit_page_matches_creation_apart_from_the_center_selector(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $create = $this->actingAs($this->superAdmin)->get(route('students.create'))->assertOk()->getContent();
        $edit = $this->actingAs($this->superAdmin)->get(route('students.edit', $student))->assertOk()->getContent();

        $this->assertSame($this->sectionSequence($create), $this->sectionSequence($edit));

        // Seule différence de champs : le sélecteur de centre, absent en
        // modification (le centre du dossier n'est pas réaffectable).
        $this->assertContains('center_id', $this->fieldSequence($create));
        $this->assertNotContains('center_id', $this->fieldSequence($edit));
        $this->assertSame(
            array_values(array_filter($this->fieldSequence($create), fn (string $n): bool => $n !== 'center_id')),
            $this->fieldSequence($edit)
        );

        // Le centre enregistré est affiché en lecture seule.
        $this->assertStringContainsString('Centre Berlin', $edit);
        $this->assertStringContainsString('Non modifiable', $edit);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Vérification 2 — préremplissage de toutes les informations enregistrées
    // ──────────────────────────────────────────────────────────────────────────

    public function test_edit_page_is_prefilled_with_every_saved_value(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);
        $enrollment = $student->currentEnrollment();

        $html = $this->actingAs($this->adminBerlin)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->getContent();

        // Champs personnels.
        foreach ([
            'value="El Amrani"',
            'value="Youssef"',
            'value="2001-03-08"',
            'value="Marocaine"',
            'value="+212 600 112233"',
            'value="+212 655 443322"',
            'value="+212 537 112233"',
        ] as $prefill) {
            $this->assertStringContainsString($prefill, $html, "Le préremplissage manque : {$prefill}");
        }

        // Le numéro enregistré international reste affiché tel quel (pas de
        // masque) et l'appelant visuel du champ demeure le placeholder commun.
        $this->assertStringContainsString('placeholder="+212 XXXXXXXXX"', $html);

        // Centre et formation pré-sélectionnés, classe chargée.
        $this->assertStringContainsString('data-center-id="'.$this->berlin->id.'"', $html);
        $this->assertMatchesRegularExpression(
            '#<option value="'.$enrollment->formation_id.'"\s+data-center-id="'.$this->berlin->id.'"\s+selected#',
            $html,
            'La formation actuelle du dossier doit être présélectionnée.'
        );
        $this->assertStringNotContainsString('value="'.$this->santeMunich->id.'"', $html, 'Seules les formations du centre du dossier sont proposées.');
        $this->assertStringContainsString('Classe actuelle de l\'inscription', $this->plainText($html));
        $this->assertStringContainsString('Groupe A', $html);
        $this->assertStringContainsString('CLS-A-01', $html);

        // Photo et pièces déjà enregistrées, sans obligation de renvoi.
        $this->assertStringContainsString('/students/'.$student->id.'/profile-photo', $html);
        $this->assertStringContainsString('CIN recto', $html);
        $this->assertStringContainsString('Attestation', $html);
        $this->assertStringContainsString('Déjà enregistré', $html);
        $this->assertStringContainsString(
            parse_url(route('documents.download', Document::firstWhere('title', 'CIN recto')), PHP_URL_PATH),
            $html
        );

        // Prix accordé repris dans la configuration Alpine du formulaire.
        $config = $this->formConfig($html);
        $this->assertStringContainsString('finalPriceInput: \''.$enrollment->final_price.'\'', $config);
        $this->assertStringContainsString('lockedCenterId: '.$this->berlin->id, $config);

        // Situation financière déjà enregistrée, en lecture seule.
        $text = $this->plainText($html);
        $this->assertStringContainsString('Situation déjà enregistrée', $text);
        $this->assertStringContainsString('4 000,00 DH', $text);
        $this->assertStringContainsString('1 500,00 DH', $text);
        $this->assertStringContainsString('2 500,00 DH', $text);
        $this->assertStringContainsString('La photo actuelle est conservée si vous ne choisissez pas de nouveau fichier.', $text);
    }

    public function test_create_page_keeps_empty_fields(): void
    {
        $html = $this->actingAs($this->adminBerlin)->get(route('students.create'))->assertOk()->getContent();

        // Mode création : mêmes composants, champs vides.
        $text = $this->plainText($html);
        foreach (['value="El Amrani"', 'value="Marocaine"'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, "La création ne doit préremplir aucune valeur : {$absent}");
        }
        foreach (['Situation déjà enregistrée', 'Classe actuelle de l\'inscription', 'Déjà enregistré'] as $absent) {
            $this->assertStringNotContainsString($absent, $text, "Le mode création ne doit pas afficher l’état enregistré : {$absent}");
        }

        $config = $this->formConfig($html);
        $this->assertStringContainsString('finalPriceInput: \'\'', $config);
        $this->assertStringContainsString('formationId: \'\'', $config);
        $this->assertStringContainsString('paidByFormation: []', $config);
        $this->assertStringContainsString('lockedCenterId: null', $config);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Vérification 3 — modification du nom et du numéro de téléphone
    // ──────────────────────────────────────────────────────────────────────────

    public function test_name_and_phone_can_be_modified_through_the_shared_form(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'first_name' => 'Amine',
                'phone' => '+212 600 998877',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Amine',
            'last_name' => 'El Amrani',
            'phone' => '+212 600 998877',
        ]);

        // L'identité du dossier est conservée : numéro et centre intacts.
        $fresh = $student->fresh();
        $this->assertSame($student->student_number, $fresh->student_number);
        $this->assertSame($this->berlin->id, $fresh->center_id);

        // Les nouvelles valeurs sont bien réaffichées après enregistrement.
        $html = $this->actingAs($this->adminBerlin)->get(route('students.edit', $fresh))->assertOk()->getContent();
        $this->assertStringContainsString('value="Amine"', $html);
        $this->assertStringContainsString('value="+212 600 998877"', $html);
    }

    public function test_a_validation_error_keeps_the_typed_values_on_the_edit_page(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        // Le formulaire d'où part la soumission : c'est lui qui enregistre l'URL
        // de retour, comme dans un navigateur.
        $this->actingAs($this->adminBerlin)
            ->get(route('students.edit', $student))
            ->assertOk();

        $invalid = $this->formPayload($student, [
            'first_name' => 'Bilal',
            'phone' => '+212 600 112200',
            // Date de naissance dans le futur : rejetée.
            'date_of_birth' => now()->addYear()->toDateString(),
        ]);

        $response = $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $invalid);

        $response->assertSessionHasErrors('date_of_birth');
        $this->samePath(
            route('students.edit', $student),
            (string) $response->headers->get('Location'),
            'Le refus doit renvoyer sur le formulaire de modification.'
        );

        // Rien n'est enregistré : la saisie reste dans old(), pas en base.
        $this->assertDatabaseHas('students', ['id' => $student->id, 'first_name' => 'Youssef']);

        // Réaffichage du formulaire (redirection suivie) : le champ en erreur est
        // signalé et la saisie est conservée grâce à old().
        $html = $this->actingAs($this->adminBerlin)
            ->from(route('students.edit', $student))
            ->followingRedirects()
            ->put(route('students.update', $student), $invalid)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('is-invalid', $html, 'Le champ en erreur doit être signalé.');
        $this->assertStringContainsString('ec-error', $html, 'Le message d\'erreur s\'affiche sous le champ.');
        $this->assertStringContainsString('value="Bilal"', $html, 'old() doit préserver la saisie après erreur.');
        $this->assertStringContainsString('value="+212 600 112200"', $html);
        $this->assertStringContainsString('Date de naissance', $this->plainText($html), 'Le libellé du champ reste affiché.');
        $this->assertStringContainsString('date de naissance doit être une date antérieure', $this->plainText($html), 'Le message d\'erreur est restitué sous le champ concerné.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Vérifications 4 et 5 — fichiers : conservation puis remplacement volontaire
    // ──────────────────────────────────────────────────────────────────────────

    public function test_photo_and_documents_are_kept_without_any_new_upload(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);
        $photoPath = $student->profile_photo_path;
        $documents = Document::where('student_id', $student->id)->get();

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // Ni le chemin, ni le fichier, ni les pièces ne bougent.
        $this->assertSame($photoPath, $student->fresh()->profile_photo_path);
        Storage::disk('private')->assertExists($photoPath);

        $this->assertCount(2, Document::where('student_id', $student->id)->get());
        foreach ($documents as $document) {
            Storage::disk('private')->assertExists($document->file_path);
        }

        // Aucun fichier n'est exigé : la mise à jour passe sans téléversement.
        $this->assertStringContainsString(
            'La photo actuelle est conservée',
            $this->actingAs($this->adminBerlin)->get(route('students.edit', $student))->assertOk()->getContent()
        );
    }

    public function test_photo_is_deliberately_replaced_and_the_old_file_removed(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);
        $oldPath = $student->profile_photo_path;

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'profile_photo' => UploadedFile::fake()->image('nouvelle-photo.jpg', 200, 200),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $fresh = $student->fresh();
        $this->assertNotSame($oldPath, $fresh->profile_photo_path);
        Storage::disk('private')->assertExists($fresh->profile_photo_path);
        Storage::disk('private')->assertMissing($oldPath);

        // Les pièces déjà enregistrées ne sont pas affectées par le remplacement.
        $this->assertCount(2, Document::where('student_id', $student->id)->get());

        // La nouvelle photo est bien celle servie depuis la fiche et le formulaire.
        $this->assertMatchesRegularExpression('/\.jpg$/', $fresh->profile_photo_path);
        $html = $this->actingAs($this->adminBerlin)->get(route('students.edit', $fresh))->assertOk()->getContent();
        $this->assertStringContainsString('/students/'.$fresh->id.'/profile-photo', $html);
    }

    public function test_new_files_are_added_without_touching_the_existing_ones(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);
        $identity = Document::firstWhere('student_id', $student->id)->firstWhere('type', 'identity');

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'cin_files' => [UploadedFile::fake()->image('cin-verso.jpg', 300, 200)],
                'other_files' => [UploadedFile::fake()->create('bulletin.pdf', 40, 'application/pdf')],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, Document::where('student_id', $student->id)->where('type', 'identity')->count());
        $this->assertSame(2, Document::where('student_id', $student->id)->where('type', 'other')->count());

        // L'ancienne pièce est toujours là, avec son fichier.
        Storage::disk('private')->assertExists($identity->file_path);
        $this->assertDatabaseHas('documents', ['id' => $identity->id, 'title' => 'CIN recto']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Vérification 6 — sélection de la formation et de la classe
    // ──────────────────────────────────────────────────────────────────────────

    public function test_formations_are_limited_to_the_student_center_on_both_pages(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $create = $this->actingAs($this->adminBerlin)->get(route('students.create'))->assertOk()->getContent();
        $edit = $this->actingAs($this->adminBerlin)->get(route('students.edit', $student))->assertOk()->getContent();

        foreach ([$create, $edit] as $html) {
            $this->assertStringContainsString('value="'.$this->santeBerlin->id.'"', $html);
            $this->assertStringContainsString('value="'.$this->infoBerlin->id.'"', $html);
            $this->assertStringNotContainsString('value="'.$this->santeMunich->id.'"', $html);
        }
    }

    public function test_formation_can_be_changed_and_the_enrollment_is_updated_in_place(): void
    {
        // Dossier sans versement : la réaffectation reste autorisée.
        $student = Student::factory()->forCenter($this->berlin)->withoutAccount()->create([
            'first_name' => 'Salma',
            'last_name' => 'Idrissi',
        ]);
        $class = ClassRoom::factory()->forFormation($this->santeBerlin)->create(['name' => 'Groupe B', 'code' => 'CLS-B-02']);
        $enrollment = Enrollment::factory()->forStudent($student)->forClass($class)
            ->create(['official_price' => 5000, 'final_price' => 5000]);

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'formation_id' => $this->infoBerlin->id,
                'final_price' => 3000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $fresh = $enrollment->fresh();
        $this->assertSame($enrollment->id, $fresh->id, 'L’inscription existante est modifiée en place, jamais dupliquée.');
        $this->assertSame($this->infoBerlin->id, (int) $fresh->formation_id);
        $this->assertSame('3000.00', (string) $fresh->final_price);
        $this->assertSame('3000.00', (string) $fresh->official_price);
        $this->assertNull($fresh->class_id, 'La classe de l’ancienne formation ne peut pas rester affectée.');
        $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
    }

    public function test_a_formation_already_paid_cannot_be_changed_from_the_file(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);
        $enrollment = $student->currentEnrollment();

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'formation_id' => $this->infoBerlin->id,
                'final_price' => 3000,
            ]))
            ->assertSessionHasErrors('formation_id');

        // Inscription et versements intacts.
        $this->assertSame($enrollment->id, $student->fresh()->currentEnrollment()->id);
        $this->assertSame($this->santeBerlin->id, (int) $student->fresh()->currentEnrollment()->formation_id);
        $this->assertSame(2, Payment::where('student_id', $student->id)->count());
    }

    public function test_a_formation_of_another_center_is_refused_on_update(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $this->actingAs($this->superAdmin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'formation_id' => $this->santeMunich->id,
                'final_price' => 5000,
            ]))
            ->assertSessionHasErrors('formation_id');

        $this->assertSame($this->berlin->id, $student->fresh()->center_id);
        $this->assertSame($this->santeBerlin->id, (int) $student->fresh()->currentEnrollment()->formation_id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Vérifications 7 et 8 — inscriptions/paiements conservés, aucun doublon
    // ──────────────────────────────────────────────────────────────────────────

    public function test_saving_the_file_creates_no_duplicate_of_anything(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $before = [
            'students' => Student::count(),
            'enrollments' => Enrollment::count(),
            'payments' => Payment::count(),
            'documents' => Document::count(),
            'enrollment_id' => $student->currentEnrollment()->id,
            'payment_ids' => Payment::where('student_id', $student->id)->pluck('id')->sort()->values()->all(),
        ];

        $response = $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, ['last_name' => 'El Amrani']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->samePath(route('students.show', $student), $response->headers->get('Location'), 'La mise à jour revient à la fiche de l’élève.');

        $fresh = $student->fresh();
        $this->assertSame($before['students'], Student::count(), 'Aucun nouvel élève créé.');
        $this->assertSame($before['enrollments'], Enrollment::count(), 'Aucune nouvelle inscription créée.');
        $this->assertSame($before['payments'], Payment::count(), 'Aucun paiement recréé par une simple modification.');
        $this->assertSame($before['documents'], Document::count(), 'Aucun document dupliqué.');
        $this->assertSame($before['enrollment_id'], $fresh->currentEnrollment()->id);
        $this->assertSame($before['payment_ids'], Payment::where('student_id', $student->id)->pluck('id')->sort()->values()->all());
        $this->assertSame(1, Student::where('id', $student->id)->count());
    }

    public function test_existing_payments_are_neither_reset_duplicated_nor_extended(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);
        $payments = Payment::where('student_id', $student->id)->orderBy('id')->get();

        // Enregistrement sans option de paiement : historique intact.
        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student))
            ->assertSessionHasNoErrors();

        $after = Payment::where('student_id', $student->id)->orderBy('id')->get();
        $this->assertSame($payments->pluck('id')->all(), $after->pluck('id')->all());
        $this->assertSame($payments->pluck('amount')->all(), $after->pluck('amount')->all());
        $this->assertSame($payments->pluck('status')->all(), $after->pluck('status')->all());
        $this->assertSame(1500.0, $student->fresh()->currentEnrollment()->paidAmount());

        // Avance explicite : un seul nouveau versement, du montant saisi.
        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'payment_option' => 'avance',
                'avance_amount' => 1000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(3, Payment::where('student_id', $student->id)->count());
        $new = Payment::where('student_id', $student->id)->orderByDesc('id')->first();
        $this->assertSame('1000.00', (string) $new->amount);
        $this->assertSame($student->currentEnrollment()->id, (int) $new->enrollment_id);
        $this->assertSame(2500.0, $student->fresh()->currentEnrollment()->paidAmount());
    }

    public function test_paye_option_records_only_the_real_remaining_balance(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'payment_option' => 'paye',
            ]))
            ->assertSessionHasNoErrors();

        // 4 000 accordés − 1 500 déjà encaissés = 2 500, jamais 4 000.
        $this->assertSame(3, Payment::where('student_id', $student->id)->count());
        $this->assertSame('2500.00', (string) Payment::where('student_id', $student->id)->orderByDesc('id')->first()->amount);
        $this->assertSame(0.0, $student->fresh()->currentEnrollment()->getRemainingAmount());
    }

    public function test_an_advance_higher_than_the_remaining_balance_is_refused(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'payment_option' => 'avance',
                'avance_amount' => 2600,
            ]))
            ->assertSessionHasErrors('avance_amount');

        $this->assertSame(2, Payment::where('student_id', $student->id)->count());
    }

    public function test_the_final_price_can_be_reduced_but_never_exceeds_the_official_rate(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        // Dépassement du tarif officiel : refusé.
        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, ['final_price' => 5500]))
            ->assertSessionHasErrors('final_price');
        $this->assertSame('4000.00', (string) $student->fresh()->currentEnrollment()->final_price);

        // Réduction accordée : enregistrée.
        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, ['final_price' => 3600]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame('3600.00', (string) $student->fresh()->currentEnrollment()->final_price);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Vérification 9 — disposition responsive, claire/obscure, FR/AR
    // ──────────────────────────────────────────────────────────────────────────

    public function test_the_shared_form_is_two_columns_on_desktop_and_one_on_mobile(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $create = $this->actingAs($this->adminBerlin)->get(route('students.create'))->assertOk()->getContent();
        $edit = $this->actingAs($this->adminBerlin)->get(route('students.edit', $student))->assertOk()->getContent();

        foreach (['create' => $create, 'edit' => $edit] as $page => $html) {
            // Mobile : une colonne. Desktop (lg) : deux cartes côte à côte.
            $this->assertStringContainsString('grid grid-cols-1 gap-6 lg:grid-cols-2', $html, "Grille responsive attendue sur {$page}.");
            // La section Paiement occupe toute la largeur, comme dans la référence.
            $this->assertStringContainsString('class="ec-card overflow-hidden lg:col-span-2"', $html, "Section pleine largeur manquante sur {$page}.");
            // Les champs internes passent en deux colonnes dès sm:.
            $this->assertStringContainsString('grid gap-5 p-6 sm:grid-cols-2', $html);
            // L'en-tête d'actions s'étend aussi sur les deux colonnes.
            $this->assertStringContainsString('flex flex-wrap items-center justify-end gap-3 pb-6 lg:col-span-2', $html);
        }
    }

    public function test_the_shared_form_supports_light_and_dark_modes(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $create = $this->actingAs($this->adminBerlin)->get(route('students.create'))->assertOk()->getContent();
        $edit = $this->actingAs($this->adminBerlin)->get(route('students.edit', $student))->assertOk()->getContent();

        foreach ([$create, $edit] as $html) {
            $this->assertStringContainsString('dark:bg-slate-800', $html);
            $this->assertStringContainsString('dark:border-slate-700', $html);
            $this->assertStringContainsString('toggleTheme', $html, 'L’interrupteur de thème doit rester dans la barre supérieure.');
        }
    }

    public function test_both_pages_render_in_french_and_in_arabic_rtl(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);
        $adminAr = User::factory()->centerAdmin($this->berlin->id)
            ->create(['email' => 'ab-ar-unif@test.de', 'locale' => 'ar']);

        // Français : libellés de la page de référence repris à l'identique.
        $htmlFr = $this->actingAs($this->adminBerlin)->get(route('students.edit', $student))->assertOk()->getContent();
        $this->assertStringNotContainsString('dir="rtl"', $htmlFr);
        $this->assertStringContainsString('Informations personnelles', $htmlFr);
        $this->assertStringContainsString('Modifier le dossier élève', $htmlFr);
        $this->assertStringContainsString('Enregistrer les modifications', $htmlFr);

        // Arabe : RTL + traductions des deux côtés du formulaire.
        $createAr = $this->actingAs($adminAr)->get(route('students.create'))->assertOk()->getContent();
        $editAr = $this->actingAs($adminAr)->get(route('students.edit', $student))->assertOk()->getContent();

        foreach (['create' => $createAr, 'edit' => $editAr] as $page => $html) {
            $this->assertStringContainsString('dir="rtl"', $html, "La page {$page} doit être en RTL en arabe.");
            $this->assertStringNotContainsString('Informations personnelles', $html, "La page {$page} doit traduire ses libellés.");
            $this->assertStringContainsString('معلومات شخصية', $html);
        }

        // Libellés arabes propres à la modification (nouveaux fichiers de langue).
        $this->assertStringContainsString('تعديل ملف التلميذ', $editAr);
        $this->assertStringContainsString('غير قابل للتعديل', $editAr);
        $this->assertStringContainsString('الوضعية المسجلة مسبقًا', $editAr);
        $this->assertStringNotContainsString('Modifier le dossier élève', $editAr);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  §5 — l'opération transmise : mise à jour, jamais création
    // ──────────────────────────────────────────────────────────────────────────

    public function test_the_edit_form_targets_the_update_route_with_the_required_label(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $html = $this->actingAs($this->adminBerlin)->get(route('students.edit', $student))->assertOk()->getContent();
        $form = $this->formTag($html);

        $this->samePath(route('students.update', $student), $form['action'], 'Le formulaire doit cibler la route de mise à jour.');
        $this->assertSame('form-edit-student', $form['id']);
        $this->assertStringContainsString('name="_method" value="PUT"', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('Enregistrer les modifications', $html);
        $this->assertStringContainsString('id="btn-update-student"', $html);
        $this->assertStringContainsString('type="submit"', $html);

        // La page d'ajout reste inchangée : POST vers students.store, libellé d'origine.
        $createHtml = $this->actingAs($this->adminBerlin)->get(route('students.create'))->assertOk()->getContent();
        $createForm = $this->formTag($createHtml);
        $this->samePath(route('students.store'), $createForm['action'], 'La création doit continuer de cibler students.store.');
        $this->assertSame('form-create-student', $createForm['id']);
        $this->assertStringNotContainsString('name="_method"', $createHtml);
        $this->assertStringContainsString('Enregistrer l\'élève', $this->plainText($createHtml));
        $this->assertStringNotContainsString('Enregistrer les modifications', $this->plainText($createHtml));
    }

    public function test_the_update_never_creates_a_second_student_record(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);
        $this->assertSame(1, Student::count());

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), $this->formPayload($student, [
                'last_name' => 'Changé',
                'phone' => '+212 611 223344',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('students.show', $student));

        $this->assertSame(1, Student::count(), 'La modification met à jour le dossier, elle n’en crée jamais un nouveau.');
        $this->assertSame('Changé', $student->fresh()->last_name);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  §7 — les quatre centres marocains, pour les administrateurs autorisés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_the_unified_form_works_in_the_four_moroccan_centers(): void
    {
        $centers = [
            ['name' => 'Centre Tanger',    'code' => 'TGR'],
            ['name' => 'Centre Agadir',    'code' => 'AGD'],
            ['name' => 'Centre Essaouira', 'code' => 'ESS'],
            ['name' => 'Centre Tétouane',  'code' => 'TET'],
        ];

        foreach ($centers as $data) {
            $center = Center::factory()->create($data);
            $admin = User::factory()->centerAdmin($center->id)
                ->create(['email' => strtolower($data['code']).'-unif@test.ma']);
            $formation = Formation::factory()->forCenter($center)->forDomain('sante')->official('sante')
                ->create(['annual_price' => 5000, 'price' => 500]);
            $student = $this->studentWithDossier($center, $formation);

            $create = $this->actingAs($admin)->get(route('students.create'))->assertOk()->getContent();
            $edit = $this->actingAs($admin)->get(route('students.edit', $student))->assertOk()->getContent();

            $this->assertSame($this->sectionSequence($create), $this->sectionSequence($edit), "Sections identiques attendues au centre {$data['code']}.");
            $this->assertSame($this->fieldSequence($create), $this->fieldSequence($edit), "Champs identiques attendus au centre {$data['code']}.");
            $this->assertStringContainsString($center->name, $edit);

            // La mise à jour fonctionne dans chaque centre, sans doublon.
            $this->actingAs($admin)
                ->put(route('students.update', $student), $this->formPayload($student, ['last_name' => 'Amrani'.$data['code']]))
                ->assertSessionHasNoErrors()
                ->assertRedirect();

            $this->assertSame('Amrani'.$data['code'], $student->fresh()->last_name);
            $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
            $this->assertSame(2, Payment::where('student_id', $student->id)->count());
        }
    }

    public function test_an_admin_of_another_center_cannot_open_or_save_the_file(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        $this->actingAs($this->adminMunich)->get(route('students.edit', $student))->assertForbidden();
        $this->actingAs($this->adminMunich)
            ->put(route('students.update', $student), $this->formPayload($student))
            ->assertForbidden();

        $this->assertSame('El Amrani', $student->fresh()->last_name);
        $this->assertSame(2, Payment::where('student_id', $student->id)->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  §7 — les autres interfaces restent inchangées
    // ──────────────────────────────────────────────────────────────────────────

    public function test_other_features_remain_operational(): void
    {
        $student = $this->studentWithDossier($this->berlin, $this->santeBerlin);

        // La fiche détaillée garde son bouton « Modifier » et ses sections propres.
        $show = $this->actingAs($this->adminBerlin)->get(route('students.show', $student))->assertOk()->getContent();
        $this->assertStringContainsString('/students/'.$student->id.'/edit', $show);

        // Listes, documents et finance toujours accessibles.
        $this->actingAs($this->adminBerlin)->get(route('students.index'))->assertOk();
        $this->actingAs($this->adminBerlin)->get(route('documents.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.finance.students'))->assertOk();
        $this->actingAs($this->adminBerlin)->get(route('formations-generales.index'))->assertOk();

        // La bascule de statut passe toujours par sa route dédiée.
        $this->actingAs($this->adminBerlin)
            ->patch(route('students.toggle-status', $student))
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $student->fresh()->status);
    }
}
