<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceRevenueSplitTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerTanger;
    protected Center $centerAgadir;
    protected User $superAdmin;
    protected User $centerAdmin;
    protected User $secretary;
    protected Formation $formationTanger;

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

        $this->formationTanger = Formation::create([
            'center_id' => $this->centerTanger->id,
            'name' => 'Développement Web',
            'code' => 'DEV-WEB-TGR',
            'duration_months' => 10,
            'price' => 10000.00,
            'domain' => 'informatique',
            'status' => 'active',
        ]);
    }

    /**
     * Test 1 : Inscription par Super Admin avec versement de 1 000 DH -> 700 DH (Super Admin) / 300 DH (Responsable Centre).
     */
    public function test_enrollment_by_super_admin_gives_70_percent_to_super_admin_and_30_percent_to_center_manager(): void
    {
        $student = Student::create([
            'center_id' => $this->centerTanger->id,
            'student_number' => 'STU-001',
            'first_name' => 'Ali',
            'last_name' => 'Tanger',
            'gender' => 'male',
            'nationality' => 'Marocaine',
            'address' => 'Tanger',
            'phone' => '0600000001',
            'status' => 'active',
        ]);

        $enrollment = Enrollment::create([
            'center_id' => $this->centerTanger->id,
            'student_id' => $student->id,
            'formation_id' => $this->formationTanger->id,
            'created_by' => $this->superAdmin->id,
            'school_year' => '2026/2027',
            'official_price' => 10000.00,
            'final_price' => 10000.00,
            'registration_number' => 'REG-001',
            'application_date' => now()->toDateString(),
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);

        Payment::create([
            'center_id' => $this->centerTanger->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'formation_id' => $this->formationTanger->id,
            'school_year' => '2026/2027',
            'amount' => 1000.00,
            'currency' => 'MAD',
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => 'PAY-001',
            'status' => 'paid',
            'recorded_by' => $this->superAdmin->id,
        ]);

        $financeService = app(FinanceService::class);
        $rows = $financeService->getStudentFinancialRows(null, '2026/2027', $this->centerTanger->id);
        $split = $financeService->calculateRevenueSplit($rows, $this->centerTanger->id);

        $this->assertEquals(1000.00, $split['total_collected']);
        $this->assertEquals(700.00, $split['super_admin_share']);
        $this->assertEquals(300.00, $split['center_admin_share']);
    }

    /**
     * Test 2 : Inscription par Secrétaire avec versement de 1 000 DH -> 300 DH (Super Admin) / 700 DH (Responsable Centre).
     */
    public function test_enrollment_by_secretary_gives_70_percent_to_center_manager_and_30_percent_to_super_admin(): void
    {
        $student = Student::create([
            'center_id' => $this->centerTanger->id,
            'student_number' => 'STU-002',
            'first_name' => 'Sara',
            'last_name' => 'Tanger',
            'gender' => 'female',
            'nationality' => 'Marocaine',
            'address' => 'Tanger',
            'phone' => '0600000002',
            'status' => 'active',
        ]);

        $enrollment = Enrollment::create([
            'center_id' => $this->centerTanger->id,
            'student_id' => $student->id,
            'formation_id' => $this->formationTanger->id,
            'created_by' => $this->secretary->id,
            'school_year' => '2026/2027',
            'official_price' => 10000.00,
            'final_price' => 10000.00,
            'registration_number' => 'REG-002',
            'application_date' => now()->toDateString(),
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);

        Payment::create([
            'center_id' => $this->centerTanger->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'formation_id' => $this->formationTanger->id,
            'school_year' => '2026/2027',
            'amount' => 1000.00,
            'currency' => 'MAD',
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => 'PAY-002',
            'status' => 'paid',
            'recorded_by' => $this->secretary->id,
        ]);

        $financeService = app(FinanceService::class);
        $rows = $financeService->getStudentFinancialRows(null, '2026/2027', $this->centerTanger->id);
        $split = $financeService->calculateRevenueSplit($rows, $this->centerTanger->id);

        $this->assertEquals(1000.00, $split['total_collected']);
        $this->assertEquals(300.00, $split['super_admin_share']);
        $this->assertEquals(700.00, $split['center_admin_share']);
    }

    /**
     * Test 3 : Versement partiel suivi d'un deuxième versement sans double comptage.
     */
    public function test_multiple_payments_sum_correctly_without_duplication(): void
    {
        $student = Student::create([
            'center_id' => $this->centerTanger->id,
            'student_number' => 'STU-003',
            'first_name' => 'Omar',
            'last_name' => 'Tanger',
            'gender' => 'male',
            'nationality' => 'Marocaine',
            'address' => 'Tanger',
            'phone' => '0600000003',
            'status' => 'active',
        ]);

        $enrollment = Enrollment::create([
            'center_id' => $this->centerTanger->id,
            'student_id' => $student->id,
            'formation_id' => $this->formationTanger->id,
            'created_by' => $this->secretary->id,
            'school_year' => '2026/2027',
            'official_price' => 10000.00,
            'final_price' => 10000.00,
            'registration_number' => 'REG-003',
            'application_date' => now()->toDateString(),
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);

        // Versement 1 : 500 DH
        Payment::create([
            'center_id' => $this->centerTanger->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'formation_id' => $this->formationTanger->id,
            'school_year' => '2026/2027',
            'amount' => 500.00,
            'currency' => 'MAD',
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => 'PAY-003A',
            'status' => 'paid',
            'recorded_by' => $this->secretary->id,
        ]);

        // Versement 2 : 1 500 DH
        Payment::create([
            'center_id' => $this->centerTanger->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'formation_id' => $this->formationTanger->id,
            'school_year' => '2026/2027',
            'amount' => 1500.00,
            'currency' => 'MAD',
            'payment_date' => now()->toDateString(),
            'method' => 'bank_transfer',
            'reference' => 'PAY-003B',
            'status' => 'paid',
            'recorded_by' => $this->secretary->id,
        ]);

        $financeService = app(FinanceService::class);
        $rows = $financeService->getStudentFinancialRows(null, '2026/2027', $this->centerTanger->id);
        $split = $financeService->calculateRevenueSplit($rows, $this->centerTanger->id);

        // Total 2000 DH -> Secrétaire = 70% Responsable (1400 DH) / 30% Super Admin (600 DH)
        $this->assertEquals(2000.00, $split['total_collected']);
        $this->assertEquals(600.00, $split['super_admin_share']);
        $this->assertEquals(1400.00, $split['center_admin_share']);
    }

    /**
     * Test 4 : Page Paiements étudiants affiche les deux cartes pour le Super Admin.
     */
    public function test_students_page_renders_the_two_cards_correctly(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.finance.students'));
        $response->assertStatus(200);
        $response->assertSee('Part du Super Admin');
        $response->assertSee('Part du Responsable Centre');
        $response->assertDontSee('Total attendu');
        $response->assertDontSee('Reste à encaisser');
    }

    /**
     * Test 5 : Responsable Centre consulte sans pouvoir encaisser.
     */
    public function test_center_manager_can_view_finance_page_read_only(): void
    {
        $response = $this->actingAs($this->centerAdmin)->get(route('super-admin.finance.students'));
        $response->assertStatus(200);
        $response->assertSee('Part du Responsable Centre');
        // Pas de bouton global "Encaisser un versement"
        $response->assertDontSee('Encaisser un versement');

        // Tentative de POST rejetée avec 403
        $postResponse = $this->actingAs($this->centerAdmin)->post(route('super-admin.finance.students.payment.store'), [
            'student_id' => 1,
            'school_year' => '2026/2027',
            'amount' => 500,
            'currency' => 'MAD',
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
        ]);
        $postResponse->assertStatus(403);
    }
}
