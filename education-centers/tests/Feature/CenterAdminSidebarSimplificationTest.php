<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission « Simplification sidebar Admin Centre ».
 *
 * La sidebar center_admin devient une navigation PLATE : exactement sept
 * liens dans l'ordre (Accueil, Domaines, Emploi du temps, Présences,
 * Évaluations, Certificats, Notifications), sans titres de sections
 * (PÉDAGOGIE / GESTION / COMMUNICATION) ni chevrons. Les liens retirés de
 * la navigation (Formations, Classes, Salles, Inscriptions, Documents,
 * Annonces) voient leurs pages/routes/fonctionnalités intégralement
 * conservées — seul le lien disparaît.
 *
 * Super Admin et Professeur : strictement inchangés.
 */
class CenterAdminSidebarSimplificationTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;
    protected User $superAdmin;
    protected User $adminBerlin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);

        $this->superAdmin  = User::factory()->superAdmin()->create(['email' => 'sa-sb@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab-sb@test.de']);
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

    /**
     * Page hôte utilisant layouts.app (sidebar rendue) pour le centre.
     * (admin.home et center-admin.domaines utilisent le layout portail
     * sans sidebar ; formations.index conserve sa page et sa sidebar.)
     */
    private const SIDEBAR_HOST = 'formations.index';

    // ──────────────────────────────────────────────────────────────────────────
    //  1. Navigation plate pour Admin Centre
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_sidebar_has_flat_navigation_in_target_order(): void
    {
        $html = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );

        $expected = [
            route('admin.home'),
            route('center-admin.domaines'),
            route('schedules.index'),
            route('attendances.index'),
            route('evaluations.index'),
            route('certificates.index'),
            route('notifications.index'),
        ];

        // Tous les liens cibles présents.
        foreach ($expected as $url) {
            $this->assertStringContainsString($url, $html, "Le lien {$url} doit figurer dans la sidebar.");
        }

        // Présents dans l'ordre exact.
        $positions = array_map(fn (string $u): int => strpos($html, $u), $expected);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($positions, $sorted, 'Les sept liens doivent apparaître dans l’ordre demandé.');
    }

    public function test_center_admin_sidebar_has_no_section_titles_or_chevrons(): void
    {
        $html = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );

        // Titres de catégories supprimés (libellés traduits FR).
        $this->assertStringNotContainsString('Pédagogie', $html);
        $this->assertStringNotContainsString('Gestion', $html);
        $this->assertStringNotContainsString('Communication', $html);

        // Pas d'accordéon : le bouton toggle (aria-expanded) et le chevron
        // de section ne sont plus rendus pour un groupe sans titre.
        $this->assertStringNotContainsString('ec-accordion-chevron', $html);
        $this->assertStringNotContainsString('localStorage.setItem', $html);
    }

    public function test_removed_links_absent_but_pages_still_reachable(): void
    {
        $html = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );

        // Liens retirés de la navigation.
        foreach ([
            'classes.index'      => route('classes.index'),
            'rooms.index'        => route('rooms.index'),
            'enrollments.index'  => route('enrollments.index'),
            'documents.index'    => route('documents.index'),
            'announcements.index'=> route('announcements.index'),
        ] as $name => $url) {
            $this->assertStringNotContainsString($url, $html, "Le lien {$name} doit avoir disparu de la sidebar.");
        }

        // « Formations » : toujours une page accessible, mais plus de lien
        // de navigation vers la liste (l'ancre exacte href=… a disparu).
        $this->assertStringNotContainsString('href="' . route('formations.index') . '"', $html);

        // Les pages existent toujours et restent accessibles (aucune
        // suppression de route/contrôleur/fonctionnalité).
        foreach (['formations.index', 'classes.index', 'rooms.index', 'enrollments.index', 'documents.index', 'announcements.index'] as $routeName) {
            $this->actingAs($this->adminBerlin)->get(route($routeName))->assertOk();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  2. État actif + bloc formation active conservés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_active_link_state_on_each_sidebar_target(): void
    {
        foreach ([
            'schedules.index',
            'attendances.index',
            'evaluations.index',
            'certificates.index',
            'notifications.index',
        ] as $routeName) {
            $html = $this->sidebarHtml(
                $this->actingAs($this->adminBerlin)->get(route($routeName))->assertOk()->getContent()
            );

            // C'est BIEN le lien de la route courante qui est marqué actif.
            $this->assertMatchesRegularExpression(
                '/<a href="' . preg_quote(route($routeName), '/') . '"[^>]+aria-current="page"/',
                $html,
                "Le lien {$routeName} doit être marqué comme page active."
            );
            $this->assertStringContainsString('ec-nav-link-active', $html);
        }
    }

    public function test_active_formation_banner_and_domain_switch_links_preserved(): void
    {
        // Contexte formation active posé via le dashboard domaine (session).
        $this->actingAs($this->adminBerlin)->get(route('center-admin.domain.dashboard', 'informatique'))->assertOk();

        $response = $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST));
        $html = $this->sidebarHtml($response->assertOk()->getContent());

        // Banner « domaine actif » en haut de sidebar…
        $this->assertStringContainsString('id="sidebar-domain-banner-center"', $html);
        // …avec les liens pour changer de formation/domaine.
        $this->assertStringContainsString('id="sidebar-changer-domaine-center"', $html);
        $this->assertStringContainsString('id="sidebar-tous-domaines-center"', $html);
        $this->assertStringContainsString(route('center-admin.domaines'), $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  3. Autres rôles inchangés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_sidebar_untouched(): void
    {
        $html = $this->sidebarHtml(
            $this->actingAs($this->superAdmin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );

        // Sections et liens historiques du Super Admin toujours rendus.
        $this->assertStringContainsString('ec-accordion-chevron', $html);
        foreach (['formations.index', 'classes.index', 'rooms.index', 'enrollments.index', 'documents.index', 'announcements.index'] as $routeName) {
            $this->assertStringContainsString(route($routeName), $html, "La sidebar Super Admin doit conserver {$routeName}.");
        }
    }

    public function test_teacher_sidebar_untouched(): void
    {
        $teacherUser = User::factory()->create([
            'role'      => User::ROLE_TEACHER,
            'center_id' => $this->centerBerlin->id,
            'email'     => 't-sb@test.de',
        ]);

        $html = $this->sidebarHtml(
            $this->actingAs($teacherUser)->get(route('dashboard'))->assertOk()->getContent()
        );

        // Navigation « Mon espace » inchangée (Messagerie + Mes classes).
        $this->assertStringContainsString(route('teacher.messages'), $html);
        $this->assertStringContainsString(route('teacher.classes'), $html);
        $this->assertStringContainsString(route('teacher.salaries'), $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  4. Contextes dédiés préservés (financement)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_finance_context_sidebar_still_dedicated_for_center_admin(): void
    {
        $html = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route('super-admin.finance.dashboard'))->assertOk()->getContent()
        );

        // Le contexte financier garde sa navigation dédiée (non plate).
        $this->assertStringContainsString(route('super-admin.finance.students'), $html);
        $this->assertStringContainsString(route('super-admin.finance.expenses'), $html);
        $this->assertStringContainsString('id="sidebar-changer-finance"', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  5. Traductions FR / AR et responsive
    // ──────────────────────────────────────────────────────────────────────────

    public function test_flat_sidebar_renders_translated_labels_in_arabic_rtl(): void
    {
        // FR par défaut : libellés français.
        $frSidebar = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );
        foreach (['Emploi du temps', 'Présences', 'Évaluations', 'Certificats', 'Notifications'] as $label) {
            $this->assertStringContainsString($label, $frSidebar);
        }

        // Bascule vers l'arabe : mêmes liens, libellés traduits, direction RTL.
        $this->actingAs($this->adminBerlin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $page = $this->actingAs($this->adminBerlin)
            ->get(route(self::SIDEBAR_HOST))
            ->assertOk()
            ->getContent();
        $arSidebar = $this->sidebarHtml($page);

        $this->assertStringContainsString('dir="rtl"', $page);
        foreach (['المجالات', 'الجدول الزمني', 'الحضور', 'التقييمات', 'الشهادات', 'الإشعارات'] as $label) {
            $this->assertStringContainsString($label, $arSidebar, 'La sidebar doit être traduite en arabe.');
        }
        // Toujours plate : aucun titre de catégorie même en arabe.
        $this->assertStringNotContainsString('ec-accordion-chevron', $arSidebar);

        // Retour au français pour les requêtes suivantes.
        $this->actingAs($this->adminBerlin)->get(route('locale.switch', 'fr'));
    }

    public function test_sidebar_keeps_responsive_and_collapse_mechanics(): void
    {
        $html = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );

        // Mécanisme responsive/ réductible inchangé (off-canvas mobile +
        // mode icônes desktop piloté par Alpine $store.ui).
        $this->assertStringContainsString('ec-sidebar-panel', $html);
        $this->assertStringContainsString('lg:static', $html);
        $this->assertStringContainsString('$store.ui.sidebarCollapsed', $html);
        $this->assertStringContainsString('aria-label="', $html);
        // Mode icônes : chaque lien garde son title (libellé accessible).
        $this->assertStringContainsString('title="', $html);
    }
}
