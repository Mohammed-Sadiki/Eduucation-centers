<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MISSION K — Formulaire « Ajouter une formation » refondu avec paiement intégré.
 *
 * Couvre les 21 scénarios obligatoires du cahier des charges : champs personnels
 * exacts, CIN / photo de profil / autres fichiers, cascade centre → domaine →
 * formation, paiement « Payé » (total, reste 0) et « Avance » (partiel, solde
 * calculé côté serveur), visibilité dans Gestion financière → Paiements, absence
 * de double enregistrement, isolation multi-centres, validations, i18n FR/AR,
 * light/dark, et préservation des autres fonctionnalités.
 */
class StudentEnrollmentPaymentFormTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;
    protected Center $centerMunich;
    protected User $superAdmin;
    protected User $adminBerlin;
    protected User $adminMunich;
    protected Formation $formationSanteBerlin;
    protected Formation $formationSanteMunich;
    protected Formation $formationInfoBerlin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->centerMunich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['email' => 'am@test.de']);

        // Tarifs fixes pour des assertions financières déterministes.
        // Nouveau parcours : les formations proposées dans « Ajouter un élève »
        // doivent être des formations générales officielles (is_official = true),
        // filtrées par centre. Les anciennes sous-formations legacy ne sont plus
        // jamais proposées à la sélection.
        $this->formationSanteBerlin = Formation::factory()
            ->forCenter($this->centerBerlin)->forDomain('sante')->official('sante')
            ->create(['annual_price' => 5000, 'price' => 500]);
        $this->formationSanteMunich = Formation::factory()
            ->forCenter($this->centerMunich)->forDomain('sante')->official('sante')
            ->create(['annual_price' => 5000, 'price' => 500]);
        $this->formationInfoBerlin = Formation::factory()
            ->forCenter($this->centerBerlin)->forDomain('informatique')->official('informatique')
            ->create(['annual_price' => 3000, 'price' => 300]);
    }

    /**
     * Jeu de données minimal du formulaire refondu (sans user_id : création
     * centralisée « dossier seul » possible).
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'center_id' => $this->centerBerlin->id,
            'first_name' => 'Awa',
            'last_name' => 'Diallo',
            'date_of_birth' => '2000-05-10',
            'nationality' => 'Malienne',
            'phone' => '+49 30 1112233',
            'whatsapp' => '+49 170 9998877',
            'emergency_contact_phone' => '+49 170 5554433',
        ], $overrides);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  1. Tous les champs personnels demandés sont affichés (et aucun autre)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_form_displays_exactly_the_requested_personal_fields(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk();

        foreach ([
            'name="last_name"',
            'name="first_name"',
            'name="date_of_birth"',
            'name="nationality"',
            'name="phone"',
            'name="whatsapp"',
            'name="emergency_contact_phone"',
            'name="profile_photo"',
            'name="cin_files[]"',
            'name="other_files[]"',
        ] as $field) {
            $response->assertSee($field, false);
        }

        // Aucun champ hors périmètre (les attributs legacy du modèle ne sont
        // plus exposés dans le formulaire refondu).
        foreach ([
            'name="gender"', 'name="address"', 'name="city"', 'name="notes"',
            'name="place_of_birth"', 'name="user_id"', 'name="status"',
        ] as $absent) {
            $response->assertDontSee($absent, false);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  2. Téléchargement des photos CIN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cin_photos_are_uploaded_and_stored_as_identity_documents(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'cin_files' => [
                UploadedFile::fake()->image('cin_recto.jpg'),
                UploadedFile::fake()->image('cin_verso.png'),
            ],
        ]));

        $response->assertRedirect();
        $student = Student::firstWhere('last_name', 'Diallo');

        $documents = Document::where('student_id', $student->id)->where('type', 'identity')->get();
        $this->assertCount(2, $documents);
        foreach ($documents as $document) {
            Storage::disk('private')->assertExists($document->file_path);
        }
    }

    public function test_non_image_cin_file_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'cin_files' => [UploadedFile::fake()->create('faux.pdf', 10, 'application/pdf')],
        ]));

        $response->assertSessionHasErrors('cin_files.0');
        $this->assertDatabaseCount('students', 0);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  3. Téléchargement de la photo de profil
    // ──────────────────────────────────────────────────────────────────────────

    public function test_profile_photo_is_uploaded_to_private_disk(): void
    {
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]))->assertRedirect();

        $student = Student::firstWhere('last_name', 'Diallo');
        $this->assertNotNull($student->profile_photo_path);
        Storage::disk('private')->assertExists($student->profile_photo_path);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  4. Téléchargement de fichiers supplémentaires
    // ──────────────────────────────────────────────────────────────────────────

    public function test_additional_files_are_uploaded_as_documents(): void
    {
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'other_files' => [
                UploadedFile::fake()->create('attestation.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('justificatif.docx', 120),
            ],
        ]))->assertRedirect();

        $student = Student::firstWhere('last_name', 'Diallo');
        $documents = Document::where('student_id', $student->id)->where('type', 'other')->get();
        $this->assertCount(2, $documents);
        $this->assertTrue($documents->contains(fn (Document $d) => $d->original_name === 'attestation.pdf'));
        foreach ($documents as $document) {
            Storage::disk('private')->assertExists($document->file_path);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  5. Le Super Admin peut sélectionner Berlin ou Munich
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_select_between_berlin_and_munich(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('name="center_id"', false)
            ->assertSee('Centre Berlin')
            ->assertSee('Centre Munich');

        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'center_id' => $this->centerMunich->id,
            'domain' => 'sante',
            'formation_id' => $this->formationSanteMunich->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('students', [
            'last_name' => 'Diallo',
            'center_id' => $this->centerMunich->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  6. L'Admin Centre utilise automatiquement son centre
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_form_has_no_center_selector_and_center_is_imposed(): void
    {
        $this->actingAs($this->adminBerlin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertDontSee('name="center_id"', false)
            ->assertSee('Automatique', false);

        // Aucun center_id transmis côté serveur : le centre du compte fait foi.
        $this->actingAs($this->adminBerlin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
        ]))->assertRedirect();

        $student = Student::firstWhere('last_name', 'Diallo');
        $this->assertSame($this->centerBerlin->id, $student->center_id);
        $this->assertStringStartsWith('BER-', $student->student_number);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  7. Nouveau parcours : aucun champ « Domaine » n'est exposé côté UI
    //     (la sélection directe Centre → Formation est appliquée partout).
    // ──────────────────────────────────────────────────────────────────────────

    public function test_no_domain_field_is_exposed_in_the_form(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertDontSee('name="domain"', false)
            ->assertDontSee('Domaine de formation', false)
            ->assertDontSee('Choisir un domaine', false)
            ->assertDontSee('Centre & domaine', false);

        // Les formations officielles restent bien proposées à la sélection.
        $response->assertSee('value="'.$this->formationSanteBerlin->id.'"', false);
        $response->assertSee('value="'.$this->formationInfoBerlin->id.'"', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  8. Les formations sont filtrées par centre (et uniquement par centre).
    // ──────────────────────────────────────────────────────────────────────────

    public function test_formations_are_scoped_to_center(): void
    {
        // Le rendu serveur expose le centre de chaque formation officielle pour
        // le filtrage dynamique (Alpine). Le domaine n'est plus un critère de
        // sélection : l'attribut data-domain a été retiré du parcours.
        $content = $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-center-id="'.$this->centerBerlin->id.'"', $content);
        $this->assertStringContainsString('data-center-id="'.$this->centerMunich->id.'"', $content);
        $this->assertStringNotContainsString('data-domain="sante"', $content);
        $this->assertStringNotContainsString('data-domain="informatique"', $content);

        // Admin Centre : uniquement les formations de son centre sont rendues.
        $this->actingAs($this->adminBerlin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('value="'.$this->formationSanteBerlin->id.'"', false)
            ->assertDontSee('value="'.$this->formationSanteMunich->id.'"', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  9 & 10. Option « Payé » : paiement complet, reste à payer 0
    // ──────────────────────────────────────────────────────────────────────────

    public function test_paye_option_records_full_payment_with_zero_remaining(): void
    {
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'payment_option' => 'paye',
        ]))->assertRedirect();

        $student = Student::firstWhere('last_name', 'Diallo');
        $payment = Payment::firstWhere('student_id', $student->id);

        $this->assertNotNull($payment);
        $this->assertSame('5000.00', (string) $payment->amount);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('MAD', $payment->currency);
        $this->assertStringStartsWith('PAY-BER-', $payment->reference);
        $this->assertNotNull($payment->enrollment_id);

        // Solde calculé par la logique financière existante : 0.
        $rows = app(FinanceService::class)->getStudentFinancialRows();
        $row = $rows->firstWhere('student.id', $student->id);
        $this->assertNotNull($row);
        $this->assertSame(0.0, (float) $row->remaining_amount);
        $this->assertSame('paid', $row->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  11 & 12. Option « Avance » : montant partiel + solde calculé côté serveur
    // ──────────────────────────────────────────────────────────────────────────

    public function test_avance_option_records_partial_payment_and_computes_remaining(): void
    {
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'payment_option' => 'avance',
            'avance_amount' => 2000,
        ]))->assertRedirect();

        $student = Student::firstWhere('last_name', 'Diallo');
        $payment = Payment::firstWhere('student_id', $student->id);

        $this->assertNotNull($payment);
        $this->assertSame('2000.00', (string) $payment->amount);
        $this->assertSame('paid', $payment->status);

        // Reste à payer = 5 000 − 2 000 = 3 000 (recalculé côté serveur).
        $rows = app(FinanceService::class)->getStudentFinancialRows();
        $row = $rows->firstWhere('student.id', $student->id);
        $this->assertSame(3000.0, (float) $row->remaining_amount);
        $this->assertSame('partial', $row->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  13. Une avance supérieure au tarif (ou négative) est refusée
    // ──────────────────────────────────────────────────────────────────────────

    public function test_avance_exceeding_formation_price_is_refused(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'payment_option' => 'avance',
            'avance_amount' => 6000,
        ]));

        $response->assertSessionHasErrors('avance_amount');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_negative_or_missing_avance_is_refused_and_exact_price_is_accepted(): void
    {
        // Avance négative : refusée (règle min:0 côté serveur).
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'payment_option' => 'avance',
            'avance_amount' => -100,
        ]))->assertSessionHasErrors('avance_amount');

        // Avance manquante : refusée.
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'payment_option' => 'avance',
        ]))->assertSessionHasErrors('avance_amount');

        // Avance exactement égale au tarif : acceptée (ne « dépasse » pas).
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'payment_option' => 'avance',
            'avance_amount' => 5000,
        ]))->assertRedirect();

        $this->assertDatabaseCount('payments', 1);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  14. Le paiement apparaît dans Gestion financière → Paiements
    // ──────────────────────────────────────────────────────────────────────────

    public function test_payment_is_visible_in_finance_students_page(): void
    {
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'last_name' => 'Visibelem',
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'payment_option' => 'avance',
            'avance_amount' => 1500,
        ]))->assertRedirect();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.students'))
            ->assertOk();

        // L'inscription payée partiellement est listée avec son solde.
        $response->assertSee('Visibelem');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  15. Aucun paiement enregistré en double
    // ──────────────────────────────────────────────────────────────────────────

    public function test_single_payment_recorded_per_enrollment(): void
    {
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'payment_option' => 'paye',
        ]))->assertRedirect();

        $student = Student::firstWhere('last_name', 'Diallo');
        $this->assertSame(1, Payment::where('student_id', $student->id)->count());
        $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
        $this->assertSame(1, Student::where('last_name', 'Diallo')->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  16. Données personnelles et fichiers conservés correctement
    // ──────────────────────────────────────────────────────────────────────────

    public function test_personal_data_and_files_are_persisted(): void
    {
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'profile_photo' => UploadedFile::fake()->image('profil.jpg'),
            'cin_files' => [UploadedFile::fake()->image('cin.jpg')],
            'other_files' => [UploadedFile::fake()->create('annexe.pdf', 50, 'application/pdf')],
        ]))->assertRedirect();

        $student = Student::firstWhere('last_name', 'Diallo');

        $this->assertSame('Awa', $student->first_name);
        $this->assertSame('2000-05-10', $student->date_of_birth->toDateString());
        $this->assertSame('Malienne', $student->nationality);
        $this->assertSame('+49 30 1112233', $student->phone);
        $this->assertSame('+49 170 9998877', $student->whatsapp);
        $this->assertSame('+49 170 5554433', $student->emergency_contact_phone);
        $this->assertNotNull($student->profile_photo_path);
        $this->assertSame('active', $student->status);

        $this->assertSame(1, Document::where('student_id', $student->id)->where('type', 'identity')->count());
        $this->assertSame(1, Document::where('student_id', $student->id)->where('type', 'other')->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  17. Les restrictions multi-centres sont respectées (avec paiement)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cross_center_enrollment_with_payment_is_blocked_and_leaves_no_trace(): void
    {
        // Admin Berlin tente d'inscrire à une formation de Munich avec paiement.
        $response = $this->actingAs($this->adminBerlin)->post(route('students.store'), $this->payload([
            'domain' => 'sante',
            'formation_id' => $this->formationSanteMunich->id,
            'payment_option' => 'paye',
        ]));

        $response->assertSessionHasErrors('formation_id');
        // Aucune inscription ni opération financière partiellement enregistrée.
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  18. Les erreurs de validation empêchent l'enregistrement
    // ──────────────────────────────────────────────────────────────────────────

    public function test_validation_errors_prevent_any_save(): void
    {
        // Champs obligatoires manquants.
        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'center_id' => $this->centerBerlin->id,
        ]);
        $response->assertSessionHasErrors(['first_name', 'last_name']);
        $this->assertDatabaseCount('students', 0);

        // Paiement transmis sans formation : refusé (pas de paiement orphelin).
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'payment_option' => 'paye',
        ]))->assertSessionHasErrors('formation_id');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  19. Le formulaire fonctionne en français et en arabe (RTL)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_form_renders_in_french_and_in_arabic_rtl(): void
    {
        // Français (défaut) : libellés FR bruts présents.
        $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('Informations personnelles')
            ->assertSee('Centre de formation')
            ->assertSee('Numéro de téléphone WhatsApp');

        // Arabe : direction RTL + libellés traduits.
        $adminAr = User::factory()->centerAdmin($this->centerBerlin->id)
            ->create(['email' => 'ab-ar@test.de', 'locale' => 'ar']);

        $this->actingAs($adminAr)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('رقم هاتف WhatsApp', false)
            ->assertDontSee('Numéro de téléphone WhatsApp', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  20. Le design fonctionne en Light / Dark Mode
    // ──────────────────────────────────────────────────────────────────────────

    public function test_form_supports_light_and_dark_modes(): void
    {
        $content = $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->getContent();

        // Classes dark: sur les cartes/champs + interrupteur de thème dans la topbar.
        $this->assertStringContainsString('dark:bg-slate-800', $content);
        $this->assertStringContainsString('toggleTheme', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  21. Les autres fonctionnalités restent opérationnelles
    // ──────────────────────────────────────────────────────────────────────────

    public function test_other_features_remain_operational(): void
    {
        // Pages concernées toujours accessibles.
        $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'))->assertOk();
        $this->actingAs($this->adminBerlin)->get(route('center-admin.domaines'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('students.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('formations.index'))->assertOk();

        // La création du catalogue de formations reste opérationnelle via sa route.
        $this->actingAs($this->superAdmin)->get(route('formations.create'))->assertOk();

        // Un dossier « dossier seul » (sans formation ni paiement) reste créé :
        // compatibilité avec le flux existant préservée.
        $this->actingAs($this->superAdmin)->post(route('students.store'), $this->payload([
            'first_name' => 'Seul',
            'last_name' => 'Dossier',
        ]))->assertRedirect();
        $this->assertDatabaseHas('students', ['last_name' => 'Dossier']);
        $this->assertSame(0, Payment::count());
    }
}
