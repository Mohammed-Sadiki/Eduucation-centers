<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSidebarSimplificationTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerTanger;
    protected Center $centerAgadir;
    protected User $superAdmin;
    protected User $centerAdmin;
    protected User $secretary;
    protected User $teacher;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerTanger = Center::create([
            'name' => 'Centre Tanger',
            'code' => 'TGR',
            'city' => 'Tanger',
            'country' => 'Morocco',
            'status' => 'active',
        ]);

        $this->centerAgadir = Center::create([
            'name' => 'Centre Agadir',
            'code' => 'AGA',
            'city' => 'Agadir',
            'country' => 'Morocco',
            'status' => 'active',
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
        ]);

        $this->centerAdmin = User::create([
            'name' => 'Admin Tanger',
            'email' => 'admin.tanger@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CENTER_ADMIN,
            'center_id' => $this->centerTanger->id,
            'status' => 'active',
        ]);

        $this->secretary = User::create([
            'name' => 'Secrétaire Tanger',
            'email' => 'sec.tanger@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerTanger->id,
            'status' => 'active',
        ]);

        $this->teacher = User::create([
            'name' => 'Professeur Tanger',
            'email' => 'teacher.tanger@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_TEACHER,
            'center_id' => $this->centerTanger->id,
            'status' => 'active',
        ]);

        $this->student = User::create([
            'name' => 'Étudiant Tanger',
            'email' => 'student.tanger@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_STUDENT,
            'center_id' => $this->centerTanger->id,
            'status' => 'active',
        ]);
    }

    private function getSidebar(string $html): string
    {
        $start = strpos($html, '<aside');
        $this->assertNotFalse($start, 'La balise <aside> de la sidebar doit être présente.');
        $end = strpos($html, '</aside>', $start);
        $this->assertNotFalse($end, 'La balise fermante </aside> doit être présente.');

        return substr($html, $start, $end - $start + strlen('</aside>'));
    }

    /**
     * 1. Super Admin : la section GESTION FINANCIÈRE contient uniquement Paiements, Dépenses, Recouvrement.
     */
    public function test_super_admin_finance_sidebar_contains_only_three_items_in_gestion_financiere(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.finance.students'));
        $response->assertOk();

        $sidebar = $this->getSidebar($response->getContent());

        // La section Gestion financière existe
        $this->assertStringContainsString(__('Gestion financière'), $sidebar);

        // Les 4 liens demandés sont présents (Paiements, Dépenses, Recouvrement, Configuration des prix)
        $this->assertStringContainsString(route('super-admin.finance.students'), $sidebar);
        $this->assertStringContainsString(route('super-admin.finance.expenses'), $sidebar);
        $this->assertStringContainsString(route('super-admin.finance.recovery'), $sidebar);
        $this->assertStringContainsString(route('super-admin.finance.configuration'), $sidebar);

        // Libellés visibles
        $this->assertStringContainsString(__('Paiements'), $sidebar);
        $this->assertStringContainsString(__('Dépenses'), $sidebar);
        $this->assertStringContainsString(__('Recouvrement'), $sidebar);
        $this->assertStringContainsString(__('Configuration des prix'), $sidebar);

        // Ordre respecté : Paiements < Dépenses < Recouvrement < Configuration des prix
        $posPaiements = strpos($sidebar, route('super-admin.finance.students'));
        $posDepenses = strpos($sidebar, route('super-admin.finance.expenses'));
        $posRecouvrement = strpos($sidebar, route('super-admin.finance.recovery'));
        $posConfig = strpos($sidebar, route('super-admin.finance.configuration'));

        $this->assertTrue($posPaiements < $posDepenses, 'Paiements doit précéder Dépenses');
        $this->assertTrue($posDepenses < $posRecouvrement, 'Dépenses doit précéder Recouvrement');
        $this->assertTrue($posRecouvrement < $posConfig, 'Recouvrement doit précéder Configuration des prix');

        // Aucun autre lien de l'ancien module ne figure dans la sidebar
        $this->assertStringNotContainsString(route('super-admin.finance.bank'), $sidebar);
        $this->assertStringNotContainsString(route('super-admin.finance.accounting'), $sidebar);
        $this->assertStringNotContainsString(route('super-admin.finance.history'), $sidebar);
        $this->assertStringNotContainsString(route('super-admin.finance.reports'), $sidebar);

        // Les anciennes entrées Dashboard / Statistiques ont été retirées des liens
        $this->assertStringNotContainsString('href="' . route('super-admin.finance.dashboard') . '"', $sidebar);
        $this->assertStringNotContainsString('href="' . route('super-admin.finance.statistics') . '"', $sidebar);
    }

    /**
     * 2. Admin Centre : la section GESTION FINANCIÈRE contient uniquement Paiements, Dépenses, Recouvrement, Configuration.
     */
    public function test_center_admin_finance_sidebar_contains_only_three_items_in_gestion_financiere(): void
    {
        $response = $this->actingAs($this->centerAdmin)->get(route('super-admin.finance.students'));
        $response->assertOk();

        $sidebar = $this->getSidebar($response->getContent());

        // La section Gestion financière existe
        $this->assertStringContainsString(__('Gestion financière'), $sidebar);

        // Les 4 liens sont présents
        $this->assertStringContainsString(route('super-admin.finance.students'), $sidebar);
        $this->assertStringContainsString(route('super-admin.finance.expenses'), $sidebar);
        $this->assertStringContainsString(route('super-admin.finance.recovery'), $sidebar);
        $this->assertStringContainsString(route('super-admin.finance.configuration'), $sidebar);

        // Libellés visibles
        $this->assertStringContainsString(__('Paiements'), $sidebar);
        $this->assertStringContainsString(__('Dépenses'), $sidebar);
        $this->assertStringContainsString(__('Recouvrement'), $sidebar);
        $this->assertStringContainsString(__('Configuration des prix'), $sidebar);

        // Ordre respecté : Paiements < Dépenses < Recouvrement < Configuration des prix
        $posPaiements = strpos($sidebar, route('super-admin.finance.students'));
        $posDepenses = strpos($sidebar, route('super-admin.finance.expenses'));
        $posRecouvrement = strpos($sidebar, route('super-admin.finance.recovery'));
        $posConfig = strpos($sidebar, route('super-admin.finance.configuration'));

        $this->assertTrue($posPaiements < $posDepenses, 'Paiements doit précéder Dépenses');
        $this->assertTrue($posDepenses < $posRecouvrement, 'Dépenses doit précéder Recouvrement');
        $this->assertTrue($posRecouvrement < $posConfig, 'Recouvrement doit précéder Configuration des prix');

        // Aucun autre lien superflu
        $this->assertStringNotContainsString(route('super-admin.finance.bank'), $sidebar);
        $this->assertStringNotContainsString(route('super-admin.finance.accounting'), $sidebar);
        $this->assertStringNotContainsString(route('super-admin.finance.history'), $sidebar);
        $this->assertStringNotContainsString(route('super-admin.finance.reports'), $sidebar);

        // Les anciennes entrées Dashboard / Statistiques ont été retirées des liens
        $this->assertStringNotContainsString('href="' . route('super-admin.finance.dashboard') . '"', $sidebar);
        $this->assertStringNotContainsString('href="' . route('super-admin.finance.statistics') . '"', $sidebar);
    }

    /**
     * 3. Vérification de l'état actif sur les trois pages pour Super Admin et Admin Centre.
     */
    public function test_active_state_is_correct_on_the_three_finance_pages(): void
    {
        $routes = [
            'super-admin.finance.students',
            'super-admin.finance.expenses',
            'super-admin.finance.recovery',
        ];

        foreach ([$this->superAdmin, $this->centerAdmin] as $user) {
            foreach ($routes as $routeName) {
                $response = $this->actingAs($user)->get(route($routeName));
                $response->assertOk();

                $sidebar = $this->getSidebar($response->getContent());

                // Le lien ciblé a l'attribut aria-current="page"
                $url = route($routeName);
                $this->assertMatchesRegularExpression(
                    '/<a href="' . preg_quote($url, '/') . '"[^>]*aria-current="page"[^>]*>/',
                    $sidebar,
                    "Le lien {$routeName} doit être marqué aria-current='page' pour {$user->role}."
                );

                // Style turquoise actif spécifique au module finance
                $this->assertStringContainsString('border-[#0DAD98]', $sidebar);
            }
        }
    }

    /**
     * 4. Les autres rôles (Secrétaire, Professeur, Étudiant) n'ont pas accès au module finance.
     */
    public function test_other_roles_cannot_access_finance_and_do_not_see_finance_in_sidebar(): void
    {
        $financeRoutes = [
            'super-admin.finance.dashboard',
            'super-admin.finance.students',
            'super-admin.finance.expenses',
            'super-admin.finance.recovery',
        ];

        foreach ([$this->secretary, $this->teacher, $this->student] as $user) {
            foreach ($financeRoutes as $routeName) {
                $this->actingAs($user)->get(route($routeName))->assertStatus(403);
            }
        }
    }

    /**
     * 5. Traductions arabe des trois liens dans la section Gestion financière.
     */
    public function test_arabic_translation_of_three_links_in_gestion_financiere(): void
    {
        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.finance.students'));
        $response->assertOk();

        $sidebar = $this->getSidebar($response->getContent());

        $this->assertStringContainsString(__('الإدارة المالية'), $sidebar);
        $this->assertStringContainsString(__('المدفوعات'), $sidebar);
        $this->assertStringContainsString(__('المصروفات'), $sidebar);
        $this->assertStringContainsString(__('التحصيل'), $sidebar);

        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'fr'));
    }
}

