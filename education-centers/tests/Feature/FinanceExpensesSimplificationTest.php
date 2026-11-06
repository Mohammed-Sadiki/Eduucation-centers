<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\CenterBudget;
use App\Models\Expense;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceExpensesSimplificationTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerTanger;
    protected Center $centerAgadir;
    protected User $superAdmin;
    protected User $centerAdminTanger;
    protected User $secretary;
    protected User $teacher;

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

        $this->centerAdminTanger = User::create([
            'name' => 'Responsable Tanger',
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
            'email' => 'prof.tanger@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_TEACHER,
            'center_id' => $this->centerTanger->id,
            'status' => 'active',
        ]);

        // Budget alloué pour Tanger
        CenterBudget::create([
            'center_id' => $this->centerTanger->id,
            'school_year' => '2026/2027',
            'annual_allocated_budget' => 100000.00,
        ]);
    }

    /**
     * Test 1 : Les 4 cartes s'affichent correctement.
     */
    public function test_expenses_page_displays_the_four_cards(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.finance.expenses', ['center_id' => $this->centerTanger->id]));

        $response->assertStatus(200);
        $response->assertSee('Revenus attribués');
        $response->assertSee('Total Dépenses');
        $response->assertSee('Solde du compte');
        $response->assertSee('Charges engagées');
        $response->assertSee('+ Ajouter');
        $response->assertSee('Historique des dépenses');
    }

    /**
     * Test 2 : Enregistrement d'une dépense de 250 DH via le modal simple.
     */
    public function test_super_admin_can_store_simple_expense_and_updates_budget(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.finance.expenses.store'), [
            'center_id' => $this->centerTanger->id,
            'title' => 'Fournitures de bureau',
            'amount' => 250.00,
        ]);

        $response->assertRedirect(route('super-admin.finance.expenses'));
        $this->assertDatabaseHas('expenses', [
            'center_id' => $this->centerTanger->id,
            'title' => 'Fournitures de bureau',
            'amount' => 250.00,
            'recorded_by' => $this->superAdmin->id,
        ]);

        // Vérification de l'affichage sur la page
        $pageResponse = $this->actingAs($this->superAdmin)->get(route('super-admin.finance.expenses', ['center_id' => $this->centerTanger->id]));
        $pageResponse->assertSee('Fournitures de bureau');
        $pageResponse->assertSee('250,00 DH');
    }

    /**
     * Test 3 : L'historique permet de filtrer par mois et année.
     */
    public function test_history_filter_by_month_and_year(): void
    {
        // Dépense en Novembre 2026
        Expense::create([
            'center_id' => $this->centerTanger->id,
            'title' => 'Transport Novembre',
            'amount' => 150.00,
            'currency' => 'MAD',
            'expense_date' => '2026-11-05',
            'payment_method' => 'cash',
            'school_year' => '2026/2027',
            'status' => 'paid',
            'recorded_by' => $this->superAdmin->id,
        ]);

        // Dépense en Octobre 2026
        Expense::create([
            'center_id' => $this->centerTanger->id,
            'title' => 'Imprimante Octobre',
            'amount' => 800.00,
            'currency' => 'MAD',
            'expense_date' => '2026-10-15',
            'payment_method' => 'cash',
            'school_year' => '2026/2027',
            'status' => 'paid',
            'recorded_by' => $this->superAdmin->id,
        ]);

        // Filtrer sur le mois de novembre
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.finance.expenses', [
            'center_id' => $this->centerTanger->id,
            'history_month' => 11,
            'history_year' => 2026,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Transport Novembre');
        $response->assertDontSee('Imprimante Octobre');
    }

    /**
     * Test 4 : Les salaires des professeurs continuent d'être inclus dans les charges engagées.
     */
    public function test_teacher_salaries_are_included_in_committed_charges(): void
    {
        $teacherModel = Teacher::create([
            'center_id' => $this->centerTanger->id,
            'user_id' => $this->teacher->id,
            'teacher_number' => 'TCH-001',
            'first_name' => 'Prof',
            'last_name' => 'Tanger',
            'status' => 'active',
        ]);

        TeacherSalary::create([
            'center_id' => $this->centerTanger->id,
            'teacher_id' => $teacherModel->id,
            'month' => 10,
            'year' => 2026,
            'school_year' => '2026/2027',
            'expected_amount' => 5000.00,
            'paid_amount' => 5000.00,
            'currency' => 'MAD',
            'status' => 'paid',
            'recorded_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.finance.expenses', ['center_id' => $this->centerTanger->id]));
        $response->assertStatus(200);
        // Le total des charges engagées doit refléter les 5 000 DH
        $response->assertSee('5 000,00');
    }

    /**
     * Test 5 : Responsable Centre consulte et peut ajouter une dépense pour son centre.
     */
    public function test_center_manager_can_access_and_add_expense(): void
    {
        $response = $this->actingAs($this->centerAdminTanger)->get(route('super-admin.finance.expenses'));
        $response->assertStatus(200);
        $response->assertSee('Historique des dépenses');
        $response->assertSee('+ Ajouter');

        // L'Admin Centre peut ajouter une dépense
        $postResponse = $this->actingAs($this->centerAdminTanger)->post(route('super-admin.finance.expenses.store'), [
            'title' => 'Achat fournitures centre',
            'amount' => 150,
        ]);
        $postResponse->assertRedirect(route('super-admin.finance.expenses'));

        $this->assertDatabaseHas('expenses', [
            'title' => 'Achat fournitures centre',
            'amount' => 150,
            'center_id' => $this->centerTanger->id,
            'recorded_by' => $this->centerAdminTanger->id,
        ]);
    }

    /**
     * Test 6 : Secrétaire et Professeur sont interdits d'accès.
     */
    public function test_secretary_and_teacher_cannot_access_expenses(): void
    {
        $this->actingAs($this->secretary)->get(route('super-admin.finance.expenses'))->assertStatus(403);
        $this->actingAs($this->teacher)->get(route('super-admin.finance.expenses'))->assertStatus(403);
    }

    /**
     * Test 7 : Isolation entre les centres dans l'historique des dépenses.
     */
    public function test_center_isolation_in_expenses(): void
    {
        Expense::create([
            'center_id' => $this->centerAgadir->id,
            'title' => 'Dépense Spécifique Agadir',
            'amount' => 300.00,
            'currency' => 'MAD',
            'expense_date' => '2026-11-05',
            'payment_method' => 'cash',
            'school_year' => '2026/2027',
            'status' => 'paid',
            'recorded_by' => $this->superAdmin->id,
        ]);

        // Le responsable de Tanger ne doit pas voir la dépense d'Agadir
        $response = $this->actingAs($this->centerAdminTanger)->get(route('super-admin.finance.expenses'));
        $response->assertStatus(200);
        $response->assertDontSee('Dépense Spécifique Agadir');
    }
}
