<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nettoyage de la sidebar des domaines de formation.
 *
 * Les six entrées Messagerie, Traductions, Audit Logs, Factures, Paiements et
 * Utilisateurs sont retirées de la sidebar pédagogique (Super Admin + Admin
 * Centres, tous domaines). Vérifie : disparition des liens, sections restantes
 * non vides et sans <ul> orphelin, et conservation des fonctionnalités ailleurs
 * (Communication depuis l'accueil, module Gestion financière, routes intactes,
 * espaces enseignant/élève inchangés).
 */
class SidebarDomainCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;
    protected Center $centerMunich;
    protected User $superAdmin;
    protected User $adminBerlin;
    protected User $adminMunich;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->centerMunich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['email' => 'am@test.de']);
    }

    /**
     * Extrait le HTML de la sidebar (<aside>…) pour des assertions ciblées,
     * indépendantes de la topbar (qui conserve légitimement la Messagerie).
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

    /**
     * Les six entrées proscrites : libellés + URLs de routes.
     *
     * @return array<string, array{0: string, 1: string}> label => [route name, URI attendue absente]
     */
    private function forbiddenEntries(): array
    {
        return [
            'Messagerie' => ['messages.index', __('Messagerie')],
            'Audit Logs' => ['audit.index', __('Audit Logs')],
            'Factures' => ['invoices.index', __('Factures')],
            'Paiements' => ['payments.index', __('Paiements')],
            'Utilisateurs' => ['users.index', __('Utilisateurs')],
        ];
    }

    private function assertSidebarIsClean(string $sidebar, bool $withTranslations, bool $flatCenterNav = false): void
    {
        foreach ($this->forbiddenEntries() as $label => [$routeName, $text]) {
            $this->assertStringNotContainsString($text, $sidebar, "Le libellé « {$label} » ne doit plus apparaître dans la sidebar.");
            $this->assertStringNotContainsString(route($routeName), $sidebar, "L'URL de « {$label} » ne doit plus apparaître dans la sidebar.");
        }

        if ($withTranslations) {
            $this->assertStringNotContainsString(__('Traductions'), $sidebar);
            $this->assertStringNotContainsString(route('super-admin.translations.index'), $sidebar);
        }

        // Sections conservées : aucun espace vide (le rendu saute les groupes vides).
        $this->assertDoesNotMatchRegularExpression('/<ul[^>]*>\s*<\/ul>/', $sidebar, 'Aucune section vide ne doit être rendue.');
        $this->assertStringNotContainsString(route('students.index'), $sidebar);
        $this->assertStringNotContainsString(route('teachers.index'), $sidebar);

        if ($flatCenterNav) {
            // Mission « sidebar plate Admin Centre » : navigation plate de
            // six liens. Les entrées Domaines / Documents / Annonces /
            // Formations / Classes / Salles / Inscriptions ne sont plus dans
            // le MENU, mais leurs pages et routes restent intactes
            // (vérifié ailleurs).
            foreach ([
                // Premier lien : libellé « Dashboard » (dashboard pédagogique
                // de la formation active).
                __('Dashboard'), __('Emploi du temps'), __('Présences'),
                __('Évaluations'), __('Certificats'), __('Notifications'),
            ] as $label) {
                $this->assertStringContainsString($label, $sidebar);
            }

            $this->assertStringNotContainsString('href="' . route('center-admin.domaines') . '"', $sidebar);
            $this->assertStringNotContainsString(__('Documents'), $sidebar);
            $this->assertStringNotContainsString(__('Annonces'), $sidebar);
            $this->assertStringNotContainsString(__('Classes'), $sidebar);

            return;
        }

        $this->assertStringContainsString(__('Présences'), $sidebar);
        $this->assertStringContainsString(__('Évaluations'), $sidebar);
        $this->assertStringContainsString(__('Documents'), $sidebar);
        $this->assertStringContainsString(__('Certificats'), $sidebar);
        $this->assertStringContainsString(__('Notifications'), $sidebar);
        $this->assertStringContainsString(__('Annonces'), $sidebar);
        $this->assertStringContainsString(__('Formations'), $sidebar);
        $this->assertStringContainsString(__('Classes'), $sidebar);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Disparition des cinq entrées — Super Admin (dans un domaine)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_domain_sidebar_no_longer_lists_the_five_entries(): void
    {
        foreach (['sante', 'informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'] as $slug) {
            $response = $this->actingAs($this->superAdmin)
                ->get(route('super-admin.domain.dashboard', $slug))
                ->assertOk();

            $this->assertSidebarIsClean($this->sidebarHtml($response), withTranslations: true);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Disparition des cinq entrées — Admin Centres (Berlin & Munich)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_domain_sidebar_no_longer_lists_the_five_entries(): void
    {
        foreach ([$this->adminBerlin, $this->adminMunich] as $admin) {
            $response = $this->actingAs($admin)
                ->get(route('center-admin.domain.dashboard', 'informatique'))
                ->assertOk();

            $this->assertSidebarIsClean($this->sidebarHtml($response), withTranslations: false, flatCenterNav: true);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Fonctionnalités conservées ailleurs
    // ──────────────────────────────────────────────────────────────────────────

    public function test_communication_remains_accessible_from_admin_home(): void
    {
        foreach ([$this->superAdmin, $this->adminBerlin] as $admin) {
            $response = $this->actingAs($admin)
                ->get(route('admin.home'))
                ->assertOk()
                ->assertSee('card-home-communication', false)
                ->assertSee(route('messages.index'), false);

            // La page de messagerie elle-même reste fonctionnelle.
            $this->actingAs($admin)->get(route('messages.index'))->assertOk();
        }
    }

    public function test_finance_module_sidebar_still_lists_payments_for_super_admin(): void
    {
        // Le contexte « Gestion financière » (module indépendant) est intact.
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.students'))
            ->assertOk();

        $sidebar = $this->sidebarHtml($response);
        $this->assertStringContainsString(__('Paiements'), $sidebar);
        $this->assertStringContainsString(route('super-admin.finance.students'), $sidebar);
    }

    public function test_routes_and_pages_of_removed_entries_still_work_when_accessed_directly(): void
    {
        // Audit, Paiements, Factures : routes/contrôleurs préservés pour les admins.
        foreach ([$this->superAdmin, $this->adminBerlin] as $admin) {
            $this->actingAs($admin)->get(route('audit.index'))->assertOk();
            $this->actingAs($admin)->get(route('payments.index'))->assertOk();
            $this->actingAs($admin)->get(route('invoices.index'))->assertOk();
            // Gestion des utilisateurs : accessible par sa route autorisée malgré le retrait du lien.
            $this->actingAs($admin)->get(route('users.index'))->assertOk();
        }

        // Traductions : réservé Super Admin, page toujours fonctionnelle.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.translations.index'))
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Autres espaces : non impactés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_student_sidebar_keeps_its_own_payments_page(): void
    {
        $student = User::factory()->student($this->centerBerlin->id)->create(['email' => 'st@test.de']);

        $response = $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk();

        $sidebar = $this->sidebarHtml($response);
        $this->assertStringContainsString(__('Mes paiements'), $sidebar);
        $this->assertStringContainsString(route('student.payments'), $sidebar);
    }
}
