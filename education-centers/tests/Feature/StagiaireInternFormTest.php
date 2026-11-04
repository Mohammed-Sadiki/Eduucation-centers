<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\StagiaireDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasStagiaireFormData;
use Tests\TestCase;

/**
 * Refonte du formulaire « Ajouter un stagiaire » (Mission L).
 *
 * Couvre les 13 scénarios exigés par la mission :
 *  1. Présence exacte des champs personnels (et absence des champs retirés).
 *  2. Téléchargement des photos CIN.
 *  3. Photo de profil téléchargeable + aperçu avant enregistrement.
 *  4. Bouton « + Ajouter un fichier » et fichiers multiples retirables.
 *  5. Carte Entreprise avec exactement trois champs.
 *  6. Enregistrement du nom, téléphone et lieu de l'entreprise.
 *  7. Affichage de ces informations dans la page de détails.
 *  8. Modification (formulaire « Modifier un stagiaire » aligné).
 *  9. Préservation des fichiers existants sans nouvel envoi.
 * 10. Permissions multi-centres (Super Admin / Berlin / Munich).
 * 11. Rendu responsive (mobile et ordinateur).
 * 12. Typographie grande et lisible.
 * 13. Traductions FR/AR et thèmes clair/sombre.
 */
class StagiaireInternFormTest extends TestCase
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

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa-intern@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->berlin->id)->create(['email' => 'ab-intern@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->munich->id)->create(['email' => 'am-intern@test.de']);
    }

    private function createPageHtml(User $as, array $query = []): string
    {
        return $this->actingAs($as)->get(route('stagiaires.create', $query))->assertOk()->getContent();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Champs personnels exacts
    // ──────────────────────────────────────────────────────────────────────────

    public function test_personal_section_contains_exactly_the_requested_fields(): void
    {
        $html = $this->createPageHtml($this->superAdmin);

        $this->assertStringContainsString('Informations personnelles', $html);

        foreach ([
            'name="last_name"', 'name="first_name"', 'name="date_of_birth"', 'name="nationality"',
            'name="phone"', 'name="cin_recto"', 'name="cin_verso"', 'name="profile_photo"',
            'name="whatsapp"', 'name="emergency_contact_phone"', 'name="other_files[]"',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html, "Le marqueur « {$marker} » doit être présent.");
        }

        // Aucun champ hors périmètre n'a été ajouté (ni les anciens retirés,
        // hors ceux de la section « Informations du stage » désormais exigés).
        foreach (['name="email"', 'name="address"', 'name="gender"', 'name="place_of_birth"',
            'name="cin_number"', 'name="notes"'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, "Le champ « {$absent} » ne doit plus figurer au formulaire.");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2 & 3. Photos CIN + photo de profil (aperçu, stockage privé)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cin_photos_and_profile_photo_are_uploaded_securely(): void
    {
        $recto = UploadedFile::fake()->create('recto.jpg', 300, 'image/jpeg');
        $verso = UploadedFile::fake()->create('verso.jpg', 300, 'image/jpeg');
        $photo = UploadedFile::fake()->image('profil.png', 200, 200);

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'cin_recto' => $recto,
                'cin_verso' => $verso,
                'profile_photo' => $photo,
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::firstOrFail();
        $this->assertNotNull($stagiaire->cin_recto_path);
        $this->assertNotNull($stagiaire->cin_verso_path);
        $this->assertNotNull($stagiaire->profile_photo_path);
        Storage::disk('local')->assertExists($stagiaire->cin_recto_path);
        Storage::disk('local')->assertExists($stagiaire->cin_verso_path);
        Storage::disk('local')->assertExists($stagiaire->profile_photo_path);
        $this->assertStringStartsWith('stagiaires/cin/', $stagiaire->cin_recto_path);

        // La photo est servie par une route autorisée (disque privé).
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.profile-photo', $stagiaire))
            ->assertOk();
    }

    public function test_profile_photo_preview_is_wired_before_save(): void
    {
        $html = $this->createPageHtml($this->superAdmin);

        // Aperçu Alpine : input référencé + zone d'aperçu liée à profilePreview.
        $this->assertStringContainsString('x-ref="profileInput"', $html);
        $this->assertStringContainsString('profilePreview', $html);
        $this->assertStringContainsString('x-on:change="onProfileChange', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Bouton « + Ajouter un fichier » (section facultative)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_add_a_file_button_exists_and_multiple_files_are_saved(): void
    {
        $html = $this->createPageHtml($this->superAdmin);
        $this->assertStringContainsString('id="btn-add-other-file"', $html);
        $this->assertStringContainsString('Ajouter un fichier', $html);
        $this->assertStringContainsString('name="other_files[]"', $html);
        $this->assertStringContainsString('multiple', $html);

        // Section acceptée vide…
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->stagiairePayloadWithCin([
                'center_id' => $this->berlin->id,
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, StagiaireDocument::count());

        // …et avec plusieurs fichiers : chacun devient un document « autre ».
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->stagiairePayloadWithCin([
                'center_id' => $this->berlin->id,
                'last_name' => 'Multifiles',
                'other_files' => [
                    UploadedFile::fake()->create('annexe.pdf', 80, 'application/pdf'),
                    UploadedFile::fake()->image('photo.doc.png'),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::where('last_name', 'Multifiles')->firstOrFail();
        $this->assertCount(2, $stagiaire->documents);
        $this->assertSame(['autre', 'autre'], $stagiaire->documents->pluck('type')->all());
        foreach ($stagiaire->documents as $document) {
            Storage::disk('local')->assertExists($document->file_path);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5 & 6. Carte Entreprise — exactement trois champs, enregistrés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_company_card_contains_exactly_three_fields(): void
    {
        $html = $this->createPageHtml($this->superAdmin);

        $start = strpos($html, 'Entreprise où le stagiaire effectuera son stage');
        $this->assertNotFalse($start, 'La carte Entreprise doit être rendue.');
        $end = strpos($html, '</section>', $start);
        $this->assertNotFalse($end);

        $card = substr($html, $start, $end - $start);

        preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"/', $card, $fields);
        $this->assertSame(
            ['company_name', 'company_phone', 'company_location'],
            $fields[1],
            'La carte Entreprise doit contenir exactement les trois champs demandés.'
        );
    }

    public function test_company_information_is_saved_with_the_stagiaire(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->stagiairePayloadWithCin([
                'center_id' => $this->berlin->id,
                'company_name' => 'Siemens Berlin',
                'company_phone' => '+49 30 1234567',
                'company_location' => 'Berlin, Allemagne',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stagiaires', [
            'company_name' => 'Siemens Berlin',
            'company_phone' => '+49 30 1234567',
            'company_location' => 'Berlin, Allemagne',
        ]);
    }

    public function test_missing_company_fields_are_rejected(): void
    {
        foreach (['company_name', 'company_phone', 'company_location'] as $field) {
            $payload = $this->stagiairePayloadWithCin(['center_id' => $this->berlin->id]);
            unset($payload[$field]);

            $this->actingAs($this->superAdmin)
                ->post(route('stagiaires.store'), $payload)
                ->assertSessionHasErrors($field);
        }

        $this->assertSame(0, Stagiaire::count(), 'Aucun dossier incomplet ne doit être enregistré.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. Page de détails
    // ──────────────────────────────────────────────────────────────────────────

    public function test_details_page_displays_company_and_new_fields(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'whatsapp' => '+212 6 11 22 33 44',
            'emergency_contact_phone' => '+212 522 00 00 00',
            'company_name' => 'Atlas Technologies',
            'company_phone' => '+212 522 33 44 55',
            'company_location' => 'Casablanca, Maroc',
        ]));

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.show', $stagiaire))
            ->assertOk()
            ->assertSee('Entreprise où le stagiaire effectuera son stage')
            ->assertSee('Atlas Technologies')
            ->assertSee('Casablanca, Maroc')
            ->assertSee('+212 6 11 22 33 44')
            ->assertSee('+212 522 00 00 00');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8 & 9. Édition alignée et préservation des fichiers
    // ──────────────────────────────────────────────────────────────────────────

    public function test_edit_form_uses_the_same_fields_and_prefills_company_data(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'cin_recto_path' => 'stagiaires/cin/recto.pdf',
            'cin_verso_path' => 'stagiaires/cin/verso.pdf',
            'profile_photo_path' => 'stagiaires/1/profile/profil.jpg',
            'company_name' => 'Atlas Technologies',
        ]));

        $html = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.edit', $stagiaire))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-stagiaire-form', $html);
        $this->assertStringContainsString('Informations personnelles', $html);
        $this->assertStringContainsString('Entreprise où le stagiaire effectuera son stage', $html);
        $this->assertStringContainsString('value="Atlas Technologies"', $html);

        // Modification des informations de l'entreprise.
        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'company_name' => 'Siemens Munich',
                'company_phone' => '+49 89 7654321',
                'company_location' => 'Munich, Allemagne',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stagiaires', [
            'id' => $stagiaire->id,
            'company_name' => 'Siemens Munich',
            'company_phone' => '+49 89 7654321',
            'company_location' => 'Munich, Allemagne',
        ]);
    }

    public function test_existing_files_are_preserved_when_no_new_upload(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'cin_recto_path' => 'stagiaires/cin/recto.pdf',
            'cin_verso_path' => 'stagiaires/cin/verso.pdf',
            'profile_photo_path' => 'stagiaires/1/profile/profil.jpg',
        ]));

        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'last_name' => 'Preserve',
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire->refresh();
        $this->assertSame('stagiaires/cin/recto.pdf', $stagiaire->cin_recto_path);
        $this->assertSame('stagiaires/cin/verso.pdf', $stagiaire->cin_verso_path);
        $this->assertSame('stagiaires/1/profile/profil.jpg', $stagiaire->profile_photo_path);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. Permissions multi-centres
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admins_only_manage_their_own_center(): void
    {
        // La liste d'Admin Berlin ne montre que ses stagiaires.
        Stagiaire::create(['first_name' => 'Berlinois', 'last_name' => 'A', 'center_id' => $this->berlin->id]);
        $munichois = Stagiaire::create(['first_name' => 'Munichois', 'last_name' => 'B', 'center_id' => $this->munich->id]);

        $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertSee('Berlinois')
            ->assertDontSee('Munichois');

        // Création : le centre est imposé côté serveur même si un autre est envoyé.
        $this->actingAs($this->adminBerlin)
            ->post(route('stagiaires.store'), $this->stagiairePayloadWithCin([
                'center_id' => $this->munich->id,
            ]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stagiaires', ['first_name' => 'Karim', 'center_id' => $this->berlin->id]);

        // Le formulaire d'Admin Centre ne contient pas de sélecteur de centre.
        $berlinForm = $this->createPageHtml($this->adminBerlin);
        $this->assertStringNotContainsString('name="center_id"', $berlinForm);

        // Un stagiaire d'un autre centre est invisible et intouchable.
        $this->actingAs($this->adminBerlin)->get(route('stagiaires.show', $munichois))->assertForbidden();
        $this->actingAs($this->adminBerlin)
            ->put(route('stagiaires.update', $munichois), $this->stagiairePayload(['center_id' => $this->munich->id]))
            ->assertForbidden();

        // La suppression reste réservée au Super Admin.
        $this->actingAs($this->adminBerlin)->delete(route('stagiaires.destroy', $munichois))->assertForbidden();
        $this->actingAs($this->adminMunich)->delete(route('stagiaires.destroy', $munichois))->assertForbidden();
        $this->actingAs($this->superAdmin)->delete(route('stagiaires.destroy', $munichois))->assertRedirect();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11 & 12. Responsive et typographie
    // ──────────────────────────────────────────────────────────────────────────

    public function test_form_is_responsive_across_breakpoints(): void
    {
        $html = $this->createPageHtml($this->superAdmin);

        // Grilles deux colonnes sur écran moyen+, pleine largeur sur mobile.
        $this->assertStringContainsString('sm:grid-cols-2', $html);
        $this->assertStringContainsString('sm:col-span-2', $html);
        $this->assertStringContainsString('flex-col', $html);
    }

    public function test_typography_is_large_and_readable(): void
    {
        $html = $this->createPageHtml($this->superAdmin);

        // Titres de sections : 22–26 px (text-2xl = 24 px).
        $this->assertMatchesRegularExpression('/<h2\b[^>]*class="[^"]*text-2xl/', $html);

        // Labels, champs et boutons : 16–18 px. L'échelle typographique centrale
        // des coquilles internes (.ec-admin / .ec-type) résout text-base en 17 px.
        $this->assertGreaterThanOrEqual(10, substr_count($html, 'text-base'));
        $this->assertMatchesRegularExpression('/<button\b[^>]*class="[^"]*text-base/', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 13. FR/AR et thèmes clair/sombre
    // ──────────────────────────────────────────────────────────────────────────

    public function test_form_renders_in_arabic_rtl_and_supports_dark_mode(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('locale.switch', 'ar'))
            ->assertRedirect();

        $html = $this->createPageHtml($this->superAdmin);

        // Traductions arabes de la mission présentes (pas de texte brut non traduit).
        $this->assertStringContainsString('المؤسسة التي سيجري فيها المتدرب تدريبه', $html);
        $this->assertStringContainsString('البطاقة الوطنية', $html);

        // Le thème sombre et son bascule restent câblés.
        $this->assertStringContainsString('dark:', $html);
        $this->assertStringContainsString('toggleTheme', $html);

        // RTL : la page arabe est bien rendue en direction droite-gauche.
        $this->assertStringContainsString('dir="rtl"', $html);

        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'fr'))->assertRedirect();
    }

    public function test_validation_messages_are_translated_in_both_locales(): void
    {
        $payload = $this->withoutField(
            $this->stagiairePayloadWithCin(['center_id' => $this->berlin->id]),
            'company_name'
        );

        // Français : le message d'erreur s'affiche sous le champ concerné
        // (apostrophe échappée par Blade dans le rendu HTML).
        $fr = $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->followingRedirects()
            ->post(route('stagiaires.store'), $payload)
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Le nom de l&#039;entreprise de stage est obligatoire.', $fr);

        // Arabe : bascule via le sélecteur de langue, le message passe par lang/ar.json.
        $this->actingAs($this->superAdmin)
            ->get(route('locale.switch', 'ar'))
            ->assertRedirect();

        $ar = $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->followingRedirects()
            ->post(route('stagiaires.store'), $payload)
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('اسم مؤسسة التدريب إلزامي.', $ar);

        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'fr'))->assertRedirect();
    }
}
