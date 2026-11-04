<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;

    protected Center $centerMunich;

    protected User $superAdmin;

    protected User $adminBerlin;

    protected User $teacherUser;

    protected User $studentUser;

    protected Teacher $teacher;

    protected Student $student;

    protected Formation $formationInformatique;

    protected Formation $formationSante;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Centres
        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->centerMunich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        // 2. Super Admin
        $this->superAdmin = User::factory()->superAdmin()->create([
            'email' => 'superadmin.finance@test.com',
            'status' => 'active',
        ]);

        // 3. Admin Centre
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create([
            'email' => 'admin.finance@test.com',
            'status' => 'active',
        ]);

        // 4. Professeur
        $this->teacherUser = User::factory()->teacher($this->centerBerlin->id)->create([
            'email' => 'prof.finance@test.com',
            'status' => 'active',
        ]);
        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'center_id' => $this->centerBerlin->id,
            'teacher_number' => 'BER-T-999901',
            'first_name' => 'Professeur',
            'last_name' => 'Finance',
            'domain' => 'informatique',
            'monthly_salary' => 6000.00,
            'status' => 'active',
        ]);

        // 5. Étudiant
        $this->studentUser = User::factory()->student($this->centerBerlin->id)->create([
            'email' => 'student.finance@test.com',
            'status' => 'active',
        ]);
        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'center_id' => $this->centerBerlin->id,
            'student_number' => 'BER-S-999901',
            'first_name' => 'Ahmed',
            'last_name' => 'Étudiant',
            'status' => 'active',
        ]);

        // 6. Formations
        $this->formationInformatique = Formation::create([
            'center_id' => $this->centerBerlin->id,
            'name' => 'Développement Web',
            'code' => 'DEV-WEB',
            'domain' => 'informatique',
            'price' => 14000.00,
            'annual_price' => 14000.00,
            'currency' => 'MAD',
            'status' => 'active',
        ]);

        $this->formationSante = Formation::create([
            'center_id' => $this->centerBerlin->id,
            'name' => 'Formation Santé',
            'code' => 'SANTE-01',
            'domain' => 'sante',
            'price' => 15000.00,
            'annual_price' => 15000.00,
            'currency' => 'MAD',
            'status' => 'active',
        ]);
    }

    /**
     * 1. Super Admin peut accéder à /super-admin/domaines.
     */
    public function test_super_admin_can_access_domaines_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domaines'))
            ->assertOk()
            ->assertSee('Formations générales');
    }

    /**
     * 2. La carte Gestion financière est visible sur /admin/accueil et absente de /super-admin/domaines.
     */
    public function test_gestion_financiere_card_is_visible(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.home'))
            ->assertOk()
            ->assertSee('id="card-home-finance"', false)
            ->assertSee('Gestion financière')
            ->assertSee('Vue globale');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domaines'))
            ->assertOk()
            ->assertDontSee('id="card-gestion-financiere"', false);
    }

    /**
     * 3. Super Admin peut ouvrir le dashboard financier.
     */
    public function test_super_admin_can_open_finance_dashboard(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('Gestion financière')
            ->assertSee('Vue globale')
            ->assertSee('Total encaissé')
            ->assertSee('Restant à encaisser')
            ->assertSee('Total attendu')
            ->assertSee('Salaires payés')
            ->assertSee('Salaires restant à payer');
    }

    /**
     * 4. Teacher ne peut pas accéder au dashboard financier (403 ou redirection).
     */
    public function test_teacher_cannot_access_finance_dashboard(): void
    {
        $this->actingAs($this->teacherUser)
            ->get(route('super-admin.finance.dashboard'))
            ->assertForbidden();
    }

    /**
     * 5. Student ne peut pas accéder au dashboard financier (403 ou redirection).
     */
    public function test_student_cannot_access_finance_dashboard(): void
    {
        $this->actingAs($this->studentUser)
            ->get(route('super-admin.finance.dashboard'))
            ->assertForbidden();
    }

    /**
     * 6. L'Admin Centre peut désormais accéder au dashboard financier, mais
     *    UNIQUEMENT sur le périmètre de SON centre (exigence produit).
     */
    public function test_center_admin_can_access_finance_dashboard_scoped_to_their_center(): void
    {
        $response = $this->actingAs($this->adminBerlin)
            ->get(route('super-admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('Gestion financière')
            // Vue centrée et non globale pour un Admin Centre.
            ->assertSee('Vue centre');
    }

    /**
     * 6b. Le filtre ?center_id est IGNORE pour un Admin Centre : le périmètre
     *     reste son propre centre (sécurité multi-centres).
     */
    public function test_center_admin_cannot_escape_center_scope_via_query(): void
    {
        // Formation appartenant à un AUTRE centre (Munich).
        Formation::create([
            'center_id' => $this->centerMunich->id,
            'name' => 'Config Munich Segrete',
            'code' => 'MUN-SEG',
            'domain' => 'informatique',
            'price' => 9000.00,
            'annual_price' => 9000.00,
            'currency' => 'MAD',
            'status' => 'active',
        ]);

        // Même en tentant de forcer ?center_id = Munich, l'Admin de Berlin ne voit
        // que SON centre : la requête est ignorée côté serveur.
        $this->actingAs($this->adminBerlin)
            ->get(route('super-admin.finance.configuration', ['center_id' => $this->centerMunich->id]))
            ->assertOk()
            ->assertSee('Développement Web')
            ->assertDontSee('Config Munich Segrete', false);
    }

    /**
     * 6c. La carte « Gestion financière » est visible sur /admin/accueil pour un
     *     Admin Centre, en 4ᵉ position (bas à droite) avec son libellé dédié.
     */
    public function test_finance_card_visible_and_ordered_for_center_admin(): void
    {
        $html = $this->actingAs($this->adminBerlin)
            ->get(route('admin.home'))
            ->assertOk()
            ->assertSee('id="card-home-finance"', false)
            ->assertSee('Gestion financière')
            ->assertSee('Vue centre')
            ->assertSee('Accéder à la gestion financière')
            ->getContent();

        // La mise en page 2×2 est obtenue par les classes CSS « order » :
        // Communication = order-3, Finance = order-4 → Finance s'affiche en bas à
        // droite (dernière case visible), sans modifier l'ordre du DOM Super Admin.
        $this->assertStringContainsString('order-4', $html);
        $this->assertStringContainsString('order-3', $html);

        // La carte financière porte bien order-4 et la carte communication order-3.
        $financeTag = $this->extractCardTag($html, 'card-home-finance');
        $commTag = $this->extractCardTag($html, 'card-home-communication');
        $this->assertStringContainsString('order-4', $financeTag);
        $this->assertStringContainsString('order-3', $commTag);

        // Les quatre cartes sont présentes.
        foreach (['card-home-formations', 'card-home-stagiaires', 'card-home-communication', 'card-home-finance'] as $id) {
            $this->assertStringContainsString($id, $html);
        }
    }

    /**
     * Extrait la balise d'ouverture <a …id="…"> d'une carte de l'Accueil.
     */
    private function extractCardTag(string $html, string $id): string
    {
        $pos = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($pos, "Carte #{$id} absente.");
        $start = strrpos(substr($html, 0, $pos), '<a');
        $end = strpos($html, '>', $pos);

        return substr($html, $start, $end - $start + 1);
    }

    /**
     * 6d. Le Super Admin conserve son Accueil inchangé : carte financière en
     *     position « globale », sans classes d'ordre, libellé d'origine.
     */
    public function test_super_admin_home_finance_card_unchanged(): void
    {
        $html = $this->actingAs($this->superAdmin)
            ->get(route('admin.home'))
            ->assertOk()
            ->assertSee('id="card-home-finance"', false)
            ->assertSee('Vue globale')
            ->assertSee('Accéder aux finances')
            ->getContent();

        $this->assertStringNotContainsString('order-4', $html);
        $this->assertStringNotContainsString('order-3', $html);
    }

    /**
     * 6e. Un Admin Centre ne peut PAS voir la rubrique financière dans sa sidebar
     *     (exigence : la carte de l'Accueil est le seul point d'entrée).
     */
    public function test_finance_not_in_center_admin_sidebar(): void
    {
        // Une page du centre utilisant la sidebar (Formations) ne doit contenir
        // aucun lien vers les routes financières.
        $html = $this->actingAs($this->adminBerlin)
            ->get(route('formations.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('super-admin/finance', $html);
        $this->assertStringNotContainsString('admin/finance', $html);
    }

    // ─── Interface financière UNIFIÉE Super Admin / Admin Centre ─────────────
    // Mission : l'Admin Centre doit utiliser exactement le même layout, la même
    // sidebar dédiée et la même organisation visuelle que le Super Admin. La
    // barre de navigation horizontale (partial _nav-center-admin) est supprimée.

    /**
     * Extrait le HTML de la sidebar (<aside>…</aside>) d'une réponse.
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
     * 6g. L'Admin Centre obtient la SIDEBAR DÉDIÉE « Gestion financière »
     *     (Contexte actif + Accueil + les 6 entrées) dès qu'il entre dans le
     *     module, avec l'état actif turquoise identique au Super Admin.
     */
    public function test_center_admin_sees_dedicated_finance_sidebar(): void
    {
        $response = $this->actingAs($this->adminBerlin)
            ->get(route('super-admin.finance.dashboard'))
            ->assertOk();

        $sidebar = $this->sidebarHtml($response);

        // Bloc « CONTEXTE ACTIF — Gestion financière » + bouton « Accueil ».
        $this->assertStringContainsString(__('Contexte actif'), $sidebar);
        $this->assertStringContainsString('id="sidebar-changer-finance"', $sidebar);
        $this->assertStringContainsString(route('admin.home'), $sidebar);

        // Les six entrées de la navigation verticale.
        foreach ([
            'super-admin.finance.dashboard',
            'super-admin.finance.statistics',
            'super-admin.finance.students',
            'super-admin.finance.expenses',
            'super-admin.finance.recovery',
            'super-admin.finance.configuration',
        ] as $name) {
            $this->assertStringContainsString(route($name), $sidebar, "Entrée manquante dans la sidebar : {$name}");
        }

        // Lien actif : style turquoise du module (bordure + fond #0DAD98).
        $this->assertStringContainsString('border-[#0DAD98]', $sidebar);
        $this->assertStringContainsString('aria-current="page"', $sidebar);

        // La sidebar générale (Pédagogie/Communication) ne doit PAS être affichée
        // simultanément dans le contexte financier.
        $this->assertStringNotContainsString(route('formations.index'), $sidebar);
        $this->assertStringNotContainsString(route('announcements.index'), $sidebar);
    }

    /**
     * 6h. La barre de navigation HORIZONTALE financière a été supprimée chez
     *     l'Admin Centre : les liens ne vivent plus que dans la sidebar.
     */
    public function test_no_horizontal_finance_nav_for_center_admin(): void
    {
        $html = $this->actingAs($this->adminBerlin)
            ->get(route('super-admin.finance.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('finance-nav-center-admin', $html);
        $this->assertStringNotContainsString(__('Navigation Gestion financière'), $html);
    }

    /**
     * 6i. L'Admin Centre peut naviguer entre TOUTES les pages financières, et
     *     le layout dédié (sidebar financière) est conservé sur chaque page.
     */
    public function test_center_admin_can_navigate_all_finance_pages_with_dedicated_layout(): void
    {
        $pages = [
            'super-admin.finance.dashboard',
            'super-admin.finance.statistics',
            'super-admin.finance.students',
            'super-admin.finance.expenses',
            'super-admin.finance.recovery',
            'super-admin.finance.configuration',
        ];

        foreach ($pages as $name) {
            $response = $this->actingAs($this->adminBerlin)
                ->get(route($name))
                ->assertOk();

            // Layout dédié conservé partout : bannière de contexte financier.
            $this->assertStringContainsString(
                'id="sidebar-changer-finance"',
                $this->sidebarHtml($response),
                "Le layout financier dédié doit être présent sur {$name}."
            );
        }
    }

    /**
     * 6j. Hors module financier, la sidebar de l'Admin Centre reste générale :
     *     aucune rubrique financière (l'Accueil utilise le layout portal sans
     *     sidebar ; on vérifie donc sur une page avec sidebar).
     */
    public function test_finance_sidebar_only_inside_finance_module_for_center_admin(): void
    {
        $sidebar = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route('formations.index'))
        );

        $this->assertStringNotContainsString('super-admin/finance', $sidebar);
        $this->assertStringNotContainsString(__('Contexte actif'), $sidebar);
    }

    /**
     * 6k. Le Super Admin conserve une interface financière strictement identique :
     *     mêmes blocs et entrées de sidebar que l'Admin Centre (unification).
     */
    public function test_super_admin_finance_sidebar_matches_center_admin_structure(): void
    {
        $saSidebar = $this->sidebarHtml(
            $this->actingAs($this->superAdmin)->get(route('super-admin.finance.dashboard'))
        );
        $caSidebar = $this->sidebarHtml(
            $this->actingAs($this->adminBerlin)->get(route('super-admin.finance.dashboard'))
        );

        // Même bannière « Contexte actif » et mêmes six entrées pour les deux rôles.
        foreach (['Contexte actif', 'Tableau de bord', 'Statistiques', 'Paiements', 'Dépenses', 'Recouvrement', 'Configuration des prix'] as $needle) {
            $this->assertStringContainsString(__($needle), $saSidebar);
            $this->assertStringContainsString(__($needle), $caSidebar);
        }

        // Aucune barre horizontale non plus pour le Super Admin (elle n'a jamais
        // été rendue, et le partial supprimé ne doit plus exister).
        $this->assertStringNotContainsString('finance-nav-center-admin', $saSidebar);
    }

    /**
     * 6l. Les quatre centres concernés (Tanger, Essaouira, Agadir, Tétouane) :
     *     chaque Admin Centre obtient la sidebar financière dédiée et ne voit
     *     que SON centre dans le filtre.
     */
    public static function centerProvider(): array
    {
        return [
            'Tanger' => ['Tanger', 'TGR'],
            'Essaouira' => ['Essaouira', 'ESS'],
            'Agadir' => ['Agadir', 'AGD'],
            'Tétouane' => ['Tétouane', 'TET'],
        ];
    }

    #[DataProvider('centerProvider')]
    public function test_each_center_admin_gets_finance_sidebar_scoped_to_their_center(string $centerName, string $code): void
    {
        $center = Center::factory()->create(['name' => $centerName, 'code' => $code]);
        $admin = User::factory()->centerAdmin($center->id)->create([
            'email' => 'admin.'.strtolower($code).'.finance@test.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('super-admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('Vue centre');

        $sidebar = $this->sidebarHtml($response);

        // Sidebar dédiée : bannière de contexte + bouton Accueil.
        $this->assertStringContainsString(__('Contexte actif'), $sidebar);
        $this->assertStringContainsString('id="sidebar-changer-finance"', $sidebar);

        // Le filtre Centre affiche SON centre et uniquement lui (bloc lecture seule).
        $this->assertStringContainsString($center->translated('name'), $sidebar.$response->getContent());
        $this->assertStringNotContainsString('finance-nav-center-admin', $response->getContent());
    }

    /**
     * 6f. La page « Configuration des prix » est filtrée sur le centre de l'Admin.
     */
    public function test_center_admin_configuration_scoped_to_their_center(): void
    {
        $foreignFormation = Formation::create([
            'center_id' => $this->centerMunich->id,
            'name' => 'Formation Munich Exclusive',
            'code' => 'MUN-EXCL',
            'domain' => 'informatique',
            'price' => 12000.00,
            'annual_price' => 12000.00,
            'currency' => 'MAD',
            'status' => 'active',
        ]);

        $this->actingAs($this->adminBerlin)
            ->get(route('super-admin.finance.configuration'))
            ->assertOk()
            ->assertSee('Développement Web')          // formation de Berlin
            ->assertDontSee('Formation Munich Exclusive', false); // pas Munich
    }

    /**
     * 6g. Garde d'écriture : un Admin Centre ne peut pas manipuler une dépense
     *     d'un AUTRE centre (annulation → 403).
     */
    public function test_center_admin_cannot_cancel_foreign_expense(): void
    {
        $category = ExpenseCategory::create(['name' => 'Fournitures', 'code' => 'FRN-TEST']);
        $foreignExpense = Expense::create([
            'center_id' => $this->centerMunich->id,
            'category_id' => $category->id,
            'title' => 'Dépense Munich',
            'amount' => 500.00,
            'currency' => 'MAD',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'school_year' => '2026/2027',
            'status' => 'paid',
            'recorded_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->adminBerlin)
            ->patch(route('super-admin.finance.expenses.cancel', $foreignExpense))
            ->assertForbidden();

        // La dépense d'un autre centre peut en revanche être annulée par son propre admin.
        $this->actingAs($this->adminBerlin)
            ->post(route('super-admin.finance.expenses.store'), [
                'center_id' => $this->centerBerlin->id,
                'category_id' => $category->id,
                'title' => 'Dépense Berlin autorisée',
                'amount' => 120.00,
                'expense_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertSessionHas('success');
    }

    /**
     * 7 & 8. Les montants étudiants et le reste sont calculés correctement.
     */
    public function test_student_financial_amounts_and_remaining_are_calculated_correctly(): void
    {
        // Inscription
        $enrollment = Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'registration_number' => 'REG-001',
            'application_date' => now()->toDateString(),
            'school_year' => '2026/2027',
            'status' => 'accepted',
        ]);

        // Paiement partiel de 8 000 DH sur 14 000 DH
        Payment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'enrollment_id' => $enrollment->id,
            'school_year' => '2026/2027',
            'domain' => 'informatique',
            'amount' => 8000.00,
            'currency' => 'MAD',
            'payment_date' => now()->toDateString(),
            'method' => 'bank_transfer',
            'reference' => 'PAY-TEST-001',
            'status' => 'paid',
        ]);

        $service = new FinanceService;
        $rows = $service->getStudentFinancialRows(null, '2026/2027', $this->centerBerlin->id);
        $studentRow = $rows->firstWhere('student.id', $this->student->id);

        $this->assertNotNull($studentRow);
        $this->assertEquals(14000.00, $studentRow->annual_price);
        $this->assertEquals(8000.00, $studentRow->paid_amount);
        $this->assertEquals(6000.00, $studentRow->remaining_amount);
        $this->assertEquals('partial', $studentRow->status);
    }

    /**
     * 9. Le statut Payé fonctionne pour les étudiants (solde 0).
     */
    public function test_student_paid_status_works(): void
    {
        $enrollment = Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'registration_number' => 'REG-PAID-001',
            'application_date' => now()->toDateString(),
            'school_year' => '2026/2027',
            'status' => 'accepted',
        ]);

        Payment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'enrollment_id' => $enrollment->id,
            'school_year' => '2026/2027',
            'amount' => 14000.00,
            'currency' => 'MAD',
            'payment_date' => now()->toDateString(),
            'method' => 'bank_transfer',
            'reference' => 'PAY-PAID-001',
            'status' => 'paid',
        ]);

        $service = new FinanceService;
        $rows = $service->getStudentFinancialRows(null, '2026/2027', $this->centerBerlin->id);
        $studentRow = $rows->firstWhere('student.id', $this->student->id);

        $this->assertEquals('paid', $studentRow->status);
        $this->assertEquals(0.00, $studentRow->remaining_amount);
    }

    /**
     * 10. Le statut Partiel fonctionne pour les étudiants.
     */
    public function test_student_partial_status_works(): void
    {
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'registration_number' => 'REG-PART-001',
            'application_date' => now()->toDateString(),
            'school_year' => '2026/2027',
            'status' => 'accepted',
        ]);

        Payment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'school_year' => '2026/2027',
            'amount' => 5000.00,
            'currency' => 'MAD',
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => 'PAY-PART-001',
            'status' => 'paid',
        ]);

        $service = new FinanceService;
        $rows = $service->getStudentFinancialRows(null, '2026/2027', $this->centerBerlin->id);
        $studentRow = $rows->firstWhere('student.id', $this->student->id);

        $this->assertEquals('partial', $studentRow->status);
        $this->assertEquals(9000.00, $studentRow->remaining_amount);
    }

    /**
     * 11. Le statut Non payé fonctionne pour les étudiants.
     */
    public function test_student_unpaid_status_works(): void
    {
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'registration_number' => 'REG-UNPAID-001',
            'application_date' => now()->toDateString(),
            'school_year' => '2026/2027',
            'status' => 'accepted',
        ]);

        $service = new FinanceService;
        $rows = $service->getStudentFinancialRows(null, '2026/2027', $this->centerBerlin->id);
        $studentRow = $rows->firstWhere('student.id', $this->student->id);

        $this->assertEquals('unpaid', $studentRow->status);
        $this->assertEquals(0.00, $studentRow->paid_amount);
        $this->assertEquals(14000.00, $studentRow->remaining_amount);
    }

    /**
     * 12. Plusieurs paiements pour un même étudiant sont correctement additionnés.
     */
    public function test_multiple_payments_for_same_student_are_summed_correctly(): void
    {
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'registration_number' => 'REG-MULTI-001',
            'application_date' => now()->toDateString(),
            'school_year' => '2026/2027',
            'status' => 'accepted',
        ]);

        // Tranche 1 : 3 000 DH
        Payment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'school_year' => '2026/2027',
            'amount' => 3000.00,
            'currency' => 'MAD',
            'payment_date' => '2026-10-01',
            'method' => 'bank_transfer',
            'reference' => 'PAY-M1',
            'status' => 'paid',
        ]);

        // Tranche 2 : 2 000 DH
        Payment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'school_year' => '2026/2027',
            'amount' => 2000.00,
            'currency' => 'MAD',
            'payment_date' => '2026-12-01',
            'method' => 'bank_transfer',
            'reference' => 'PAY-M2',
            'status' => 'paid',
        ]);

        // Tranche 3 : 3 000 DH
        Payment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'school_year' => '2026/2027',
            'amount' => 3000.00,
            'currency' => 'MAD',
            'payment_date' => '2027-02-01',
            'method' => 'cash',
            'reference' => 'PAY-M3',
            'status' => 'paid',
        ]);

        $service = new FinanceService;
        $rows = $service->getStudentFinancialRows(null, '2026/2027', $this->centerBerlin->id);
        $studentRow = $rows->firstWhere('student.id', $this->student->id);

        $this->assertEquals(8000.00, $studentRow->paid_amount);
        $this->assertEquals(6000.00, $studentRow->remaining_amount);
        $this->assertEquals('partial', $studentRow->status);
    }

    /**
     * 13. Les salaires professeurs sont calculés correctement.
     */
    public function test_teacher_salaries_are_calculated_correctly(): void
    {
        $service = new FinanceService;
        $rows = $service->getTeacherSalaryRows(null, 10, 2026, '2026/2027', $this->centerBerlin->id);
        $teacherRow = $rows->firstWhere('teacher.id', $this->teacher->id);

        $this->assertNotNull($teacherRow);
        $this->assertEquals(6000.00, $teacherRow->monthly_salary);
        $this->assertEquals(0.00, $teacherRow->paid_amount);
        $this->assertEquals(6000.00, $teacherRow->remaining_amount);
        $this->assertEquals('unpaid', $teacherRow->status);
    }

    /**
     * 14. Payé / Non payé fonctionne pour les professeurs.
     */
    public function test_teacher_salary_paid_and_unpaid_status_works(): void
    {
        // Enregistrer paiement complet de salaire pour Octobre 2026
        TeacherSalary::create([
            'center_id' => $this->centerBerlin->id,
            'teacher_id' => $this->teacher->id,
            'domain' => 'informatique',
            'month' => 10,
            'year' => 2026,
            'school_year' => '2026/2027',
            'expected_amount' => 6000.00,
            'paid_amount' => 6000.00,
            'currency' => 'MAD',
            'status' => 'paid',
            'payment_date' => '2026-10-28',
        ]);

        $service = new FinanceService;
        $rows = $service->getTeacherSalaryRows(null, 10, 2026, '2026/2027', $this->centerBerlin->id);
        $teacherRow = $rows->firstWhere('teacher.id', $this->teacher->id);

        $this->assertEquals('paid', $teacherRow->status);
        $this->assertEquals(6000.00, $teacherRow->paid_amount);
        $this->assertEquals(0.00, $teacherRow->remaining_amount);

        // Mois de Novembre : Non payé
        $novRows = $service->getTeacherSalaryRows(null, 11, 2026, '2026/2027', $this->centerBerlin->id);
        $novTeacherRow = $novRows->firstWhere('teacher.id', $this->teacher->id);
        $this->assertEquals('unpaid', $novTeacherRow->status);
        $this->assertEquals(6000.00, $novTeacherRow->remaining_amount);
    }

    /**
     * 15. Le filtre par domaine fonctionne.
     */
    public function test_filter_by_domain_works(): void
    {
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'registration_number' => 'REG-INF-01',
            'application_date' => now()->toDateString(),
            'school_year' => '2026/2027',
            'status' => 'accepted',
        ]);

        $service = new FinanceService;

        // Filtre Informatique
        $infoRows = $service->getStudentFinancialRows('informatique', '2026/2027');
        $this->assertTrue($infoRows->contains('formation.id', $this->formationInformatique->id));

        // Filtre Santé -> ne doit pas contenir l'inscription Informatique
        $santeRows = $service->getStudentFinancialRows('sante', '2026/2027');
        $this->assertFalse($santeRows->contains('formation.id', $this->formationInformatique->id));
    }

    /**
     * 16. Le filtre par année scolaire fonctionne.
     */
    public function test_filter_by_school_year_works(): void
    {
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'registration_number' => 'REG-Y26',
            'application_date' => now()->toDateString(),
            'school_year' => '2026/2027',
            'status' => 'accepted',
        ]);

        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $this->student->id,
            'formation_id' => $this->formationInformatique->id,
            'registration_number' => 'REG-Y27',
            'application_date' => now()->toDateString(),
            'school_year' => '2027/2028',
            'status' => 'accepted',
        ]);

        $service = new FinanceService;
        $rows26 = $service->getStudentFinancialRows(null, '2026/2027');
        $rows27 = $service->getStudentFinancialRows(null, '2027/2028');

        $this->assertTrue($rows26->contains('enrollment.registration_number', 'REG-Y26'));
        $this->assertFalse($rows26->contains('enrollment.registration_number', 'REG-Y27'));

        $this->assertTrue($rows27->contains('enrollment.registration_number', 'REG-Y27'));
        $this->assertFalse($rows27->contains('enrollment.registration_number', 'REG-Y26'));
    }

    /**
     * 17. Le filtre par mois pour les salaires fonctionne.
     */
    public function test_filter_by_month_for_teacher_salaries_works(): void
    {
        TeacherSalary::create([
            'center_id' => $this->centerBerlin->id,
            'teacher_id' => $this->teacher->id,
            'month' => 10,
            'year' => 2026,
            'school_year' => '2026/2027',
            'expected_amount' => 6000.00,
            'paid_amount' => 6000.00,
            'status' => 'paid',
        ]);

        $service = new FinanceService;
        $octRows = $service->getTeacherSalaryRows(null, 10, 2026);
        $novRows = $service->getTeacherSalaryRows(null, 11, 2026);

        $this->assertEquals('paid', $octRows->firstWhere('teacher.id', $this->teacher->id)->status);
        $this->assertEquals('unpaid', $novRows->firstWhere('teacher.id', $this->teacher->id)->status);
    }

    /**
     * 18. La devise par défaut est MAD.
     */
    public function test_default_currency_is_mad(): void
    {
        $this->assertEquals('MAD', default_currency());
        $this->assertEquals('DH', currency_symbol());
        $formatted = format_price(15000);
        $this->assertStringContainsString('DH', $formatted);
    }

    /**
     * 19. L’isolation par centre n’est pas cassée.
     */
    public function test_center_isolation_is_maintained(): void
    {
        // Enseignant à Munich
        $munichTeacherUser = User::factory()->teacher($this->centerMunich->id)->create(['status' => 'active']);
        $munichTeacher = Teacher::create([
            'user_id' => $munichTeacherUser->id,
            'center_id' => $this->centerMunich->id,
            'teacher_number' => 'MUN-T-999902',
            'first_name' => 'Greta',
            'last_name' => 'Munich',
            'domain' => 'sante',
            'monthly_salary' => 7000.00,
            'status' => 'active',
        ]);

        $service = new FinanceService;

        // Vue filtrée sur Berlin
        $berlinRows = $service->getTeacherSalaryRows(null, 10, 2026, '2026/2027', $this->centerBerlin->id);
        $this->assertTrue($berlinRows->contains('teacher.id', $this->teacher->id));
        $this->assertFalse($berlinRows->contains('teacher.id', $munichTeacher->id));

        // Vue filtrée sur Munich
        $munichRows = $service->getTeacherSalaryRows(null, 10, 2026, '2026/2027', $this->centerMunich->id);
        $this->assertTrue($munichRows->contains('teacher.id', $munichTeacher->id));
        $this->assertFalse($munichRows->contains('teacher.id', $this->teacher->id));
    }

    /**
     * 20. Student reste fonctionnel.
     */
    public function test_student_dashboard_remains_functional(): void
    {
        $this->actingAs($this->studentUser)
            ->get(route('dashboard'))
            ->assertOk();
    }

    /**
     * 21. Stagiaires reste fonctionnel (dashboard redirigé vers la liste simplifiée).
     */
    public function test_stagiaires_module_remains_functional(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.dashboard'))
            ->assertRedirect(route('stagiaires.index'));

        $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->get(route('stagiaires.dashboard'))
            ->assertOk()
            ->assertSee('Tous les stagiaires');
    }

    /**
     * 22. Santé reste fonctionnel.
     */
    public function test_sante_domain_dashboard_remains_functional(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.sante.dashboard'))
            ->assertOk()
            ->assertSee('Santé');
    }

    /**
     * 23. Les autres domaines restent fonctionnels.
     */
    public function test_other_domains_remain_functional(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'))
            ->assertOk()
            ->assertSee('Informatique');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'hotellerie'))
            ->assertOk()
            ->assertSee('Hôtellerie');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'maconnerie'))
            ->assertOk()
            ->assertSee('Maçonnerie');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'design-interieur'))
            ->assertOk()
            ->assertSee('Design intérieur');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'design-graphique'))
            ->assertOk()
            ->assertSee('Design graphique');
    }

    /**
     * 24. Enregistrement d'un paiement via la route financière.
     */
    public function test_super_admin_can_store_student_payment(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.finance.students.payment.store'), [
                'student_id' => $this->student->id,
                'formation_id' => $this->formationInformatique->id,
                'amount' => 3500.00,
                'currency' => 'MAD',
                'payment_date' => now()->toDateString(),
                'school_year' => '2026/2027',
                'method' => 'bank_transfer',
                'reference' => 'VIR-TEST-888',
                'notes' => 'Acompte 1',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'student_id' => $this->student->id,
            'amount' => 3500.00,
            'reference' => 'VIR-TEST-888',
        ]);
    }

    /**
     * 25. Enregistrement du règlement d'un salaire professeur.
     */
    public function test_super_admin_can_store_teacher_salary(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.finance.teachers.salary.store'), [
                'teacher_id' => $this->teacher->id,
                'month' => 10,
                'year' => 2026,
                'school_year' => '2026/2027',
                'expected_amount' => 6000.00,
                'paid_amount' => 6000.00,
                'currency' => 'MAD',
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Virement bancaire',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_salaries', [
            'teacher_id' => $this->teacher->id,
            'month' => 10,
            'year' => 2026,
            'paid_amount' => 6000.00,
            'status' => 'paid',
        ]);
    }

    /**
     * 26. Configuration personnalisée des prix de formation et salaires professeurs.
     */
    public function test_super_admin_can_update_price_configuration(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->put(route('super-admin.finance.configuration.update'), [
                'formations' => [
                    [
                        'id' => $this->formationInformatique->id,
                        'annual_price' => 18000.00,
                        'domain' => 'informatique',
                    ],
                ],
                'teachers' => [
                    [
                        'id' => $this->teacher->id,
                        'monthly_salary' => 9500.00,
                        'domain' => 'informatique',
                    ],
                ],
            ]);

        $response->assertRedirect(route('super-admin.finance.configuration'));
        $this->assertDatabaseHas('formations', [
            'id' => $this->formationInformatique->id,
            'annual_price' => 18000.00,
        ]);
        $this->assertDatabaseHas('teachers', [
            'id' => $this->teacher->id,
            'monthly_salary' => 9500.00,
        ]);
    }

    /**
     * 27. L'ancienne page des salaires redirige désormais vers Dépenses
     *     (la gestion des salaires est intégrée aux Dépenses, plus de page autonome).
     */
    public function test_super_admin_can_view_teachers_page_with_filters(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/super-admin/finance/professeurs?domain=all&month=10&year=2026')
            ->assertRedirectContains('/super-admin/finance/depenses');
    }

    /**
     * 28. Super Admin peut afficher la page des paiements étudiants avec différents modes.
     */
    public function test_super_admin_can_view_students_page_with_filters(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/super-admin/finance/etudiants?domain=all&view=students')
            ->assertOk()
            ->assertSee('Paiements étudiants');

        $this->actingAs($this->superAdmin)
            ->get('/super-admin/finance/etudiants?view=monthly')
            ->assertOk()
            ->assertSee('Paiements étudiants');

        $this->actingAs($this->superAdmin)
            ->get('/super-admin/finance/etudiants?view=unpaid')
            ->assertOk()
            ->assertSee('Paiements étudiants');
    }

    /**
     * 29. Super Admin peut afficher l'historique et les rapports financiers.
     */
    public function test_super_admin_can_view_history_and_reports(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.history'))
            ->assertOk()
            ->assertSee('Journal des opérations');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.reports'))
            ->assertOk()
            ->assertSee('Rapports financiers');
    }

    /**
     * 30. Super Admin peut afficher la page Statistiques avec graphiques Cad Syndic.
     */
    public function test_super_admin_can_view_statistics_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.statistics'))
            ->assertOk()
            ->assertSee('Statistiques financières')
            ->assertSee('Total attendu')
            ->assertSee('Reste impayé')
            ->assertSee('Encaissements par mois');
    }

    /**
     * 31. Super Admin peut afficher la page Dépenses et enregistrer une charge.
     */
    public function test_super_admin_can_view_expenses_and_store_expense(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.expenses'))
            ->assertOk()
            ->assertSee('Dépenses')
            ->assertSee('Budget alloué')
            ->assertSee('Charges engagées');

        // Test de l'enregistrement avec articles multiples et calcul automatique
        $postResponse = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.finance.expenses.store'), [
                'center_id' => $this->centerBerlin->id,
                'title' => 'Achat de fournitures scolaires',
                'supplier' => 'Librairie Centrale',
                'expense_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
                'reference' => 'DEP-TEST-001',
                'school_year' => '2026/2027',
                'items' => [
                    ['name' => 'Feutres', 'quantity' => 5, 'unit_price' => 20.00],
                    ['name' => 'Papier A4', 'quantity' => 2, 'unit_price' => 50.00],
                    ['name' => 'Stylos', 'quantity' => 10, 'unit_price' => 3.00],
                ],
            ]);

        $postResponse->assertRedirect();
        // Le montant total calculé doit être 100 + 100 + 30 = 230 DH
        $this->assertDatabaseHas('expenses', [
            'title' => 'Achat de fournitures scolaires',
            'amount' => 230.00,
            'supplier' => 'Librairie Centrale',
            'center_id' => $this->centerBerlin->id,
        ]);
        $this->assertDatabaseHas('expense_items', [
            'name' => 'Feutres',
            'quantity' => 5,
            'unit_price' => 20.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'type' => 'withdrawal',
            'amount' => 230.00,
            'center_id' => $this->centerBerlin->id,
        ]);

        // Test d'annulation de dépense
        $createdExpense = Expense::where('reference', 'DEP-TEST-001')->first();
        $cancelResponse = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.finance.expenses.cancel', $createdExpense->id));
        $cancelResponse->assertRedirect();
        $this->assertEquals('cancelled', $createdExpense->fresh()->status);
    }

    /**
     * 32. Super Admin peut afficher la page Recouvrement avec relances WhatsApp.
     */
    public function test_super_admin_can_view_recovery_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.recovery'))
            ->assertOk()
            ->assertSee('Recouvrement & Impayés')
            ->assertSee('Créance totale')
            ->assertSee('Dossiers débiteurs')
            ->assertSee('WhatsApp');
    }

    /**
     * 33. Super Admin peut afficher le Compte bancaire et enregistrer une écriture.
     */
    public function test_super_admin_can_view_bank_and_store_transaction(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.bank'))
            ->assertOk()
            ->assertSee('Compte bancaire & Trésorerie')
            ->assertSee('Total Déposé')
            ->assertSee('Total Retiré')
            ->assertSee('Solde Banque');

        $postResponse = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.finance.bank.store'), [
                'center_id' => $this->centerBerlin->id,
                'transaction_date' => now()->toDateString(),
                'label' => 'Dépôt espèces scolarités',
                'origin' => 'Caisse',
                'method' => 'cash',
                'type' => 'deposit',
                'amount' => 12000.00,
                'reference' => 'DEPOT-ESPECES-01',
            ]);

        $postResponse->assertRedirect();
        $this->assertDatabaseHas('bank_transactions', [
            'label' => 'Dépôt espèces scolarités',
            'type' => 'deposit',
            'amount' => 12000.00,
            'center_id' => $this->centerBerlin->id,
        ]);
    }

    /**
     * 34. Super Admin peut afficher la Comptabilité avec onglets Journal, CPC, Bilan et Grand Livre.
     */
    public function test_super_admin_can_view_accounting_tabs(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.accounting', ['tab' => 'journal']))
            ->assertOk()
            ->assertSee('Comptabilité')
            ->assertSee('Livre-Journal général');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.accounting', ['tab' => 'cpc']))
            ->assertOk()
            ->assertSee('PRODUITS')
            ->assertSee('CHARGES');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.accounting', ['tab' => 'bilan']))
            ->assertOk()
            ->assertSee('ACTIF')
            ->assertSee('PASSIF');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.accounting', ['tab' => 'grand_livre']))
            ->assertOk()
            ->assertSee('Grand Livre des Comptes');
    }
}
