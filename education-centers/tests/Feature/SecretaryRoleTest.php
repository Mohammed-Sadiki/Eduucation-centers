<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretaryRoleTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerA;
    protected Center $centerB;
    protected User $superAdmin;
    protected User $centerAdminA;
    protected User $secretaryA;
    protected User $secretaryB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerA = Center::create([
            'name' => 'Centre Tanger',
            'code' => 'TGR',
            'city' => 'Tanger',
            'country' => 'Morocco',
            'status' => 'active',
        ]);

        $this->centerB = Center::create([
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

        $this->centerAdminA = User::create([
            'name' => 'Responsable Tanger',
            'email' => 'admin.tanger@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CENTER_ADMIN,
            'center_id' => $this->centerA->id,
            'status' => 'active',
        ]);

        $this->secretaryA = User::create([
            'name' => 'Secrétaire Tanger',
            'email' => 'secretaire.tanger@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerA->id,
            'status' => 'active',
        ]);

        $this->secretaryB = User::create([
            'name' => 'Secrétaire Agadir',
            'email' => 'secretaire.agadir@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerB->id,
            'status' => 'active',
        ]);
    }

    public function test_secretary_can_access_admin_home(): void
    {
        $response = $this->actingAs($this->secretaryA)->get(route('admin.home'));
        $response->assertStatus(200);
        $response->assertSee('Secrétaire');
    }

    public function test_secretary_can_view_students_list(): void
    {
        $response = $this->actingAs($this->secretaryA)->get(route('students.index'));
        $response->assertStatus(200);
    }

    public function test_secretary_cannot_access_global_finance_module(): void
    {
        $response = $this->actingAs($this->secretaryA)->get('/super-admin/finance');
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]));
    }

    public function test_secretary_cannot_manage_users(): void
    {
        $response = $this->actingAs($this->secretaryA)->get(route('users.index'));
        $response->assertStatus(403);
    }

    public function test_center_admin_is_read_only_for_student_creation(): void
    {
        $response = $this->actingAs($this->centerAdminA)->get(route('students.create'));
        $response->assertStatus(403);
    }

    public function test_center_admin_can_view_students(): void
    {
        $response = $this->actingAs($this->centerAdminA)->get(route('students.index'));
        $response->assertStatus(200);
    }

    public function test_secretary_cannot_view_or_modify_students_from_another_center(): void
    {
        $studentB = Student::create([
            'center_id' => $this->centerB->id,
            'student_number' => 'STU-B-001',
            'first_name' => 'Karim',
            'last_name' => 'Agadir',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'birth_place' => 'Agadir',
            'nationality' => 'Marocaine',
            'cin' => 'AB123456',
            'address' => 'Rue Agadir',
            'email' => 'karim@test.com',
            'phone' => '0612345678',
            'status' => 'active',
            'registration_number' => 'STU-B-001',
        ]);

        // Secrétaire A tente d'accéder à l'élève du centre B
        $response = $this->actingAs($this->secretaryA)->get(route('students.show', $studentB));
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]));
    }

    public function test_super_admin_can_create_secretary_account(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Nouvelle Secrétaire',
            'email' => 'new.sec@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerA->id,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'new.sec@test.com',
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerA->id,
        ]);
    }
}
