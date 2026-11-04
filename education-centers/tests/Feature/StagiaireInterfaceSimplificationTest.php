<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\StagiaireDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasStagiaireFormData;
use Tests\TestCase;

/**
 * Simplification complète de l'interface Gestion des stagiaires.
 *
 * Le module devient un espace à deux fonctionnalités : la sidebar ne contient
 * plus que « Tous les stagiaires » et « Ajouter un stagiaire » (pas de groupe
 * Dashboard, pas de groupe Dossiers/ Documents). L'ancienne route du tableau
 * de bord redirige proprement vers la liste, qui devient la page principale.
 * Aucune fonctionnalité métier n'est perdue : recherche, filtres, création,
 * consultation, modification et page Documents restent accessibles ; les
 * espaces professeur/élève et les autres modules sont inchangés.
 */
class StagiaireInterfaceSimplificationTest extends TestCase
{
    use HasStagiaireFormData;
    use RefreshDatabase;

    protected Center $centerBerlin;

    protected Center $centerMunich;

    protected User $superAdmin;

    protected User $adminBerlin;

    protected User $teacher;

    protected User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->centerMunich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa-stg@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab-stg@test.de']);
        $this->teacher = User::factory()->teacher($this->centerBerlin->id)->create(['email' => 'tc-stg@test.de']);
        $this->studentUser = User::factory()->student($this->centerBerlin->id)->create(['email' => 'st-stg@test.de']);
    }

    /**
     * Extrait le HTML de la sidebar (<aside>…) pour des assertions ciblées.
     */
    private function sidebarHtml($response): string
    {
        $html = $response->getContent();
        $start = strpos($html, '<aside');
        $end = strpos($html, '</aside>', $start ?: 0);

        $this->assertNotFalse($start, 'La sidebar (<aside>) doit être rendue.');
        $this->assertNotFalse($end, 'La sidebar doit être correctement fermée.');

        return substr($html, $start, $end - $start);
    }

    private function makeStagiaire(array $attrs = []): Stagiaire
    {
        return Stagiaire::create(array_merge([
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'stage_price' => 1000.00,
            'currency' => 'EUR',
            'status' => 'nouveau',
            'center_id' => $this->centerBerlin->id,
        ], $attrs));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Navigation : le module s'ouvre directement sur « Tous les stagiaires »
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_home_entry_links_directly_to_stagiaires_list(): void
    {
        $home = $this->actingAs($this->superAdmin)->get(route('admin.home'));
        $home->assertOk()->assertSee('Gestion des stagiaires');

        // La carte d'entrée pointe sur la liste, plus sur l'ancien tableau de bord.
        $home->assertSee(route('stagiaires.index'), false);
        $home->assertDontSee(route('stagiaires.dashboard'), false);
    }

    public function test_legacy_dashboard_route_redirects_to_list(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.dashboard'))
            ->assertRedirect(route('stagiaires.index'));

        // Suivi de redirection : la liste s'affiche bien (200).
        $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->get(route('stagiaires.dashboard'))
            ->assertOk()
            ->assertSee('Tous les stagiaires');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2 à 6. Sidebar : uniquement deux liens fonctionnels
    // ──────────────────────────────────────────────────────────────────────────

    public function test_sidebar_contains_only_the_two_functional_links(): void
    {
        $sidebar = $this->sidebarHtml(
            $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))
        );

        $this->assertStringContainsString('Tous les stagiaires', $sidebar);
        $this->assertStringContainsString('Ajouter un stagiaire', $sidebar);
        $this->assertStringContainsString(route('stagiaires.index'), $sidebar);
        $this->assertStringContainsString(route('stagiaires.create'), $sidebar);

        // Groupe « Gestion des stagiaires » toujours présent.
        $this->assertStringContainsString('Gestion des stagiaires', $sidebar);

        // Éléments structurels conservés : badge contexte + retour accueil.
        $this->assertStringContainsString('Espace administratif', $sidebar);
        $this->assertStringContainsString(route('admin.home'), $sidebar);

        // Absence du groupe Dashboard et de son lien.
        $this->assertStringNotContainsString('Tableau de bord', $sidebar);
        $this->assertStringNotContainsString(route('stagiaires.dashboard'), $sidebar);

        // Absence du groupe Dossiers et de ses trois liens.
        $this->assertStringNotContainsString('Dossiers complets', $sidebar);
        $this->assertStringNotContainsString('Dossiers incomplets', $sidebar);
        $this->assertStringNotContainsString('Documents', $sidebar);
        $this->assertStringNotContainsString(route('stagiaires.documents'), $sidebar);

        // Pas de <ul> orphelin (aucun espace vide inutile).
        $this->assertDoesNotMatchRegularExpression('/<ul[^>]*>\s*<\/ul>/', $sidebar);
    }

    public function test_sidebar_is_identical_on_create_page(): void
    {
        $sidebar = $this->sidebarHtml(
            $this->actingAs($this->superAdmin)->get(route('stagiaires.create'))
        );

        $this->assertStringContainsString('Tous les stagiaires', $sidebar);
        $this->assertStringContainsString('Ajouter un stagiaire', $sidebar);
        $this->assertStringNotContainsString('Tableau de bord', $sidebar);
        $this->assertStringNotContainsString('Dossiers complets', $sidebar);
        $this->assertStringNotContainsString(route('stagiaires.documents'), $sidebar);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7 à 11. Contenu principal : plus aucun élément de l'ancien dashboard
    // ──────────────────────────────────────────────────────────────────────────

    public function test_list_page_has_no_dashboard_remnants(): void
    {
        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();

        // Bandeau violet et ses composants.
        $this->assertStringNotContainsString('Espace administratif autonome', $page);
        $this->assertStringNotContainsString('Tableau de bord — Stagiaires', $page);
        $this->assertStringNotContainsString('Changer de domaine', $page);

        // Recherche rapide du dashboard (placeholder dédié).
        $this->assertStringNotContainsString('Recherche rapide d\'un stagiaire', $page);
        $this->assertStringNotContainsString('Voir la liste complète', $page);

        // Cinq des six cartes statistiques de l'ancien dashboard (libellés exclusifs).
        // La carte « Total stagiaires » est volontairement conservée : c'est la carte
        // unique demandée par la simplification finale de la page liste.
        $this->assertStringNotContainsString('Dossiers créés', $page);
        $this->assertStringNotContainsString('Récemment inscrits', $page);
        $this->assertStringNotContainsString('Actuellement au centre', $page);
        $this->assertStringNotContainsString('Pièces à régulariser', $page);
        $this->assertStringNotContainsString('Période échue', $page);

        // Quatre cartes de raccourcis.
        $this->assertStringNotContainsString('Dossiers à compléter', $page);
        $this->assertStringNotContainsString('Espace Documents', $page);
        $this->assertStringNotContainsString('Portail Domaines', $page);

        // Le bouton « Tableau de bord » de l'en-tête de la liste a disparu.
        $this->assertStringNotContainsString('Tableau de bord', $page);
    }

    public function test_create_page_has_no_dashboard_remnants(): void
    {
        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Tableau de bord — Stagiaires', $page);
        $this->assertStringNotContainsString('Recherche rapide d\'un stagiaire', $page);
    }

    public function test_dashboard_view_file_is_removed(): void
    {
        $this->assertFileDoesNotExist(
            base_path('resources/views/super-admin/stagiaires/dashboard.blade.php'),
            'La vue du tableau de bord statistique doit avoir été supprimée.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12 à 14. Fonctionnalités conservées
    // ──────────────────────────────────────────────────────────────────────────

    public function test_search_and_filters_still_work_on_the_list(): void
    {
        $this->makeStagiaire(['first_name' => 'Alice', 'last_name' => 'Muller']);
        $this->makeStagiaire(['first_name' => 'Bruno', 'last_name' => 'Schmidt', 'center_id' => $this->centerMunich->id]);

        // Recherche par nom.
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['search' => 'Muller']))
            ->assertOk()
            ->assertSee('Alice')
            ->assertDontSee('Schmidt');

        // Filtre par centre (Super Admin).
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['center_id' => $this->centerMunich->id]))
            ->assertOk()
            ->assertSee('Schmidt')
            ->assertDontSee('Alice');

        // Filtre statut.
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['status' => 'nouveau']))
            ->assertOk()
            ->assertSee('Muller');
    }

    public function test_creation_form_still_works(): void
    {
        $payload = $this->stagiairePayloadWithCin([
            'first_name' => 'Nadia',
            'last_name' => 'Front',
            'gender' => 'femme',
            'nationality' => 'Allemande',
            'email' => 'nadia.front@example.de',
            'stage_price' => 900.00,
            'currency' => 'EUR',
            'status' => 'nouveau',
            'center_id' => $this->centerBerlin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('stagiaires.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stagiaires', ['last_name' => 'Front', 'center_id' => $this->centerBerlin->id]);
    }

    public function test_creation_still_blocked_when_required_fields_missing(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), [])
            ->assertSessionHasErrors([
                'first_name', 'last_name', 'center_id', 'date_of_birth', 'nationality',
                'phone', 'whatsapp', 'emergency_contact_phone',
                'cin_recto', 'cin_verso', 'profile_photo',
                'company_name', 'company_phone', 'company_location',
                'stage_start_date', 'stage_end_date', 'stage_price',
            ]);
    }

    public function test_show_edit_and_documents_pages_remain_accessible(): void
    {
        $stagiaire = $this->makeStagiaire(['last_name' => 'Archives', 'cin_number' => 'C1234567']);

        StagiaireDocument::create([
            'stagiaire_id' => $stagiaire->id,
            'title' => 'Convention de stage',
            'type' => 'convention',
            'file_path' => 'stagiaires/docs/convention.pdf',
            'file_name' => 'convention.pdf',
        ]);

        // Consultation.
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.show', $stagiaire))
            ->assertOk()
            ->assertSee('Archives')
            ->assertSee('C1234567');

        // Modification (formulaire).
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.edit', $stagiaire))
            ->assertOk()
            ->assertSee('Archives');

        // Espace Documents : plus de bouton dans la liste (simplification finale),
        // mais la page et les fichiers restent accessibles par leur route autorisée.
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertDontSee(route('stagiaires.documents'), false);

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.documents'))
            ->assertOk()
            ->assertSee('Convention de stage')
            ->assertSee('Archives');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 15 & 16. Permissions et isolation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_keeps_full_access(): void
    {
        $this->makeStagiaire(['last_name' => 'Berlinais']);
        $this->makeStagiaire(['last_name' => 'Munichois', 'center_id' => $this->centerMunich->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertSee('Berlinais')
            ->assertSee('Munichois');

        $this->actingAs($this->superAdmin)->get(route('stagiaires.create'))->assertOk();
    }

    public function test_non_super_admin_roles_keep_previous_access_rules(): void
    {
        // L'Admin Centre accède au module (refonte : permissions Berlin/Munich).
        $this->actingAs($this->adminBerlin)->get(route('stagiaires.index'))->assertOk();
        $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.dashboard'))
            ->assertRedirect(route('stagiaires.index'));

        // Inchangé pour les autres rôles : professeur et étudiant exclus.
        $this->actingAs($this->teacher)->get(route('stagiaires.index'))->assertForbidden();
        $this->actingAs($this->studentUser)->get(route('stagiaires.index'))->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 17. FR/AR et Light/Dark
    // ──────────────────────────────────────────────────────────────────────────

    public function test_navigation_works_in_french_and_arabic_locales(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['locale' => 'ar']))
            ->assertOk();

        $sidebarAr = $this->sidebarHtml(
            $this->actingAs($this->superAdmin)->get(route('stagiaires.index', ['locale' => 'ar']))
        );
        // La sidebar arabe ne contient plus aucun des éléments retirés.
        $this->assertStringNotContainsString('Tableau de bord', $sidebarAr);
        $this->assertStringNotContainsString(route('stagiaires.dashboard'), $sidebarAr);
        $this->assertStringNotContainsString(route('stagiaires.documents'), $sidebarAr);

        // Le sélecteur de langue et le contrôle de thème restent présents.
        $page = $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->getContent();
        $this->assertStringContainsString('toggleTheme', $page);
        $this->assertStringContainsString('lang=', $page);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 18. Aucun impact sur les autres modules
    // ──────────────────────────────────────────────────────────────────────────

    public function test_other_modules_sidebars_are_untouched(): void
    {
        // Contexte domaine : la sidebar pédagogique garde son groupe Pédagogie.
        $domainSidebar = $this->sidebarHtml(
            $this->actingAs($this->superAdmin)->get(route('super-admin.domain.dashboard', 'informatique'))
        );
        $this->assertStringContainsString('Pédagogie', $domainSidebar);
        $this->assertStringNotContainsString('Tous les stagiaires', $domainSidebar);

        // Accueil administratif : cartes Communication et Finance toujours là.
        $home = $this->actingAs($this->superAdmin)->get(route('admin.home'));
        $home->assertOk()
            ->assertSee('card-home-communication', false)
            ->assertSee('Gestion financière');
    }
}
