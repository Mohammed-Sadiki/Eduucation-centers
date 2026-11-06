<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Stagiaire;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseAccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerTanger;
    protected Center $centerTetouan;
    protected User $superAdmin;
    protected User $centerAdminTanger;
    protected User $centerAdminTetouan;
    protected User $secretaryTanger;
    protected FinanceService $financeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeService = app(FinanceService::class);

        $this->centerTanger = Center::create([
            'name' => 'Centre Tanger',
            'code' => 'TNG',
            'status' => 'active',
        ]);

        $this->centerTetouan = Center::create([
            'name' => 'Centre Tétouan',
            'code' => 'TET',
            'status' => 'active',
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'center_id' => null,
        ]);

        $this->centerAdminTanger = User::factory()->create([
            'role' => User::ROLE_CENTER_ADMIN,
            'name' => 'Admin Tanger',
            'email' => 'admin.tanger@example.com',
            'center_id' => $this->centerTanger->id,
        ]);

        $this->centerAdminTetouan = User::factory()->create([
            'role' => User::ROLE_CENTER_ADMIN,
            'name' => 'Admin Tétouan',
            'email' => 'admin.tetouan@example.com',
            'center_id' => $this->centerTetouan->id,
        ]);

        $this->secretaryTanger = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'name' => 'Secrétaire Tanger',
            'email' => 'secretaire.tanger@example.com',
            'center_id' => $this->centerTanger->id,
        ]);
    }

    /**
     * Test 1 : Répartition 70/30 pour les stagiaires selon qui l'a enregistré.
     */
    public function test_stagiaire_revenue_split_rule(): void
    {
        // 1. Stagiaire enregistré par Super Admin (prix 1 000 DH) -> 70% SA (700), 30% Centre (300)
        Stagiaire::create([
            'center_id' => $this->centerTanger->id,
            'created_by' => $this->superAdmin->id,
            'first_name' => 'Omar',
            'last_name' => 'Alami',
            'stage_price' => 1000.00,
            'status' => 'en_cours',
        ]);

        // 2. Stagiaire enregistré par Secrétaire (prix 2 000 DH) -> 70% Centre (1400), 30% SA (600)
        Stagiaire::create([
            'center_id' => $this->centerTanger->id,
            'created_by' => $this->secretaryTanger->id,
            'first_name' => 'Salma',
            'last_name' => 'Bennani',
            'stage_price' => 2000.00,
            'status' => 'en_cours',
        ]);

        $split = $this->financeService->calculateStagiaireRevenueSplit($this->centerTanger->id);

        $this->assertEquals(3000.00, $split['total_collected']);
        // Super Admin share : 700 + 600 = 1300
        $this->assertEquals(1300.00, $split['super_admin_share']);
        // Center Admin share : 300 + 1400 = 1700
        $this->assertEquals(1700.00, $split['center_admin_share']);
    }

    /**
     * Test 2 : Admin Centre peut ajouter une dépense pour son propre centre.
     */
    public function test_center_admin_can_add_expense_for_own_center(): void
    {
        $response = $this->actingAs($this->centerAdminTanger)->post(route('super-admin.finance.expenses.store'), [
            'title' => 'Facture Électricité Tanger',
            'amount' => 450.50,
        ]);

        $response->assertRedirect(route('super-admin.finance.expenses'));

        $this->assertDatabaseHas('expenses', [
            'title' => 'Facture Électricité Tanger',
            'amount' => 450.50,
            'center_id' => $this->centerTanger->id,
            'recorded_by' => $this->centerAdminTanger->id,
        ]);
    }

    /**
     * Test 3 : Super Admin peut ajouter une dépense pour son compte.
     */
    public function test_super_admin_can_add_expense(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.finance.expenses.store'), [
            'title' => 'Abonnement Serveur Cloud',
            'amount' => 1200.00,
            'center_id' => $this->centerTanger->id,
        ]);

        $response->assertRedirect(route('super-admin.finance.expenses'));

        $this->assertDatabaseHas('expenses', [
            'title' => 'Abonnement Serveur Cloud',
            'amount' => 1200.00,
            'recorded_by' => $this->superAdmin->id,
        ]);
    }

    /**
     * Test 4 : Calcul automatique du solde (Revenus attribués - Dépenses enregistrées).
     */
    public function test_automatic_balance_calculation(): void
    {
        // Inscription élève par Secrétaire Tanger avec paiement 10 000 DH
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT, 'center_id' => $this->centerTanger->id]);
        $student = \App\Models\Student::factory()->create(['user_id' => $studentUser->id]);
        $formation = Formation::factory()->create(['center_id' => $this->centerTanger->id]);
        $enrollment = Enrollment::create([
            'registration_number' => 'REG-2026-001',
            'application_date' => now()->toDateString(),
            'student_id' => $student->id,
            'formation_id' => $formation->id,
            'center_id' => $this->centerTanger->id,
            'created_by' => $this->secretaryTanger->id,
            'status' => 'accepted',
            'school_year' => '2026/2027',
        ]);
        Payment::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'center_id' => $this->centerTanger->id,
            'amount' => 10000.00,
            'status' => 'paid',
            'payment_date' => now(),
            'payment_method' => 'cash',
            'recorded_by' => $this->secretaryTanger->id,
        ]);

        // Dépense enregistrée par Admin Centre Tanger : 2 000 DH
        Expense::create([
            'center_id' => $this->centerTanger->id,
            'title' => 'Rénovation peinture Tanger',
            'amount' => 2000.00,
            'status' => 'paid',
            'school_year' => '2026/2027',
            'expense_date' => now(),
            'payment_method' => 'cash',
            'recorded_by' => $this->centerAdminTanger->id,
        ]);

        // Compte Admin Centre Tanger :
        // Revenus élèves = 70% de 10 000 = 7 000 DH
        // Dépenses = 2 000 DH
        // Solde = 7 000 - 2 000 = 5 000 DH
        $balanceCenter = $this->financeService->getAccountBalance($this->centerTanger->id, false);
        $this->assertEquals(7000.00, $balanceCenter['revenue']);
        $this->assertEquals(2000.00, $balanceCenter['expenses']);
        $this->assertEquals(5000.00, $balanceCenter['balance']);
        $this->assertFalse($balanceCenter['is_negative']);

        // Compte Super Admin :
        // Revenus élèves = 30% de 10 000 = 3 000 DH
        // Dépenses Super Admin = 0 DH
        // Solde = 3 000 DH
        $balanceSA = $this->financeService->getAccountBalance($this->centerTanger->id, true);
        $this->assertEquals(3000.00, $balanceSA['revenue']);
        $this->assertEquals(0.00, $balanceSA['expenses']);
        $this->assertEquals(3000.00, $balanceSA['balance']);
    }

    /**
     * Test 5 : Le solde peut devenir négatif si les dépenses dépassent les revenus.
     */
    public function test_balance_can_be_negative(): void
    {
        // Dépense enregistrée par Admin Centre sans aucun revenu : 4 500 DH
        Expense::create([
            'center_id' => $this->centerTanger->id,
            'title' => 'Achat ordinateurs',
            'amount' => 4500.00,
            'status' => 'paid',
            'school_year' => '2026/2027',
            'expense_date' => now(),
            'payment_method' => 'cash',
            'recorded_by' => $this->centerAdminTanger->id,
        ]);

        $balance = $this->financeService->getAccountBalance($this->centerTanger->id, false);
        $this->assertEquals(0.00, $balance['revenue']);
        $this->assertEquals(4500.00, $balance['expenses']);
        $this->assertEquals(-4500.00, $balance['balance']);
        $this->assertTrue($balance['is_negative']);
    }

    /**
     * Test 6 : Isolation stricte entre les centres.
     */
    public function test_expenses_isolation_between_centers(): void
    {
        // Dépense Tanger
        Expense::create([
            'center_id' => $this->centerTanger->id,
            'title' => 'Dépense Tanger Secrète',
            'amount' => 500.00,
            'status' => 'paid',
            'school_year' => '2026/2027',
            'expense_date' => now(),
            'payment_method' => 'cash',
            'recorded_by' => $this->centerAdminTanger->id,
        ]);

        // Dépense Tétouan
        Expense::create([
            'center_id' => $this->centerTetouan->id,
            'title' => 'Dépense Tétouan Secrète',
            'amount' => 800.00,
            'status' => 'paid',
            'school_year' => '2026/2027',
            'expense_date' => now(),
            'payment_method' => 'cash',
            'recorded_by' => $this->centerAdminTetouan->id,
        ]);

        // L'Admin de Tanger consulte la page des dépenses
        $responseTanger = $this->actingAs($this->centerAdminTanger)->get(route('super-admin.finance.expenses'));
        $responseTanger->assertStatus(200);
        $responseTanger->assertSee('Dépense Tanger Secrète');
        $responseTanger->assertDontSee('Dépense Tétouan Secrète');

        // L'Admin de Tétouan consulte la page des dépenses
        $responseTetouan = $this->actingAs($this->centerAdminTetouan)->get(route('super-admin.finance.expenses'));
        $responseTetouan->assertStatus(200);
        $responseTetouan->assertSee('Dépense Tétouan Secrète');
        $responseTetouan->assertDontSee('Dépense Tanger Secrète');
    }
}
