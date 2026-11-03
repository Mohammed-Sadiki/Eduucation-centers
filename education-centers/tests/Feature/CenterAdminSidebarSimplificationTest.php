<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission « Simplification sidebar Admin Centre ».
 *
 * La sidebar center_admin est une navigation PLATE : exactement six liens
 * dans l'ordre (Dashboard, Emploi du temps, Présences, Évaluations,
 * Certificats, Notifications), sans titres de sections
 * (PÉDAGOGIE / GESTION / COMMUNICATION) ni chevrons. Les liens retirés de
 * la navigation (Domaines, Formations, Classes, Salles, Inscriptions,
 * Documents, Annonces) voient leurs pages/routes/fonctionnalités
 * intégralement conservées — seul le lien disparaît.
 *
 * La bannière supérieure « formation active » est conservée, avec son bouton
 * désormais libellé « Toutes les formations » qui pointe vers la page des
 * cartes des formations du centre (formations-generales.index).
 *
 * Deux boutons, deux destinations distinctes :
 *  - le lien « Dashboard » de la navigation ouvre le tableau de bord
 *    PÉDAGOGIQUE de la formation active (center-admin.domain.dashboard,
 *    jamais /admin/accueil) ;
 *  - le petit bouton « Accueil » de la bannière ramène à la page d'accueil
 *    générale /admin/accueil (message « Bienvenue » + cartes des modules).
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
        // Contexte « formation active » posé en session, comme dans une
        // navigation réelle : le lien Dashboard doit suivre ce domaine.
        $this->actingAs($this->adminBerlin)
            ->get(route('center-admin.domain.dashboard', 'informatique'))
            ->assertOk();

        $html = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );

        $expected = [
            route('center-admin.domain.dashboard', 'informatique'),
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
        $this->assertSame($positions, $sorted, 'Les six liens doivent apparaître dans l’ordre demandé.');

        // Le lien « Domaines » a été retiré de la navigation (la page, elle,
        // reste accessible : voir test_domaines_page_still_reachable_without_link).
        $this->assertStringNotContainsString('href="' . route('center-admin.domaines') . '"', $html);
    }

    /**
     * « Ne supprimer aucune page, route, donnée ou fonctionnalité » : la
     * page Domaines de l'Admin Centre est toujours servie, même si son lien
     * de navigation a disparu.
     */
    public function test_domaines_page_still_reachable_without_link(): void
    {
        $this->actingAs($this->adminBerlin)->get(route('center-admin.domaines'))->assertOk();

        $html = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );
        $this->assertStringNotContainsString('href="' . route('center-admin.domaines') . '"', $html);
    }

    /**
     * « Dashboard » = tableau de bord pédagogique de la formation active
     * (Admin Centre uniquement) : le premier lien de la navigation ouvre
     * « Espace {formation active} » via la route existante des dashboards de
     * domaine, et plus jamais la page d'accueil générale admin.home.
     */
    public function test_dashboard_link_opens_the_active_formation_dashboard(): void
    {
        foreach (['maconnerie', 'sante', 'informatique'] as $domain) {
            // Formation active posée en session (navigation réelle).
            $this->actingAs($this->adminBerlin)
                ->get(route('center-admin.domain.dashboard', $domain))
                ->assertOk();

            $html = $this->sidebarHtml(
                $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
            );

            // Libellé « Dashboard » (texte visible + infobulle) sur l'URL du
            // dashboard pédagogique du domaine actif.
            $this->assertMatchesRegularExpression(
                '/<a href="' . preg_quote(route('center-admin.domain.dashboard', $domain), '/') . '"[^>]*title="Dashboard"[^>]*>\s*<svg.*?<span class="flex-1 truncate"[^>]*>Dashboard<\/span>/s',
                $html,
                "Le lien « Dashboard » doit ouvrir le dashboard pédagogique du domaine {$domain}."
            );

            // Emplacement conservé : toujours le premier lien du menu.
            $this->assertLessThan(
                strpos($html, route('schedules.index')),
                strpos($html, route('center-admin.domain.dashboard', $domain)),
                'Le lien « Dashboard » doit rester en première position.'
            );

            // La destination sert bien le tableau de bord pédagogique.
            $this->actingAs($this->adminBerlin)
                ->get(route('center-admin.domain.dashboard', $domain))
                ->assertOk();
        }

        // Sans formation active en session (retour au catalogue des formations,
        // qui efface active_domain), le lien reste un dashboard pédagogique :
        // il ne retombe jamais sur la page d'accueil générale.
        $this->actingAs($this->adminBerlin)->get(route('center-admin.domaines'))->assertOk();
        $noDomain = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/center-admin\/domaines\/[a-z\-]+"[^>]*title="Dashboard"/',
            $noDomain,
            'Sans formation active, le lien « Dashboard » ouvre un dashboard de domaine.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a href="' . preg_quote(route('admin.home'), '/') . '"[^>]*title="Dashboard"/',
            $noDomain,
            'Le bouton « Dashboard » ne doit plus viser /admin/accueil.'
        );

        // La page d'accueil générale reste servie (bouton « Accueil » de la bannière).
        $this->actingAs($this->adminBerlin)->get(route('admin.home'))->assertOk();
    }

    /**
     * Parcours réel demandé en vérification : depuis « Espace Maçonnerie »,
     * le lien Dashboard y maintient, le petit bouton Accueil ouvre
     * /admin/accueil (Bienvenue + cartes des modules), puis le contrôle est
     * répété sur une autre formation et sur un autre centre.
     */
    public function test_the_two_buttons_have_distinct_functional_destinations(): void
    {
        $otherCenter = Center::factory()->create(['name' => 'Centre Agadir', 'code' => 'AGD']);
        $otherAdmin = User::factory()->centerAdmin($otherCenter->id)->create(['email' => 'agd-sb@test.de']);

        $scenarios = [
            'Tanger-like / Maçonnerie' => [$this->adminBerlin, 'maconnerie', 'Maçonnerie'],
            'même centre / Santé'       => [$this->adminBerlin, 'sante', 'Santé'],
            'autre centre / Informatique' => [$otherAdmin, 'informatique', 'Informatique'],
        ];

        foreach ($scenarios as $name => [$admin, $domain, $label]) {
            // 1. Dans le dashboard pédagogique de la formation active.
            $dashboard = $this->actingAs($admin)
                ->get(route('center-admin.domain.dashboard', $domain))
                ->assertOk()
                ->assertSee('Espace ' . $label);
            $sidebar = $this->sidebarHtml($dashboard->getContent());

            // Deux boutons, deux destinations distinctes, toutes deux rendues.
            $dashboardUrl = route('center-admin.domain.dashboard', $domain);
            $accueilUrl = route('admin.home');
            $this->assertStringContainsString('href="' . $dashboardUrl . '"', $sidebar, $name);
            $this->assertStringContainsString('href="' . $accueilUrl . '"', $sidebar, $name);
            $this->assertNotSame($dashboardUrl, $accueilUrl);

            // 2. Clic sur « Dashboard » : on reste sur le dashboard pédagogique.
            $this->actingAs($admin)
                ->get($dashboardUrl)
                ->assertOk()
                ->assertSee('Espace ' . $label)
                ->assertDontSee('Bienvenue');

            // 3. Clic sur « Accueil » : la page d'accueil générale existe.
            $home = $this->actingAs($admin)
                ->get($accueilUrl)
                ->assertOk()
                ->assertSee('Bienvenue')
                ->getContent();
            $this->assertStringContainsString('card-home-formations', $home, $name);

            // 4. De retour dans une formation : le lien Dashboard fonctionne
            // toujours et cible bien le dashboard du domaine actif.
            $after = $this->sidebarHtml(
                $this->actingAs($admin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
            );
            $this->assertStringContainsString('href="' . $dashboardUrl . '"', $after, $name);
            $this->assertDoesNotMatchRegularExpression(
                '/<a href="' . preg_quote($accueilUrl, '/') . '"[^>]*title="Dashboard"/',
                $after,
                $name . ' : le lien Dashboard ne doit jamais viser /admin/accueil.'
            );
        }
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
        // …avec le petit bouton « Accueil » : il ramène à la page d'accueil
        // générale (/admin.home), à la même place, avec le même design
        // (icône home h-3 w-3, mêmes classes) — seul son libellé change.
        $this->assertStringContainsString('id="sidebar-changer-domaine-center"', $html);
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('admin.home'), '/') . '"[^>]+id="sidebar-changer-domaine-center"[^>]*>.*?<span>Accueil<\/span>/s',
            $html,
            'Le petit bouton du bloc formation active doit être libellé « Accueil » et viser /admin/accueil.'
        );
        $this->assertStringNotContainsString('<span>Dashboard</span>', $html);
        // Les deux boutons ont bien des destinations différentes : le lien
        // « Dashboard » de la navigation ouvre le dashboard de la formation
        // active, pas /admin/accueil.
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('center-admin.domain.dashboard', 'informatique'), '/') . '"[^>]*title="Dashboard"/',
            $html,
            'Le lien « Dashboard » doit cibler le dashboard pédagogique du domaine actif.'
        );
        // …et le bouton supérieur, désormais « Toutes les formations », qui
        // cible la page des cartes des formations du centre. Icône, emplacement
        // et design du bouton sont inchangés.
        $this->assertStringContainsString('id="sidebar-tous-domaines-center"', $html);
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('formations-generales.index'), '/') . '"[^>]+id="sidebar-tous-domaines-center"/',
            $html,
            'Le bouton supérieur de la bannière doit pointer vers les formations du centre.'
        );
        $this->assertStringContainsString('Toutes les formations', $html);
        $this->assertStringNotContainsString('Tous les domaines', $html);
        // Icône et design du bouton conservés (bâtiment, h-3 w-3).
        $this->assertMatchesRegularExpression(
            '/id="sidebar-tous-domaines-center".*?<svg.*?h-3 w-3.*?M3\.75 21h16\.5/s',
            $html,
            'Le bouton doit conserver son icône d’origine.'
        );
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

        // Renommage « Accueil » → « Dashboard » réservé à l'Admin Centre :
        // le lien d'accueil du Super Admin garde son libellé d'origine.
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('admin.home'), '/') . '"[^>]*title="Accueil"/',
            $html,
            'Le Super Admin doit conserver son lien « Accueil ».'
        );
        $this->assertStringNotContainsString('title="Dashboard"', $html);
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
        // Contexte « formation active » : nécessaire pour rendre la bannière
        // et son petit bouton « Accueil ».
        $this->actingAs($this->adminBerlin)
            ->get(route('center-admin.domain.dashboard', 'sante'))
            ->assertOk();

        // FR par défaut : libellés français.
        $frSidebar = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route(self::SIDEBAR_HOST))->assertOk()->getContent()
        );
        foreach (['Dashboard', 'Emploi du temps', 'Présences', 'Évaluations', 'Certificats', 'Notifications'] as $label) {
            $this->assertStringContainsString($label, $frSidebar);
        }
        // Le petit bouton de la bannière est bien « Accueil ».
        $this->assertStringContainsString('<span>Accueil</span>', $frSidebar);

        // Bascule vers l'arabe : mêmes liens, libellés traduits, direction RTL.
        $this->actingAs($this->adminBerlin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $page = $this->actingAs($this->adminBerlin)
            ->get(route(self::SIDEBAR_HOST))
            ->assertOk()
            ->getContent();
        $arSidebar = $this->sidebarHtml($page);

        $this->assertStringContainsString('dir="rtl"', $page);
        foreach (['الجدول الزمني', 'الحضور', 'التقييمات', 'الشهادات', 'الإشعارات'] as $label) {
            $this->assertStringContainsString($label, $arSidebar, 'La sidebar doit être traduite en arabe.');
        }
        // Lien « Domaines » retiré : son libellé arabe ne doit plus apparaître.
        $this->assertStringNotContainsString('المجالات', $arSidebar);
        // Lien « Dashboard » : libellé arabe du tableau de bord, sur l'URL du
        // dashboard pédagogique de la formation active.
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('center-admin.domain.dashboard', 'sante'), '/') . '"[^>]*title="لوحة التحكم"/',
            $arSidebar,
            'Le lien du tableau de bord doit être traduit en arabe.'
        );
        // Petit bouton « Accueil » de la bannière : traduction arabe existante
        // du libellé « Accueil » (clé partagée avec les autres boutons).
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('admin.home'), '/') . '"[^>]+id="sidebar-changer-domaine-center"[^>]*>.*?<span>مرحباً<\/span>/s',
            $arSidebar,
            'Le bouton « Accueil » doit être traduit en arabe.'
        );
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
