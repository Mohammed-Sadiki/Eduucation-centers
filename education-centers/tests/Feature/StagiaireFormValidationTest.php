<?php

namespace Tests\Feature;

use App\Http\Requests\StoreStagiaireRequest;
use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\StagiaireDiploma;
use App\Models\StagiaireDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\Concerns\HasStagiaireFormData;
use Tests\TestCase;

/**
 * Validation stricte des formulaires « Ajouter un stagiaire » et « Modifier un stagiaire ».
 *
 * Règle maîtresse : toutes les informations personnelles (dont WhatsApp, téléphone
 * d'urgence, photos CIN et photo de profil), les trois champs de l'entreprise de
 * stage et les trois champs « Informations du stage » (dates de début/fin, prix)
 * sont obligatoires. Seule la section « Autres fichiers » peut rester vide.
 * Les autres champs hérités de l'ancien formulaire (email, adresse, type de stage,
 * notes…) restent acceptés mais facultatifs ; dès qu'ils sont fournis, leur format
 * est contrôlé.
 *
 * Les sections dynamiques « Diplômes » et « Autres documents » (legacy) suivent
 * toujours la logique « tout ou rien » : une ligne commencée exige tous ses champs.
 *
 * Les écritures (stagiaire + pièces) sont transactionnelles : aucune erreur de
 * validation ne laisse de dossier partiel en base.
 */
class StagiaireFormValidationTest extends TestCase
{
    use HasStagiaireFormData;
    use RefreshDatabase;

    protected Center $berlin;

    protected Center $munich;

    protected User $superAdmin;

    protected User $adminBerlin;

    protected User $adminMunich;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->berlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->munich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa-form@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->berlin->id)->create(['email' => 'ab-form@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->munich->id)->create(['email' => 'am-form@test.de']);
    }

    /**
     * Données complètes du formulaire, centre inclus (Super Admin).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $this->stagiairePayloadWithCin(array_merge(
            ['center_id' => $this->berlin->id],
            $overrides
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1 & 2. Sections obligatoires
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scénario 1 — toutes les informations obligatoires sont remplies : accepté.
     */
    public function test_complete_form_is_accepted(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('stagiaires', [
            'first_name' => 'Karim',
            'last_name' => 'Benani',
            'cin_number' => 'AB123456',
            'center_id' => $this->berlin->id,
        ]);
    }

    /**
     * Scénario 2 — chaque champ obligatoire manquant Individuellement : refusé.
     */
    public function test_each_required_field_is_enforced(): void
    {
        $requiredFields = [
            'first_name', 'last_name', 'center_id', 'date_of_birth', 'nationality',
            'phone', 'whatsapp', 'emergency_contact_phone',
            'cin_recto', 'cin_verso', 'profile_photo',
            'company_name', 'company_phone', 'company_location',
            'stage_start_date', 'stage_end_date', 'stage_price',
        ];

        foreach ($requiredFields as $field) {
            $payload = $this->form();
            unset($payload[$field]);

            $this->actingAs($this->superAdmin)
                ->from(route('stagiaires.create'))
                ->post(route('stagiaires.store'), $payload)
                ->assertSessionHasErrors($field);

            $this->assertSame(0, Stagiaire::count(), "Aucun stagiaire ne doit être créé sans « {$field} ».");
        }
    }

    /**
     * Un champ obligatoire rempli d'espaces ne vaut pas une réponse.
     */
    public function test_whitespace_only_values_are_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['first_name' => '   ']))
            ->assertSessionHasErrors('first_name');

        $this->assertSame(0, Stagiaire::count());
    }

    /**
     * Les formats sont vérifiés : e-mail, téléphone, dates et montant.
     */
    public function test_formats_are_validated(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['email' => 'pas-un-email']))
            ->assertSessionHasErrors('email');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['phone' => 'abcdef']))
            ->assertSessionHasErrors('phone');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'stage_start_date' => '2027-04-30',
                'stage_end_date' => '2026-11-01',
            ]))
            ->assertSessionHasErrors('stage_end_date');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['stage_price' => -250]))
            ->assertSessionHasErrors('stage_price');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['gender' => 'inconnu']))
            ->assertSessionHasErrors('gender');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['status' => 'brouillon']))
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Stagiaire::count());
    }

    /**
     * Le centre doit exister : impossible d'affecter un centre fantôme.
     */
    public function test_center_must_exist(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['center_id' => 99999]))
            ->assertSessionHasErrors('center_id');

        $this->assertSame(0, Stagiaire::count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3 à 7. Sections facultatives laissées vides
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scénarios 3, 4 et 5 — aucune clé diplômes/documents : accepté.
     */
    public function test_stagiaire_can_be_saved_without_any_diploma_or_document(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form())
            ->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::firstOrFail();
        $this->assertSame(0, StagiaireDiploma::count());
        $this->assertSame(0, StagiaireDocument::count());
        $this->assertCount(0, $stagiaire->diplomas);
        $this->assertCount(0, $stagiaire->documents);
    }

    /**
     * Scénarios 5 et 6 — tableaux vides et ligne de diplôme vide par défaut : accepté.
     */
    public function test_empty_diploma_row_does_not_block_saving(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => [
                    ['title' => '', 'institution' => '', 'year' => ''],
                ],
                'other_documents' => [],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Stagiaire::count());
        $this->assertSame(0, StagiaireDiploma::count(), 'Une ligne vide ne doit pas créer de faux diplôme.');
    }

    /**
     * Scénario 7 — ligne de document vide, type présélectionné inclus : accepté.
     */
    public function test_empty_document_row_with_preset_type_does_not_block_saving(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'other_documents' => [
                    ['title' => '', 'type' => 'attestation', 'file' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Stagiaire::count());
        $this->assertSame(0, StagiaireDocument::count(), 'Le type présélectionné seul ne crée aucun document.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8 à 13. Lignes facultatives réellement ajoutées
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scénario 8 — diplôme partiellement rempli : tous les champs manquants sont signalés.
     */
    public function test_partially_filled_diploma_is_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => [
                    ['title' => 'Licence Fondamentale', 'institution' => '', 'year' => ''],
                ],
            ]))
            ->assertSessionHasErrors(['diplomas.0.institution', 'diplomas.0.year', 'diplomas.0.file']);

        $this->assertSame(0, Stagiaire::count());
        $this->assertSame(0, StagiaireDiploma::count());
    }

    /**
     * Un diplôme sans fichier, ou un fichier sans diplôme, est refusé.
     */
    public function test_diploma_requires_its_scanned_file(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => [
                    [
                        'title' => 'Master',
                        'institution' => 'Université de Munich',
                        'year' => 2024,
                    ],
                ],
            ]))
            ->assertSessionHasErrors('diplomas.0.file');

        // L'inverse : un fichier déposé dans une ligne sans intitulé.
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => [
                    [
                        'title' => '',
                        'institution' => '',
                        'year' => '',
                        'file' => UploadedFile::fake()->create('diplome.pdf', 120, 'application/pdf'),
                    ],
                ],
            ]))
            ->assertSessionHasErrors(['diplomas.0.title', 'diplomas.0.institution', 'diplomas.0.year']);

        $this->assertSame(0, Stagiaire::count());
    }

    /**
     * Scénario 9 — diplôme complet avec fichier valide : accepté et stocké.
     */
    public function test_complete_diploma_with_valid_file_is_accepted(): void
    {
        $file = UploadedFile::fake()->image('licence.jpg');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => [
                    [
                        'title' => 'Licence Fondamentale',
                        'institution' => 'Université Hassan II',
                        'year' => 2022,
                        'file' => $file,
                    ],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::firstOrFail();
        $diploma = $stagiaire->diplomas()->first();

        $this->assertNotNull($diploma);
        $this->assertSame('Licence Fondamentale', $diploma->title);
        $this->assertSame('Université Hassan II', $diploma->institution);
        $this->assertSame(2022, (int) $diploma->year);
        $this->assertNotNull($diploma->file_path);
        Storage::disk('local')->assertExists($diploma->file_path);
    }

    /**
     * Scénario 10 — document partiellement rempli : refusé.
     */
    public function test_partially_filled_document_is_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->post(route('stagiaires.store'), $this->form([
                'other_documents' => [
                    ['title' => 'Convention tripartite', 'type' => 'convention', 'file' => ''],
                ],
            ]))
            ->assertSessionHasErrors('other_documents.0.file');

        $this->assertSame(0, Stagiaire::count());
        $this->assertSame(0, StagiaireDocument::count());
    }

    /**
     * Le type de pièce d'une ligne commencée doit être une valeur connue.
     */
    public function test_document_type_must_be_a_known_value(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'other_documents' => [
                    [
                        'title' => 'Attestation',
                        'type' => 'photo_de_cLASSE',
                        'file' => UploadedFile::fake()->create('attestation.pdf', 100, 'application/pdf'),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('other_documents.0.type');

        $this->assertSame(0, Stagiaire::count());
    }

    /**
     * Scénario 11 — document complet avec fichier valide : accepté et stocké.
     */
    public function test_complete_document_with_valid_file_is_accepted(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'other_documents' => [
                    [
                        'title' => 'Convention de stage',
                        'type' => 'convention',
                        'file' => UploadedFile::fake()->create('convention.pdf', 200, 'application/pdf'),
                    ],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $document = StagiaireDocument::firstOrFail();
        $this->assertSame('Convention de stage', $document->title);
        $this->assertSame('convention', $document->type);
        Storage::disk('local')->assertExists($document->file_path);
    }

    /**
     * Scénario 12 — plusieurs diplômes complets, mélangés à une ligne vide : acceptés.
     */
    public function test_multiple_complete_diplomas_are_accepted(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => [
                    [
                        'title' => 'Baccalauréat',
                        'institution' => 'Lycée Descartes',
                        'year' => 2018,
                        'file' => UploadedFile::fake()->create('bac.pdf', 100, 'application/pdf'),
                    ],
                    ['title' => '', 'institution' => '', 'year' => '', 'file' => ''],
                    [
                        'title' => 'Master Spécialisé',
                        'institution' => 'Université Paris-Saclay',
                        'year' => 2024,
                        'file' => UploadedFile::fake()->image('master.png'),
                    ],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::firstOrFail();
        $this->assertCount(2, $stagiaire->diplomas, 'Seules les lignes réellement remplies sont enregistrées.');
        $this->assertSame(0, StagiaireDiploma::where('title', '')->count());
    }

    /**
     * Scénario 13 — plusieurs documents complets : acceptés.
     */
    public function test_multiple_complete_documents_are_accepted(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'other_documents' => [
                    [
                        'title' => 'Attestation de réussite',
                        'type' => 'attestation',
                        'file' => UploadedFile::fake()->create('attestation.pdf', 100, 'application/pdf'),
                    ],
                    [
                        'title' => 'CV',
                        'type' => 'cv',
                        'file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
                    ],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::firstOrFail();
        $this->assertCount(2, $stagiaire->documents);
        $this->assertSame(['attestation', 'cv'], $stagiaire->documents->pluck('type')->sort()->values()->all());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14. Sécurité des fichiers téléversés
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scénario 14 — extension interdite et fichier trop volumineux : refusés.
     */
    public function test_invalid_and_oversized_files_are_rejected(): void
    {
        // Diplôme au format interdit.
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => [
                    [
                        'title' => 'Licence',
                        'institution' => 'Université',
                        'year' => 2020,
                        'file' => UploadedFile::fake()->create('licence.exe', 50, 'application/octet-stream'),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('diplomas.0.file');

        // Document dépassant la taille maximale (10 Mo).
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'other_documents' => [
                    [
                        'title' => 'Gros document',
                        'type' => 'autre',
                        'file' => UploadedFile::fake()->create('gros.pdf', 20000, 'application/pdf'),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('other_documents.0.file');

        // CIN dépassant la taille maximale (5 Mo).
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'cin_recto' => UploadedFile::fake()->create('recto.pdf', 9000, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('cin_recto');

        $this->assertSame(0, Stagiaire::count());
        $this->assertSame(0, StagiaireDiploma::count());
        $this->assertSame(0, StagiaireDocument::count());
    }

    /**
     * Une année de diplôme invalide est refusée.
     */
    public function test_diploma_year_must_be_a_valid_year(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => [
                    [
                        'title' => 'Licence',
                        'institution' => 'Université',
                        'year' => '18xx',
                        'file' => UploadedFile::fake()->create('licence.pdf', 50, 'application/pdf'),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('diplomas.0.year');

        $this->assertSame(0, Stagiaire::count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 15 & 16. Atomicité et restitution des erreurs
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scénario 15 — une erreur de validation ne crée ni dossier partiel ni pièce orpheline.
     */
    public function test_validation_error_leaves_no_partial_record(): void
    {
        $before = [Stagiaire::count(), StagiaireDiploma::count(), StagiaireDocument::count()];

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'email' => 'invalide',
                'diplomas' => [
                    ['title' => 'Licence', 'institution' => '', 'year' => '', 'file' => ''],
                ],
            ]))
            ->assertSessionHasErrors(['email', 'diplomas.0.institution']);

        $this->assertSame($before, [Stagiaire::count(), StagiaireDiploma::count(), StagiaireDocument::count()]);
        $this->assertEmpty(Storage::disk('local')->allFiles(), 'Aucun fichier ne doit être écrit avant validation.');
    }

    /**
     * Scénario 16 — les erreurs et les saisies sont restituées dans le formulaire.
     */
    public function test_errors_and_previous_input_are_displayed_in_the_form(): void
    {
        // Refus serveur : retour sur le formulaire, erreurs et saisies restituées.
        $html = $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->followingRedirects()
            ->post(route('stagiaires.store'), $this->form([
                'last_name' => '',
                'company_location' => '',
            ]))
            ->assertOk()
            ->getContent();

        // Bandeau récapitulatif + message sous le champ concerné.
        $this->assertStringContainsString('Veuillez corriger les erreurs suivantes', $html);
        $this->assertStringContainsString('name="last_name"', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('ec-error', $html);

        // Valeurs conservées, y compris dans la carte Entreprise de stage.
        $this->assertStringContainsString('value="Karim"', $html);
        $this->assertStringContainsString('value="Atlas Technologies"', $html);
    }

    /**
     * Le refus du serveur ramène l'administrateur sur son formulaire d'origine.
     */
    public function test_failed_submission_redirects_back_to_the_creation_form(): void
    {
        $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->post(route('stagiaires.store'), $this->form(['last_name' => '']))
            ->assertRedirect(route('stagiaires.create'))
            ->assertSessionHasErrors('last_name');

        $this->assertSame(0, Stagiaire::count());
    }

    /**
     * Le formulaire rendu porte bien les marqueurs de validation stricte.
     */
    public function test_creation_form_displays_required_and_optional_markers(): void
    {
        $html = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-stagiaire-form', $html);
        $this->assertStringContainsString('novalidate', $html);
        $this->assertStringContainsString('Facultatif', $html);

        // Les trois cartes demandées sont bien présentes.
        $this->assertStringContainsString('Informations personnelles', $html);
        $this->assertStringContainsString('Entreprise où le stagiaire effectuera son stage', $html);
        $this->assertStringContainsString('Informations du stage', $html);

        foreach (['first_name', 'last_name', 'center_id', 'date_of_birth', 'nationality',
            'phone', 'whatsapp', 'emergency_contact_phone',
            'company_name', 'company_phone', 'company_location',
            'stage_start_date', 'stage_end_date', 'stage_price'] as $field) {
            preg_match_all(
                '/<(?:input|select|textarea)\b[^>]*\bname="'.preg_quote($field, '/').'"[^>]*>/s',
                $html,
                $tags
            );

            $this->assertNotEmpty($tags[0], "Le champ « {$field} » doit être présent dans le formulaire.");

            foreach ($tags[0] as $tag) {
                $this->assertStringContainsString('required', $tag, "Le champ obligatoire « {$field} » doit exiger la saisie : {$tag}");
                $this->assertStringContainsString('data-required', $tag, "Le champ obligatoire « {$field} » doit être contrôlé par le script : {$tag}");
            }
        }

        // Les pièces obligatoires sont instrumentées de la même façon.
        foreach (['cin_recto', 'cin_verso', 'profile_photo'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<input\b[^>]*name="'.preg_quote($field, '/').'"[^>]*data-required[^>]*>/s',
                $html,
                "La pièce obligatoire « {$field} » doit être contrôlée par le script."
            );
        }

        // Seule section facultative : « Autres fichiers » — jamais marquée obligatoire.
        preg_match_all('/<input\b[^>]*name="other_files\[\]"[^>]*>/s', $html, $optionalTags);
        $this->assertNotEmpty($optionalTags[0], 'Le champ « Autres fichiers » doit être présent.');
        foreach ($optionalTags[0] as $tag) {
            $this->assertStringNotContainsString('required', $tag, '« Autres fichiers » ne doit pas être obligatoire : '.$tag);
            $this->assertStringContainsString('data-optional-files', $tag);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 17. Permissions et isolation Berlin / Munich
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scénario 17 — les Admin Centres accèdent au module, limitées à leur centre :
     * la création leur est ouverte (centre imposé côté serveur), la suppression
     * reste réservée au Super Admin.
     */
    public function test_centre_admins_reach_their_own_stagiaires_module(): void
    {
        foreach ([[$this->adminBerlin, $this->berlin], [$this->adminMunich, $this->munich]] as [$admin, $center]) {
            $this->actingAs($admin)->get(route('stagiaires.index'))->assertOk();
            $this->actingAs($admin)->get(route('stagiaires.create'))->assertOk();

            // Même en tentant d'imposer un autre centre, le serveur force le leur.
            $this->actingAs($admin)
                ->post(route('stagiaires.store'), $this->form(['center_id' => $center === $this->berlin ? $this->munich->id : $this->berlin->id]))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('stagiaires', [
                'first_name' => 'Karim',
                'last_name' => 'Benani',
                'center_id' => $center->id,
            ]);

            $stagiaire = Stagiaire::where('center_id', $center->id)->firstOrFail();
            $this->actingAs($admin)->delete(route('stagiaires.destroy', $stagiaire))->assertForbidden();
        }
    }

    /**
     * Un Admin Centre ne peut pas modifier un stagiaire d'un autre centre.
     */
    public function test_centre_admin_cannot_touch_another_centre_stagiaire(): void
    {
        $stagiaire = Stagiaire::create([
            'first_name' => 'Stagiaire',
            'last_name' => 'Munich',
            'center_id' => $this->munich->id,
        ]);

        $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.show', $stagiaire))
            ->assertForbidden();

        $this->actingAs($this->adminBerlin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayloadWithCin())
            ->assertForbidden();
    }

    /**
     * Le Super Admin peut affecter un stagiaire à Berlin comme à Munich.
     */
    public function test_super_admin_may_assign_berlin_or_munich(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['center_id' => $this->berlin->id, 'last_name' => 'Berliner']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['center_id' => $this->munich->id, 'last_name' => 'Munchen']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stagiaires', ['last_name' => 'Berliner', 'center_id' => $this->berlin->id]);
        $this->assertDatabaseHas('stagiaires', ['last_name' => 'Munchen', 'center_id' => $this->munich->id]);
    }

    /**
     * Pour un Admin Centre, le centre est résolu côté serveur : sa requête ne peut
     * pas imposer un autre centre (défense en profondeur, même si la route est 403).
     */
    public function test_centre_is_resolved_server_side_for_centre_admin(): void
    {
        $request = StoreStagiaireRequest::create(
            route('stagiaires.store'),
            'POST',
            ['center_id' => $this->munich->id, 'first_name' => 'Hacker']
        );
        $request->setUserResolver(fn () => $this->adminBerlin);

        $prepare = new ReflectionMethod($request, 'prepareForValidation');
        $prepare->setAccessible(true);
        $prepare->invoke($request);

        $this->assertSame(
            $this->berlin->id,
            (int) $request->input('center_id'),
            'Le centre d\'un Admin Centre est imposé par le serveur.'
        );
        $this->assertContains('required', $request->rules()['center_id']);
    }

    /**
     * Une requête HTTP directe contournant le formulaire reste bloquée.
     */
    public function test_direct_json_request_cannot_bypass_validation(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson(route('stagiaires.store'), [
                'first_name' => 'Direct',
                'last_name' => 'Requete',
                'center_id' => $this->berlin->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth', 'nationality', 'phone', 'whatsapp',
                'emergency_contact_phone', 'cin_recto', 'cin_verso', 'profile_photo',
                'company_name', 'company_phone', 'company_location',
                'stage_start_date', 'stage_end_date', 'stage_price']);

        $this->assertDatabaseCount('stagiaires', 0);
    }

    /**
     * Une ligne falsifiée (non-tableau) est refusée sans crash.
     */
    public function test_malformed_dynamic_rows_are_refused(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'diplomas' => 'une-chaine',
            ]))
            ->assertSessionHasErrors('diplomas');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'other_documents' => [42],
            ]))
            ->assertSessionHasErrors('other_documents.0');

        $this->assertDatabaseCount('stagiaires', 0);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 18 & 19. Modification d'un stagiaire
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scénario 18 — les stagiaires existants restent accessibles malgré le durcissement.
     */
    public function test_existing_stagiaires_remain_accessible(): void
    {
        $stagiaire = Stagiaire::create([
            'first_name' => 'Ancien',
            'last_name' => 'Dossier',
            'center_id' => $this->berlin->id,
            'stage_price' => 300.00,
            'currency' => 'EUR',
            'status' => 'en_cours',
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.show', $stagiaire))
            ->assertOk()
            ->assertSee('Ancien');

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.edit', $stagiaire))
            ->assertOk()
            ->assertSee('Modifier');

        $this->assertDatabaseHas('stagiaires', ['id' => $stagiaire->id, 'last_name' => 'Dossier']);
    }

    /**
     * Scénario 19 — la modification exige les mêmes informations obligatoires.
     */
    public function test_update_requires_the_same_mandatory_fields(): void
    {
        $stagiaire = Stagiaire::create([
            'first_name' => 'Marie',
            'last_name' => 'Weiss',
            'center_id' => $this->berlin->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), ['last_name' => 'Noir'])
            ->assertSessionHasErrors([
                'first_name', 'center_id', 'date_of_birth', 'nationality', 'phone',
                'whatsapp', 'emergency_contact_phone', 'cin_recto', 'profile_photo',
                'company_name', 'company_phone', 'company_location',
                'stage_start_date', 'stage_end_date', 'stage_price',
            ]);

        $this->assertDatabaseHas('stagiaires', ['id' => $stagiaire->id, 'last_name' => 'Weiss']);
    }

    /**
     * Scénario 19 — un dossier dont les pièces existent déjà (CIN, photo de profil)
     * n'exige aucun nouveau fichier : les éléments enregistrés sont conservés.
     */
    public function test_update_keeps_existing_files_without_new_upload(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'cin_recto_path' => 'stagiaires/cin/recto.pdf',
            'cin_verso_path' => 'stagiaires/cin/verso.pdf',
            'profile_photo_path' => 'stagiaires/1/profile/profil.jpg',
        ]));

        $diploma = $stagiaire->diplomas()->create([
            'title' => 'Diplôme existant',
            'institution' => 'Université existante',
            'year' => 2019,
            'file_path' => 'stagiaires/'.$stagiaire->id.'/diplomas/diplome.pdf',
            'file_name' => 'diplome.pdf',
        ]);

        $document = $stagiaire->documents()->create([
            'title' => 'Document existant',
            'type' => 'convention',
            'file_path' => 'stagiaires/'.$stagiaire->id.'/documents/convention.pdf',
            'file_name' => 'convention.pdf',
        ]);

        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'last_name' => 'Weiss-Mutation',
                'stage_price' => 1750.00,
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire->refresh();
        $this->assertSame('Weiss-Mutation', $stagiaire->last_name);
        $this->assertSame(1750.00, (float) $stagiaire->stage_price);

        // Aucune pièce existante n'a été supprimée ni remplacée.
        $this->assertSame('stagiaires/cin/recto.pdf', $stagiaire->cin_recto_path);
        $this->assertSame('stagiaires/cin/verso.pdf', $stagiaire->cin_verso_path);
        $this->assertSame('stagiaires/1/profile/profil.jpg', $stagiaire->profile_photo_path);
        $this->assertDatabaseHas('stagiaire_diplomas', ['id' => $diploma->id, 'title' => 'Diplôme existant']);
        $this->assertDatabaseHas('stagiaire_documents', ['id' => $document->id, 'title' => 'Document existant']);
    }

    /**
     * En édition, une ligne facultative vide est ignorée et une ligne
     * commencée est intégralement exigée.
     */
    public function test_update_applies_the_same_optional_section_rules(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'cin_recto_path' => 'stagiaires/cin/recto.pdf',
            'cin_verso_path' => 'stagiaires/cin/verso.pdf',
            'profile_photo_path' => 'stagiaires/profile/legacy.jpg',
        ]));

        // Ligne vide : acceptée, aucun diplôme parasite créé.
        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'diplomas' => [['title' => '', 'institution' => '', 'year' => '', 'file' => '']],
                'other_documents' => [['title' => '', 'type' => 'attestation', 'file' => '']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $stagiaire->diplomas()->get());
        $this->assertCount(0, $stagiaire->documents()->get());

        // Ligne commencée mais incomplète : refusée, aucune écriture.
        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'diplomas' => [['title' => 'Master', 'institution' => 'Université', 'year' => '', 'file' => '']],
            ]))
            ->assertSessionHasErrors(['diplomas.0.year', 'diplomas.0.file']);

        $this->assertCount(0, $stagiaire->diplomas()->get());

        // Ligne complète : ajoutée auprès des pièces existantes.
        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'diplomas' => [[
                    'title' => 'Master',
                    'institution' => 'Université de Munich',
                    'year' => 2025,
                    'file' => UploadedFile::fake()->create('master.pdf', 100, 'application/pdf'),
                ]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $stagiaire->diplomas()->get());
        Storage::disk('local')->assertExists($stagiaire->diplomas()->first()->file_path);
    }

    /**
     * En édition, un scan CIN manquant est tout de même exigé.
     */
    public function test_update_requires_missing_cin_scans(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'cin_recto_path' => 'stagiaires/cin/recto.pdf',
        ]));

        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload(['center_id' => $this->berlin->id]))
            ->assertSessionHasErrors('cin_verso');

        $this->assertDatabaseHas('stagiaires', ['id' => $stagiaire->id, 'cin_verso_path' => null]);
    }

    /**
     * Les alias du formulaire d'édition (birth_date, cin_delivered_at…) alimentent
     * les champs canoniques obligatoires : le dossier est validé et enregistré.
     */
    public function test_form_aliases_feed_the_canonical_fields(): void
    {
        $payload = $this->form();
        unset($payload['date_of_birth'], $payload['place_of_birth'], $payload['cin_issued_date'], $payload['cin_issued_place']);

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), array_merge($payload, [
                'birth_date' => '1995-02-08',
                'birth_place' => 'Lyon',
                'cin_delivered_at' => '2019-06-01',
                'cin_delivered_place' => 'Préfecture Lyon',
                'gender' => 'femme',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stagiaires', [
            'place_of_birth' => 'Lyon',
            'cin_issued_place' => 'Préfecture Lyon',
            'gender' => 'female',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 20. Absence de régression
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scénario 20 — les autres modules et les rôles autres restent inchangés.
     */
    public function test_other_modules_are_not_regressed(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.home'))->assertOk();
        $this->actingAs($this->adminBerlin)->get(route('admin.home'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('centers.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->assertOk()->assertSee('Tous les stagiaires');
    }

    /**
     * La logique « tout ou rien » des lignes facultatives est bien la source
     * unique des deux formulaires : une ligne vide n'engendre aucune règle,
     * une ligne commencée engendre toutes ses règles.
     */
    public function test_row_rules_are_generated_only_for_started_rows(): void
    {
        $request = StoreStagiaireRequest::create(route('stagiaires.store'), 'POST', [
            'diplomas' => [
                ['title' => '', 'institution' => '', 'year' => ''],
                ['title' => 'Licence', 'institution' => 'Université Hassan II', 'year' => 2022],
            ],
            'other_documents' => [
                ['title' => '', 'type' => 'attestation'],
                ['title' => 'Convention', 'type' => 'convention'],
            ],
        ]);

        $rules = (new ReflectionMethod($request, 'attachmentRowRules'))->invoke($request);

        $this->assertArrayNotHasKey('diplomas.0.title', $rules);
        $this->assertArrayNotHasKey('diplomas.0.file', $rules);
        $this->assertArrayNotHasKey('other_documents.0.type', $rules);
        $this->assertArrayNotHasKey('other_documents.0.file', $rules);

        $this->assertContains('required', $rules['diplomas.1.title']);
        $this->assertContains('required', $rules['diplomas.1.file']);
        $this->assertContains('required', $rules['other_documents.1.type']);
        $this->assertContains('required', $rules['other_documents.1.file']);

        // Les scans CIN et la photo de profil sont exigés à la création.
        $this->assertContains('required', $request->rules()['cin_recto']);
        $this->assertContains('required', $request->rules()['profile_photo']);
        $this->assertContains('required', $request->rules()['company_name']);
        // « Informations du stage » : période et montant exigés, date de fin
        // contrôlée par rapport à la date de début, montant positif ou nul.
        $this->assertContains('required', $request->rules()['stage_start_date']);
        $this->assertContains('required', $request->rules()['stage_end_date']);
        $this->assertContains('after_or_equal:stage_start_date', $request->rules()['stage_end_date']);
        $this->assertContains('required', $request->rules()['stage_price']);
        $this->assertContains('min:0', $request->rules()['stage_price']);
        // Les champs hérités de l'ancien formulaire restent facultatifs.
        $this->assertContains('nullable', $request->rules()['notes']);
    }
}
