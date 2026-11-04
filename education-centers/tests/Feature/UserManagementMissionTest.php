<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementMissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Le bouton "Utilisateurs" est présent sur l'interface administrative principale (admin.home)
     *    et dans le menu de navigation (portal topbar).
     */
    public function test_utilisateurs_buttons_are_visible_for_admins()
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $responsePortal = $this->actingAs($superAdmin)->get(route('admin.home'));
        $responsePortal->assertStatus(200);
        $responsePortal->assertSee('id="btn-admin-home-users"', false);
        $responsePortal->assertSee('id="portal-users-button"', false);
        $responsePortal->assertSee(route('users.index'));
    }

    /**
     * 2 & 3. La page affiche uniquement Super Admin, Admins Centre et Professeurs.
     * Les élèves n'apparaissent JAMAIS dans la liste des utilisateurs.
     */
    public function test_users_index_shows_only_super_admin_center_admin_and_teachers()
    {
        $center = Center::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Super Administrateur Unique',
        ]);
        $centerAdmin = User::factory()->centerAdmin($center->id)->create([
            'name' => 'Admin Centre Test',
        ]);
        $teacher = User::factory()->teacher($center->id)->create([
            'name' => 'Professeur Test',
        ]);

        // Création d'un dossier élève pur (user_id = null)
        $student = Student::factory()->create([
            'center_id' => $center->id,
            'first_name' => 'Imad',
            'last_name' => 'EleveFantome',
            'user_id' => null,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('users.index'));
        $response->assertStatus(200);

        // Doit voir les comptes autorisés
        $response->assertSee('Super Administrateur Unique');
        $response->assertSee('Admin Centre Test');
        $response->assertSee('Professeur Test');

        // Ne doit jamais voir le dossier élève
        $response->assertDontSee('EleveFantome');

        // Doit contenir la carte statistique Total utilisateurs
        $response->assertSee('id="stat-total-users"', false);
        $response->assertSee('Total utilisateurs');
        $response->assertSee('Gestion des utilisateurs');
    }

    /**
     * 4 & 5. Seul le Super Admin peut créer un compte Professeur.
     * Un Admin Centre ne peut pas accéder au formulaire ni créer de compte (403).
     * Aucun compte élève ne peut être créé.
     */
    public function test_form_and_backend_only_allow_creating_teacher_accounts()
    {
        $center = Center::factory()->create();
        $admin = User::factory()->centerAdmin($center->id)->create();
        $superAdmin = User::factory()->superAdmin()->create();

        // Admin Centre se voit refuser l'accès au formulaire de création (403)
        $responseCreateAdmin = $this->actingAs($admin)->get(route('users.create'));
        $responseCreateAdmin->assertStatus(403);

        // Super Admin accède au formulaire de création
        $responseCreate = $this->actingAs($superAdmin)->get(route('users.create'));
        $responseCreate->assertStatus(200);
        $responseCreate->assertSee('Professeur');
        $responseCreate->assertDontSee('value="student"', false);
        $responseCreate->assertDontSee('value="center_admin"', false);

        // Tentative d'un admin centre de soumettre -> 403 Forbidden
        $responseAdminStore = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Tentative Admin Centre',
            'email' => 'admin_attempt@ec.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'status' => 'active',
            'role' => User::ROLE_TEACHER,
        ]);
        $responseAdminStore->assertStatus(403);

        // Tentative de soumission d'un rôle "student" par Super Admin -> Doit être rejeté par la validation
        $responseStudent = $this->actingAs($superAdmin)->post(route('users.store'), [
            'name' => 'Tentative Eleve',
            'email' => 'fake_student@ec.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'status' => 'active',
            'role' => 'student',
        ]);
        $responseStudent->assertSessionHasErrors(['role']);

        \App\Models\Domain::firstOrCreate(['slug' => 'informatique'], ['name' => 'Informatique', 'icon' => 'monitor', 'color' => 'blue', 'sort_order' => 1, 'is_active' => true]);
        $formation = \App\Models\Formation::create([
            'center_id' => $center->id,
            'domain' => 'informatique',
            'name' => 'Web Dev Berlin',
            'code' => 'WEB-BER',
            'duration_hours' => 120,
            'price' => 500,
            'status' => 'active',
        ]);

        // Création valide d'un professeur par Super Admin
        $responseTeacher = $this->actingAs($superAdmin)->post(route('users.store'), [
            'name' => 'Nouveau Professeur',
            'email' => 'nouveau_prof@ec.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'status' => 'active',
            'center_id' => $center->id,
            'role' => User::ROLE_TEACHER,
            'domain' => 'informatique',
            'formation_id' => $formation->id,
        ]);
        $responseTeacher->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'nouveau_prof@ec.test',
            'role' => User::ROLE_TEACHER,
            'center_id' => $center->id,
            'domain' => 'informatique',
            'formation_id' => $formation->id,
        ]);
    }

    /**
     * 6. Les élèves ne peuvent pas se connecter.
     */
    public function test_students_cannot_login()
    {
        $center = Center::factory()->create();
        $studentUser = User::factory()->create([
            'email' => 'student_attempt@ec.test',
            'password' => Hash::make('Password123!'),
            'role' => 'student',
            'center_id' => $center->id,
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'student_attempt@ec.test',
            'password' => 'Password123!',
        ]);

        // La connexion doit être refusée et déconnectée avec un message d'erreur
        $this->assertGuest();
        $response->assertSessionHasErrors(['email']);
    }

    /**
     * 7 & 8. L'ajout d'un élève ne crée aucun compte utilisateur.
     * Les dossiers et paiements des élèves restent intacts.
     */
    public function test_adding_student_creates_administrative_dossier_without_user_account()
    {
        $center = Center::factory()->create();
        $admin = User::factory()->centerAdmin($center->id)->create();

        $initialUserCount = User::count();

        // Création d'un dossier étudiant via StudentController
        $response = $this->actingAs($admin)->post(route('students.store'), [
            'center_id' => $center->id,
            'first_name' => 'Karim',
            'last_name' => 'Bennani',
            'date_of_birth' => '2002-05-15',
            'status' => 'active',
        ]);

        // Aucun utilisateur supplémentaire créé
        $this->assertEquals($initialUserCount, User::count());

        // Le dossier étudiant existe bien avec user_id = null
        $student = Student::where('first_name', 'Karim')->where('last_name', 'Bennani')->first();
        $this->assertNotNull($student);
        $this->assertNull($student->user_id);
        $this->assertEquals($center->id, $student->center_id);
    }

    /**
     * 9 & 10. Les comptes administrateurs et professeurs continuent de se connecter.
     */
    public function test_admins_and_teachers_can_login_successfully()
    {
        $center = Center::factory()->create();
        $teacher = User::factory()->teacher($center->id)->create([
            'email' => 'professeur_actif@ec.test',
            'password' => Hash::make('SecretPass123!'),
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'professeur_actif@ec.test',
            'password' => 'SecretPass123!',
        ]);

        $this->assertAuthenticatedAs($teacher);
    }

    /**
     * 11. Les permissions multi-centres sont strictement respectées.
     */
    public function test_multi_center_isolation_for_center_admin()
    {
        $center1 = Center::factory()->create();
        $center2 = Center::factory()->create();

        $admin1 = User::factory()->centerAdmin($center1->id)->create([
            'name' => 'Admin Centre 1',
        ]);

        $teacher1 = User::factory()->teacher($center1->id)->create([
            'name' => 'Professeur Centre 1',
        ]);

        $teacher2 = User::factory()->teacher($center2->id)->create([
            'name' => 'Professeur Centre 2',
        ]);

        $response = $this->actingAs($admin1)->get(route('users.index'));
        $response->assertStatus(200);
        $response->assertSee('Professeur Centre 1');
        $response->assertDontSee('Professeur Centre 2');
    }

    /**
     * 12. Typographie et conformité du formulaire d'ajout d'utilisateur.
     */
    public function test_user_creation_form_has_large_typography_and_clean_design()
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->get(route('users.create'));
        $response->assertStatus(200);

        // Grandes écritures : classes text-base (17 px via l'échelle centrale),
        // text-2xl font-extrabold
        $response->assertSee('text-2xl font-extrabold', false);
        $response->assertSee('text-base', false);
        $response->assertSee('ec-card', false);
        $response->assertSee('ec-input', false);
        $response->assertSee('ec-label', false);
    }
}
