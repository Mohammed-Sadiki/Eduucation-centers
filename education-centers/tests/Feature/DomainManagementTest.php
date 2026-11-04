<?php

namespace Tests\Feature;

use App\Http\Controllers\SuperAdminDomainController;
use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tests couvrant l'activation des 6 domaines de formation.
 *
 * Couvre les points de la spécification :
 *  1.  Super Admin peut accéder à la page des domaines.
 *  2.  Santé reste accessible.
 *  3.  Informatique est accessible.
 *  4.  Hôtellerie est accessible.
 *  5.  Maçonnerie est accessible.
 *  6.  Design intérieur est accessible.
 *  7.  Design graphique est accessible.
 *  8.  Aucun des cinq nouveaux domaines n'affiche « Bientôt disponible ».
 *  9.  Chaque carte mène vers le bon contexte (route correcte).
 *  10. Le contexte actif apparaît correctement dans l'interface (session + sidebar).
 *  11. « Changer de domaine » retourne vers /super-admin/domaines.
 *  12. Un utilisateur non Super Admin ne peut pas accéder aux routes Super Admin des domaines.
 *  13. Stagiaires reste indépendant.
 *  14. Student reste indépendant de Stagiaire.
 *  15. Santé n'a aucune régression.
 *  16. L'isolation multi-centres existante reste fonctionnelle.
 *  17. (partiel) Filtrage par domaine à consolider quand la colonne domain sera ajoutée.
 */
class DomainManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Center $center;

    protected User $superAdmin;

    protected User $centerAdmin;

    protected User $teacher;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->center = Center::create([
            'name' => 'Centre Test',
            'code' => 'TEST01',
            'city' => 'Rabat',
            'country' => 'Maroc',
            'status' => 'active',
        ]);

        $this->superAdmin = User::factory()->superAdmin()->create();

        $this->centerAdmin = User::factory()->centerAdmin($this->center->id)->create();

        $this->teacher = User::factory()->create([
            'role' => 'teacher',
            'center_id' => $this->center->id,
        ]);

        $this->student = User::factory()->create([
            'role' => 'student',
            'center_id' => $this->center->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 1 — Super Admin peut accéder à la page des domaines
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_access_domaines_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));

        $response->assertStatus(200);
        $response->assertSee('Formations générales');
    }

    public function test_domaines_page_lists_all_six_domain_cards(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));

        $response->assertStatus(200);
        $response->assertSee('Santé');
        $response->assertSee('Informatique');
        $response->assertSee('Hôtellerie');
        $response->assertSee('Maçonnerie');
        $response->assertSee('Design intérieur');
        $response->assertSee('Design graphique');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 2 — Santé reste accessible (pas de régression)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_sante_dashboard_is_accessible_via_named_route(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.sante.dashboard'));

        $response->assertStatus(200);
    }

    public function test_sante_dashboard_sets_active_domain_session(): void
    {
        $this->actingAs($this->superAdmin)->get(route('super-admin.sante.dashboard'));

        $this->assertEquals('sante', session('active_domain'));
    }

    public function test_sante_dashboard_shows_domain_label(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.sante.dashboard'));

        $response->assertSee('Santé');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 3 — Informatique est accessible
    // ──────────────────────────────────────────────────────────────────────────

    public function test_informatique_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        $response->assertStatus(200);
    }

    public function test_informatique_dashboard_sets_session(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        $this->assertEquals('informatique', session('active_domain'));
    }

    public function test_informatique_dashboard_shows_correct_label(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        $response->assertSee('Informatique');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 4 — Hôtellerie est accessible
    // ──────────────────────────────────────────────────────────────────────────

    public function test_hotellerie_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'hotellerie'));

        $response->assertStatus(200);
    }

    public function test_hotellerie_dashboard_sets_session(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'hotellerie'));

        $this->assertEquals('hotellerie', session('active_domain'));
    }

    public function test_hotellerie_dashboard_shows_correct_label(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'hotellerie'));

        $response->assertSee('Hôtellerie');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 5 — Maçonnerie est accessible
    // ──────────────────────────────────────────────────────────────────────────

    public function test_maconnerie_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'maconnerie'));

        $response->assertStatus(200);
    }

    public function test_maconnerie_dashboard_sets_session(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'maconnerie'));

        $this->assertEquals('maconnerie', session('active_domain'));
    }

    public function test_maconnerie_dashboard_shows_correct_label(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'maconnerie'));

        $response->assertSee('Maçonnerie');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 6 — Design intérieur est accessible
    // ──────────────────────────────────────────────────────────────────────────

    public function test_design_interieur_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'design-interieur'));

        $response->assertStatus(200);
    }

    public function test_design_interieur_dashboard_sets_session(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'design-interieur'));

        $this->assertEquals('design-interieur', session('active_domain'));
    }

    public function test_design_interieur_dashboard_shows_correct_label(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'design-interieur'));

        $response->assertSee('Design intérieur');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 7 — Design graphique est accessible
    // ──────────────────────────────────────────────────────────────────────────

    public function test_design_graphique_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'design-graphique'));

        $response->assertStatus(200);
    }

    public function test_design_graphique_dashboard_sets_session(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'design-graphique'));

        $this->assertEquals('design-graphique', session('active_domain'));
    }

    public function test_design_graphique_dashboard_shows_correct_label(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'design-graphique'));

        $response->assertSee('Design graphique');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 8 — Aucun des cinq nouveaux domaines n'affiche « Bientôt disponible »
    // ──────────────────────────────────────────────────────────────────────────

    public function test_domaines_page_does_not_show_bientot_disponible(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));

        $response->assertDontSee('Bientôt disponible');
        $response->assertDontSee('Module en cours de préparation');
    }

    public function test_informatique_card_is_clickable_link_not_disabled(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));

        $response->assertSee('card-domaine-informatique');
        $response->assertDontSee('cursor-not-allowed');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 9 — Chaque carte mène vers le bon contexte (route correcte)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_domaines_page_contains_correct_link_for_sante(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));
        $response->assertSee(route('super-admin.sante.dashboard'));
    }

    public function test_domaines_page_contains_correct_link_for_informatique(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));
        $response->assertSee(route('super-admin.domain.dashboard', 'informatique'));
    }

    public function test_domaines_page_contains_correct_link_for_hotellerie(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));
        $response->assertSee(route('super-admin.domain.dashboard', 'hotellerie'));
    }

    public function test_domaines_page_contains_correct_link_for_maconnerie(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));
        $response->assertSee(route('super-admin.domain.dashboard', 'maconnerie'));
    }

    public function test_domaines_page_contains_correct_link_for_design_interieur(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));
        $response->assertSee(route('super-admin.domain.dashboard', 'design-interieur'));
    }

    public function test_domaines_page_contains_correct_link_for_design_graphique(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));
        $response->assertSee(route('super-admin.domain.dashboard', 'design-graphique'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 10 — Le contexte actif apparaît correctement dans l'interface
    // ──────────────────────────────────────────────────────────────────────────

    public function test_domain_dashboard_shows_active_domain_badge(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        $response->assertSee('Formation générale active');
        $response->assertSee('Espace Informatique');
    }

    public function test_sante_domain_dashboard_shows_active_domain_badge(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.sante.dashboard'));

        $response->assertSee('Formation générale active');
        $response->assertSee('Espace Santé');
    }

    public function test_domain_dashboard_url_matches_domain(): void
    {
        foreach (['informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'] as $slug) {
            $response = $this->actingAs($this->superAdmin)
                ->get('/super-admin/domaines/'.$slug);

            $response->assertStatus(200);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 11 — « Changer de domaine » retourne vers /super-admin/domaines
    // ──────────────────────────────────────────────────────────────────────────

    public function test_domain_dashboard_shows_changer_de_domaine_button(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        $response->assertSee('Changer de formation générale');
        $response->assertSee(route('super-admin.domaines'));
    }

    public function test_sante_dashboard_shows_changer_de_domaine_button(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.sante.dashboard'));

        $response->assertSee('Changer de formation générale');
        $response->assertSee(route('super-admin.domaines'));
    }

    public function test_changer_de_domaine_btn_has_correct_id(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'hotellerie'));

        $response->assertSee('btn-retour-domaines');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 12 — Un utilisateur non Super Admin ne peut pas accéder aux routes Super Admin
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_cannot_access_domaines_page(): void
    {
        $response = $this->actingAs($this->centerAdmin)->get(route('super-admin.domaines'));
        $response->assertStatus(403);
    }

    public function test_teacher_cannot_access_domaines_page(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('super-admin.domaines'));
        $response->assertStatus(403);
    }

    public function test_student_cannot_access_domaines_page(): void
    {
        $response = $this->actingAs($this->student)->get(route('super-admin.domaines'));
        $response->assertStatus(403);
    }

    public function test_center_admin_cannot_access_sante_dashboard(): void
    {
        $response = $this->actingAs($this->centerAdmin)->get(route('super-admin.sante.dashboard'));
        $response->assertStatus(403);
    }

    public function test_center_admin_cannot_access_informatique_dashboard(): void
    {
        $response = $this->actingAs($this->centerAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));
        $response->assertStatus(403);
    }

    public function test_teacher_cannot_access_domain_dashboards(): void
    {
        foreach (['informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'] as $slug) {
            $response = $this->actingAs($this->teacher)
                ->get(route('super-admin.domain.dashboard', $slug));
            $response->assertStatus(403);
        }
    }

    public function test_unauthenticated_user_is_redirected_from_domaines(): void
    {
        $response = $this->get(route('super-admin.domaines'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_domain_dashboard(): void
    {
        $response = $this->get(route('super-admin.domain.dashboard', 'informatique'));
        $response->assertRedirect(route('login'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 13 — Stagiaires reste indépendant
    // ──────────────────────────────────────────────────────────────────────────

    public function test_stagiaires_dashboard_is_still_accessible(): void
    {
        // L'ancienne entrée redirige proprement vers la liste « Tous les stagiaires ».
        $response = $this->actingAs($this->superAdmin)->get(route('stagiaires.dashboard'));
        $response->assertRedirect(route('stagiaires.index'));

        $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->get(route('stagiaires.dashboard'))
            ->assertOk();
    }

    public function test_stagiaires_route_is_independent_of_domain_routes(): void
    {
        // Accéder à un domaine ne doit pas affecter la route Stagiaires
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        // La route Stagiaires reste indépendante (liste accessible, statut 200)
        $response = $this->actingAs($this->superAdmin)->get(route('stagiaires.index'));
        $response->assertStatus(200);
    }

    public function test_domaines_page_no_longer_shows_administrative_section(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));

        $response->assertDontSee('Gestion administrative');
        $response->assertDontSee('id="card-stagiaires"', false);
        $response->assertDontSee('id="card-gestion-financiere"', false);

        // Stagiaires est désormais accessible depuis la page d'accueil principale /admin/accueil
        $homeResponse = $this->actingAs($this->superAdmin)->get(route('admin.home'));
        $homeResponse->assertSee('Gestion des stagiaires');
        // L'entrée du module pointe directement sur la liste « Tous les stagiaires ».
        $homeResponse->assertSee(route('stagiaires.index'), false);
    }

    public function test_stagiaires_is_not_listed_as_a_formation_domain(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));

        // Stagiaires doit être dans la section "Gestion administrative", pas "Domaines de formation"
        $content = $response->getContent();

        // Vérifier que Stagiaires n'est pas dans les routes de domaines de formation
        $this->assertStringNotContainsString(
            route('super-admin.domain.dashboard', 'stagiaires'),
            $content,
            'Stagiaires ne doit pas avoir de route de domaine de formation'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 14 — Student reste indépendant de Stagiaire
    // ──────────────────────────────────────────────────────────────────────────

    public function test_student_module_is_independent_of_stagiaire(): void
    {
        // Students ont des routes indépendantes
        $response = $this->actingAs($this->superAdmin)->get(route('students.index'));
        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 15 — Santé n'a aucune régression
    // ──────────────────────────────────────────────────────────────────────────

    public function test_sante_route_still_exists_with_original_name(): void
    {
        $this->assertTrue(Route::has('super-admin.sante.dashboard'));
    }

    public function test_sante_dashboard_url_is_correct(): void
    {
        $url = route('super-admin.sante.dashboard');
        $this->assertEquals('/super-admin/domaines/sante', parse_url($url, PHP_URL_PATH));
    }

    public function test_sante_dashboard_redirects_to_generic_view(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.sante.dashboard'));

        // La vue générée est super-admin.domain.dashboard (via l'alias santeDashboard)
        $response->assertStatus(200);
        $response->assertViewIs('super-admin.domain.dashboard');
    }

    public function test_sante_domain_constant_exists_in_controller(): void
    {
        $this->assertArrayHasKey('sante', SuperAdminDomainController::DOMAINS);
        $this->assertEquals('Santé', SuperAdminDomainController::DOMAINS['sante']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 16 — L'isolation multi-centres reste fonctionnelle
    // ──────────────────────────────────────────────────────────────────────────

    public function test_domain_dashboard_does_not_require_center_id(): void
    {
        // Le Super Admin n'a pas de center_id, il doit quand même accéder aux dashboards
        $this->assertNull($this->superAdmin->center_id);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        $response->assertStatus(200);
    }

    public function test_center_admin_is_scoped_to_own_center(): void
    {
        // Un center_admin ne peut pas accéder aux routes Super Admin des domaines
        $response = $this->actingAs($this->centerAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        $response->assertStatus(403);
    }

    public function test_super_admin_has_no_center_constraint(): void
    {
        $this->assertNull($this->superAdmin->center_id);
        $this->assertTrue($this->superAdmin->isSuperAdmin());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POINT 17 — Tous les domaines DOMAINS définis dans le contrôleur
    // ──────────────────────────────────────────────────────────────────────────

    public function test_all_six_domains_are_defined_in_controller_constant(): void
    {
        $domains = SuperAdminDomainController::DOMAINS;

        $this->assertCount(6, $domains);
        $this->assertArrayHasKey('sante', $domains);
        $this->assertArrayHasKey('informatique', $domains);
        $this->assertArrayHasKey('hotellerie', $domains);
        $this->assertArrayHasKey('maconnerie', $domains);
        $this->assertArrayHasKey('design-interieur', $domains);
        $this->assertArrayHasKey('design-graphique', $domains);
    }

    public function test_unknown_domain_slug_returns_404(): void
    {
        // Un slug inconnu comme "cuisine" ne doit pas être accepté
        $response = $this->actingAs($this->superAdmin)
            ->get('/super-admin/domaines/cuisine');

        // Soit 404 (route non matchée car pas dans le pattern where) soit 404 via abort_unless
        $response->assertStatus(404);
    }

    public function test_domaines_page_resets_active_domain_session(): void
    {
        // Après avoir visité un domaine, retourner à /domaines doit effacer la session
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'));

        $this->assertEquals('informatique', session('active_domain'));

        $this->actingAs($this->superAdmin)->get(route('super-admin.domaines'));

        $this->assertNull(session('active_domain'));
    }

    public function test_all_domain_dashboards_show_changer_de_domaine(): void
    {
        $slugs = ['informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'];

        foreach ($slugs as $slug) {
            $response = $this->actingAs($this->superAdmin)
                ->get(route('super-admin.domain.dashboard', $slug));

            $response->assertSee('Changer de formation générale', false);
        }
    }

    public function test_all_domain_dashboards_show_stats_cards(): void
    {
        $slugs = ['sante', 'informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'];

        foreach ($slugs as $slug) {
            if ($slug === 'sante') {
                $response = $this->actingAs($this->superAdmin)->get(route('super-admin.sante.dashboard'));
            } else {
                $response = $this->actingAs($this->superAdmin)
                    ->get(route('super-admin.domain.dashboard', $slug));
            }

            $response->assertStatus(200);
            $response->assertSee('Étudiants');
            $response->assertSee('Professeurs');
            $response->assertSee('Formations');
        }
    }
}
