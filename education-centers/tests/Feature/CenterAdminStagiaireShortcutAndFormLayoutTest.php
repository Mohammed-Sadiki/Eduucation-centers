<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasStagiaireFormData;
use Tests\TestCase;

/**
 * Mission « Raccourci Admin Centre + cartes du formulaire côte à côte ».
 *
 * 1. Sur /admin/accueil, la carte « Gestion des stagiaires » doit envoyer
 *    l'Admin Centre DIRECTEMENT vers « Ajouter un stagiaire » (et non plus vers
 *    « Tous les stagiaires »). Le Super Admin garde la destination d'origine, et
 *    « Tous les stagiaires » reste atteignable depuis la sidebar du module.
 *
 * 2. Le formulaire « Ajouter un stagiaire » retrouve sa disposition en cartes
 *    côte à côte : deux colonnes équilibrées sur desktop (« Informations
 *    personnelles » à côté de « Documents et fichiers »), la section la plus
 *    longue (entreprise de stage) en pleine largeur, une seule colonne sur
 *    tablette/mobile. Aucun champ, aucune validation, aucun upload n'est modifié.
 */
class CenterAdminStagiaireShortcutAndFormLayoutTest extends TestCase
{
    use HasStagiaireFormData;
    use RefreshDatabase;

    protected Center $berlin;
    protected User $superAdmin;
    protected User $adminBerlin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->berlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);

        $this->superAdmin  = User::factory()->superAdmin()->create(['email' => 'sa-shortcut@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->berlin->id)->create(['email' => 'ab-shortcut@test.de']);
    }

    /**
     * Href de la carte « Gestion des stagiaires » sur la page d'accueil.
     */
    private function stagiairesCardHref(User $user): string
    {
        $html = $this->actingAs($user)->get(route('admin.home'))->assertOk()->getContent();

        $matched = preg_match('/<a href="([^"]+)"\s+id="card-home-stagiaires"/', $html, $m);

        $this->assertSame(1, $matched, 'La carte « Gestion des stagiaires » doit être présente sur /admin/accueil.');

        return $m[1];
    }

    /**
     * Extrait le HTML de la sidebar (<aside>…</aside>) d'une réponse.
     */
    private function sidebarHtml(string $html): string
    {
        $start = strpos($html, '<aside');
        $this->assertNotFalse($start, 'La sidebar (<aside>) doit être rendue.');
        $end = strpos($html, '</aside>', $start);
        $this->assertNotFalse($end, 'La sidebar doit être correctement fermée.');

        return substr($html, $start, $end - $start);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  1. Navigation depuis la carte de l'Accueil
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_home_card_links_directly_to_add_stagiaire(): void
    {
        $this->assertSame(route('stagiaires.create'), $this->stagiairesCardHref($this->adminBerlin));
    }

    public function test_super_admin_home_card_still_links_to_all_stagiaires(): void
    {
        $this->assertSame(route('stagiaires.index'), $this->stagiairesCardHref($this->superAdmin));
    }

    public function test_center_admin_card_destination_is_accessible_and_renders_the_form(): void
    {
        $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.create'))
            ->assertOk()
            ->assertSee(__('Ajouter un stagiaire'), false);
    }

    public function test_all_stagiaires_page_remains_reachable_from_the_sidebar(): void
    {
        // Le formulaire utilise layouts.app : la sidebar est rendue dans le
        // contexte dédié « Gestion des stagiaires ».
        $html = $this->actingAs($this->adminBerlin)->get(route('stagiaires.create'))->assertOk()->getContent();

        $sidebar = $this->sidebarHtml($html);

        $this->assertStringContainsString(route('stagiaires.index', [], false), $sidebar,
            '« Tous les stagiaires » doit rester proposé dans la sidebar.');
        $this->assertStringContainsString(__('Tous les stagiaires'), $sidebar);
        $this->assertStringContainsString(__('Ajouter un stagiaire'), $sidebar);

        // La page elle-même reste accessible.
        $this->actingAs($this->adminBerlin)->get(route('stagiaires.index'))->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  2. Disposition des cartes du formulaire
    // ──────────────────────────────────────────────────────────────────────────

    public function test_form_uses_a_two_column_grid_on_desktop_and_one_column_below(): void
    {
        $html = $this->actingAs($this->adminBerlin)->get(route('stagiaires.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">/',
            $html,
            'La grille du formulaire doit être à une colonne par défaut (mobile/tablette) et deux colonnes en desktop.'
        );
    }

    public function test_personal_information_and_documents_cards_are_side_by_side(): void
    {
        $html = $this->actingAs($this->adminBerlin)->get(route('stagiaires.create'))->assertOk()->getContent();

        // Trois cartes : personnelles, documents/ fichiers, entreprise.
        $this->assertSame(3, preg_match_all('/<section class="ec-card overflow-hidden( lg:col-span-2)?">/', $html),
            'Le formulaire doit rendre exactement trois cartes.');

        $posPersonnel = strpos($html, __('Informations personnelles'));
        $posDocuments = strpos($html, __('Documents et fichiers'));
        $posEntreprise = strpos($html, __('Entreprise où le stagiaire effectuera son stage'));

        $this->assertNotFalse($posPersonnel);
        $this->assertNotFalse($posDocuments, 'La carte « Documents et fichiers » doit exister à côté des informations personnelles.');
        $this->assertNotFalse($posEntreprise);
        $this->assertLessThan($posDocuments, $posPersonnel);
        $this->assertLessThan($posEntreprise, $posDocuments);

        // Les deux premières cartes partagent une ligne (pas de pleine largeur),
        // la section la plus longue occupe toute la largeur.
        $this->assertMatchesRegularExpression('/<section class="ec-card overflow-hidden">/', $html);
        $this->assertMatchesRegularExpression('/<section class="ec-card overflow-hidden lg:col-span-2">/', $html);

        // Numérotation cohérente 1 → 2 → 3.
        preg_match_all('/rounded-full bg-brand-100[^>]*>(\d)<\/span>/', $html, $badges);
        $this->assertSame(['1', '2', '3'], $badges[1]);
    }

    public function test_super_admin_form_keeps_the_same_layout_and_its_center_selector(): void
    {
        $html = $this->actingAs($this->superAdmin)->get(route('stagiaires.create'))->assertOk()->getContent();

        $this->assertStringContainsString(__('Documents et fichiers'), $html);
        $this->assertStringContainsString('name="center_id"', $html,
            'Le sélecteur de centre du Super Admin est conservé.');
    }

    public function test_center_admin_does_not_see_the_center_selector(): void
    {
        $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.create'))
            ->assertOk()
            ->assertDontSee('name="center_id"', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  3. Contenu du formulaire intégralement conservé
    // ──────────────────────────────────────────────────────────────────────────

    public function test_every_field_upload_and_button_is_still_rendered(): void
    {
        $html = $this->actingAs($this->adminBerlin)->get(route('stagiaires.create'))->assertOk()->getContent();

        $fields = [
            'last_name', 'first_name', 'date_of_birth', 'nationality', 'phone',
            'whatsapp', 'emergency_contact_phone',
            'cin_recto', 'cin_verso', 'profile_photo', 'other_files[]',
            'company_name', 'company_phone', 'company_location',
        ];

        foreach ($fields as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "Le champ « {$field} » doit rester présent.");
        }

        // Uploads et bouton d'enregistrement.
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('accept=".pdf,.jpg,.jpeg,.png"', $html);
        $this->assertStringContainsString('id="btn-submit-stagiaire"', $html);
        $this->assertStringContainsString(__('Enregistrer le stagiaire'), $html);
        $this->assertStringContainsString('action="'.route('stagiaires.store').'"', $html);
    }

    public function test_validation_errors_are_still_displayed_after_an_invalid_submission(): void
    {
        $payload = $this->withoutField($this->stagiairePayloadWithCin(), 'first_name');

        $html = $this->actingAs($this->adminBerlin)
            ->from(route('stagiaires.create'))
            ->followingRedirects()
            ->post(route('stagiaires.store'), $payload)
            ->assertOk()
            ->getContent();

        // Bandeau récapitulatif + champ signalé en erreur : rien n'a été perdu.
        $this->assertStringContainsString('Veuillez corriger les erreurs suivantes', $html);
        $this->assertStringContainsString('name="first_name"', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('ec-error', $html);

        // Les cartes restent rendues côte à côte même en présence d'erreurs.
        $this->assertStringContainsString(__('Documents et fichiers'), $html);
    }

    public function test_center_admin_can_still_save_a_complete_stagiaire_file(): void
    {
        $this->actingAs($this->adminBerlin)
            ->post(route('stagiaires.store'), $this->stagiairePayloadWithCin())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('stagiaires', [
            'first_name' => 'Karim',
            'last_name' => 'Benani',
            'company_name' => 'Atlas Technologies',
            'center_id' => $this->berlin->id,
        ]);

        $stagiaire = Stagiaire::firstOrFail();
        $this->assertNotNull($stagiaire->cin_recto_path ?? null, 'Les pièces justificatives doivent rester enregistrées.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  4. FR / AR · RTL
    // ──────────────────────────────────────────────────────────────────────────

    public function test_documents_card_is_translated_in_arabic_with_rtl(): void
    {
        $this->actingAs($this->adminBerlin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $html = $this->actingAs($this->adminBerlin)->get(route('stagiaires.create'))->assertOk()->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('الوثائق والملفات', $html);
        $this->assertStringContainsString('المعلومات الشخصية', $html);
    }

    public function test_teacher_space_is_not_affected(): void
    {
        $teacher = User::factory()->teacher()->create(['email' => 't-shortcut@test.de', 'center_id' => $this->berlin->id]);

        // Le professeur n'a pas accès au module stagiaires : aucune régression.
        $this->actingAs($teacher)->get(route('stagiaires.create'))->assertForbidden();
    }
}
