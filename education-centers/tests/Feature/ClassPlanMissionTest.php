<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Domain;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassPlanMissionTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;
    protected Center $centerMunich;
    protected User $superAdmin;
    protected User $adminBerlin;
    protected User $adminMunich;
    protected Teacher $teacherBerlin;
    protected Teacher $teacherMunich;
    protected Formation $formationBerlin;
    protected ClassRoom $classBerlin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER', 'city' => 'Berlin']);
        $this->centerMunich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN', 'city' => 'Munich']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@ec.test']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab@ec.test']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['email' => 'am@ec.test']);

        Domain::firstOrCreate(['slug' => 'informatique'], ['name' => 'Informatique', 'icon' => 'monitor', 'color' => 'blue', 'sort_order' => 1, 'is_active' => true]);

        $this->formationBerlin = Formation::create([
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Développement Web',
            'code' => 'DWB',
            'duration_hours' => 120,
            'price' => 500,
            'status' => 'active',
        ]);

        // Utilisateur professeur Berlin
        $teacherUserBerlin = User::factory()->teacher($this->centerBerlin->id)->create([
            'name' => 'Ahmed Mansour',
            'domain' => 'informatique',
            'formation_id' => $this->formationBerlin->id,
        ]);

        $this->teacherBerlin = Teacher::create([
            'user_id' => $teacherUserBerlin->id,
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'formation_id' => $this->formationBerlin->id,
            'teacher_number' => 'BER-T-001',
            'first_name' => 'Ahmed',
            'last_name' => 'Mansour',
            'gender' => 'male',
            'status' => 'active',
        ]);

        // Classe Berlin
        $this->classBerlin = ClassRoom::create([
            'center_id' => $this->centerBerlin->id,
            'formation_id' => $this->formationBerlin->id,
            'name' => 'Classe DW Berlin A',
            'code' => 'DWB-A',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        // Affecter le professeur à la classe
        $this->teacherBerlin->classes()->attach($this->classBerlin->id);

        // Professeur Munich
        $formationMunich = Formation::create([
            'center_id' => $this->centerMunich->id,
            'domain' => 'informatique',
            'name' => 'Réseau Munich',
            'code' => 'RNM',
            'duration_hours' => 80,
            'price' => 400,
            'status' => 'active',
        ]);
        $teacherUserMunich = User::factory()->teacher($this->centerMunich->id)->create();
        $this->teacherMunich = Teacher::create([
            'user_id' => $teacherUserMunich->id,
            'center_id' => $this->centerMunich->id,
            'teacher_number' => 'MUN-T-001',
            'first_name' => 'Marie',
            'last_name' => 'Dupont',
            'gender' => 'female',
            'status' => 'active',
        ]);
    }

    /**
     * 1. Le bouton « Plan de classe » apparaît sur la carte Formations de
     *    la page d'accueil admin pour le Super Admin.
     */
    public function test_class_plan_button_appears_on_super_admin_domaines(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.home'));
        $response->assertStatus(200);
        $response->assertSee('btn-class-plan-domaines', false);
        $response->assertSee(route('class-plan.index'));
        $response->assertSee('Plan de classe');
    }

    /**
     * 2. Le bouton « Plan de classe » apparaît sur la carte Formations de
     *    la page d'accueil admin pour l'Admin Centre.
     */
    public function test_class_plan_button_appears_on_center_admin_domaines(): void
    {
        $response = $this->actingAs($this->adminBerlin)->get(route('admin.home'));
        $response->assertStatus(200);
        $response->assertSee('btn-class-plan-ca-domaines', false);
        $response->assertSee(route('class-plan.index'));
        $response->assertSee('Plan de classe');
    }

    /**
     * 3. La page Plan de classe est accessible au Super Admin.
     */
    public function test_class_plan_page_is_accessible_to_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('class-plan.index'));
        $response->assertStatus(200);
        $response->assertSee('Plan de classe');
        $response->assertSee('Choisir un professeur');
        $response->assertSee('select-teacher', false);
    }

    /**
     * 4. La page Plan de classe est accessible à l'Admin Centre.
     */
    public function test_class_plan_page_is_accessible_to_center_admin(): void
    {
        $response = $this->actingAs($this->adminBerlin)->get(route('class-plan.index'));
        $response->assertStatus(200);
        $response->assertSee('Plan de classe');
    }

    /**
     * 5. Un professeur (role=teacher) ne peut PAS accéder au plan de classe (page admin).
     */
    public function test_teacher_user_cannot_access_class_plan(): void
    {
        $teacherUser = User::factory()->teacher($this->centerBerlin->id)->create();
        $response = $this->actingAs($teacherUser)->get(route('class-plan.index'));
        $response->assertStatus(403);
    }

    /**
     * 6. Le Select affiche le professeur Berlin dans la liste.
     */
    public function test_select_shows_teacher_from_correct_center(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('class-plan.index'));
        $response->assertStatus(200);
        $response->assertSee($this->teacherBerlin->last_name);
        $response->assertSee($this->teacherMunich->last_name);
    }

    /**
     * 7. Admin Centre Berlin voit uniquement les professeurs de Berlin.
     */
    public function test_center_admin_berlin_sees_only_berlin_teachers(): void
    {
        $response = $this->actingAs($this->adminBerlin)->get(route('class-plan.index'));
        $response->assertStatus(200);
        $response->assertSee($this->teacherBerlin->last_name);
        $response->assertDontSee($this->teacherMunich->last_name);
    }

    /**
     * 8. L'API des classes d'un professeur retourne la bonne classe.
     */
    public function test_api_teacher_classes_returns_assigned_classes(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('class-plan.teacher.classes', $this->teacherBerlin->id));

        $response->assertStatus(200);
        $response->assertJsonStructure(['classes' => [['id', 'name', 'code']]]);

        $classes = $response->json('classes');
        $this->assertCount(1, $classes);
        $this->assertEquals($this->classBerlin->id, $classes[0]['id']);
        $this->assertEquals('Classe DW Berlin A', $classes[0]['name']);
    }

    /**
     * 9. Admin Centre Munich ne peut pas consulter les classes d'un professeur de Berlin.
     */
    public function test_center_admin_munich_cannot_access_berlin_teacher_classes(): void
    {
        $response = $this->actingAs($this->adminMunich)
            ->getJson(route('class-plan.teacher.classes', $this->teacherBerlin->id));

        $response->assertStatus(404);
    }

    /**
     * 10. L'API des élèves retourne les élèves acceptés dans la classe.
     */
    public function test_api_class_students_returns_accepted_students(): void
    {
        // Créer 3 élèves
        $students = [];
        for ($i = 1; $i <= 3; $i++) {
            $s = Student::create([
                'center_id' => $this->centerBerlin->id,
                'student_number' => "BER-00000{$i}",
                'first_name' => "Élève{$i}",
                'last_name' => "Dupont{$i}",
                'gender' => 'male',
                'nationality' => 'MA',
                'status' => 'active',
            ]);
            $students[] = $s;

            // Inscription acceptée dans la classe
            Enrollment::create([
                'center_id' => $this->centerBerlin->id,
                'student_id' => $s->id,
                'formation_id' => $this->formationBerlin->id,
                'class_id' => $this->classBerlin->id,
                'status' => Enrollment::STATUS_ACCEPTED,
                'registration_number' => "BER-INS-00000{$i}",
                'application_date' => '2026-10-01',
                'school_year' => '2026-2027',
            ]);
        }

        // Créer 1 élève en attente (ne doit pas apparaître)
        $pendingStudent = Student::create([
            'center_id' => $this->centerBerlin->id,
            'student_number' => 'BER-000004',
            'first_name' => 'EnAttente',
            'last_name' => 'Non',
            'gender' => 'female',
            'nationality' => 'MA',
            'status' => 'active',
        ]);
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $pendingStudent->id,
            'formation_id' => $this->formationBerlin->id,
            'class_id' => $this->classBerlin->id,
            'status' => Enrollment::STATUS_PENDING,
            'registration_number' => 'BER-INS-000004',
            'application_date' => '2026-10-01',
            'school_year' => '2026-2027',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('class-plan.class.students', $this->classBerlin->id));

        $response->assertStatus(200);
        $response->assertJsonStructure(['students' => [['id', 'full_name', 'first_name', 'last_name']]]);

        $returnedStudents = $response->json('students');
        $this->assertCount(3, $returnedStudents); // uniquement les acceptés
        $this->assertEquals(3, $response->json('count'));

        // Le nom du premier élève doit être présent
        $names = array_column($returnedStudents, 'first_name');
        $this->assertContains('Élève1', $names);
        // L'élève en attente ne doit pas être là
        $this->assertNotContains('EnAttente', $names);
    }

    /**
     * 11. Les élèves ne sont pas affichés en double même si inscrits plusieurs fois.
     */
    public function test_students_are_not_duplicated(): void
    {
        $student = Student::create([
            'center_id' => $this->centerBerlin->id,
            'student_number' => 'BER-000099',
            'first_name' => 'Unique',
            'last_name' => 'Student',
            'gender' => 'male',
            'nationality' => 'MA',
            'status' => 'active',
        ]);

        // Double inscription dans la même classe (cas limite)
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $student->id,
            'formation_id' => $this->formationBerlin->id,
            'class_id' => $this->classBerlin->id,
            'status' => Enrollment::STATUS_ACCEPTED,
            'registration_number' => 'BER-INS-000099A',
            'application_date' => '2026-10-01',
            'school_year' => '2026-2027',
        ]);
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $student->id,
            'formation_id' => $this->formationBerlin->id,
            'class_id' => $this->classBerlin->id,
            'status' => Enrollment::STATUS_ACCEPTED,
            'registration_number' => 'BER-INS-000099B',
            'application_date' => '2026-10-15',
            'school_year' => '2026-2027',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('class-plan.class.students', $this->classBerlin->id));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('students')); // Unique, pas de doublon
    }

    /**
     * 12. Admin Centre Munich ne peut pas consulter les élèves d'une classe de Berlin.
     */
    public function test_center_admin_munich_cannot_access_berlin_class_students(): void
    {
        $response = $this->actingAs($this->adminMunich)
            ->getJson(route('class-plan.class.students', $this->classBerlin->id));

        $response->assertStatus(403);
    }

    /**
     * 13. Une classe sans élèves inscrits retourne une liste vide.
     */
    public function test_empty_class_returns_empty_students_list(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('class-plan.class.students', $this->classBerlin->id));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('students'));
        $this->assertEquals(0, $response->json('count'));
    }

    /**
     * 14. Le professeur sans classe retourne une liste vide de classes.
     */
    public function test_teacher_without_classes_returns_empty_classes(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('class-plan.teacher.classes', $this->teacherMunich->id));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('classes'));
    }

    /**
     * 15. La page avec teacher_id préselectionné charge les classes server-side.
     */
    public function test_page_with_teacher_id_preselected_loads_classes_server_side(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('class-plan.index', ['teacher_id' => $this->teacherBerlin->id]));

        $response->assertStatus(200);
        $response->assertSee($this->teacherBerlin->last_name);
        $response->assertSee('Classe DW Berlin A');
    }

    /**
     * 16. La page avec teacher_id ET class_id préselectionne la classe.
     */
    public function test_page_with_teacher_and_class_preselected(): void
    {
        // Créer un élève
        $student = Student::create([
            'center_id' => $this->centerBerlin->id,
            'student_number' => 'BER-000001',
            'first_name' => 'Jean',
            'last_name' => 'Martin',
            'gender' => 'male',
            'nationality' => 'MA',
            'status' => 'active',
        ]);
        Enrollment::create([
            'center_id' => $this->centerBerlin->id,
            'student_id' => $student->id,
            'formation_id' => $this->formationBerlin->id,
            'class_id' => $this->classBerlin->id,
            'status' => Enrollment::STATUS_ACCEPTED,
            'registration_number' => 'BER-INS-000001',
            'application_date' => '2026-10-01',
            'school_year' => '2026-2027',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('class-plan.index', [
            'teacher_id' => $this->teacherBerlin->id,
            'class_id' => $this->classBerlin->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Jean');
        $response->assertSee('Martin');
    }

    /**
     * 17. Admin Centre Berlin ne peut pas voir les élèves d'un class_id de Munich
     *     même en modifiant l'URL (sécurité côté serveur).
     */
    public function test_center_admin_berlin_cannot_access_munich_class_via_url_tampering(): void
    {
        $formationMunich = Formation::create([
            'center_id' => $this->centerMunich->id,
            'domain' => 'informatique',
            'name' => 'Formation Munich',
            'code' => 'FM',
            'duration_hours' => 60,
            'price' => 300,
            'status' => 'active',
        ]);

        $classMunich = ClassRoom::create([
            'center_id' => $this->centerMunich->id,
            'formation_id' => $formationMunich->id,
            'name' => 'Classe Munich',
            'code' => 'CM',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-01',
            'status' => 'active',
        ]);

        // L'admin de Berlin tente d'accéder aux élèves d'une classe de Munich
        $response = $this->actingAs($this->adminBerlin)
            ->getJson(route('class-plan.class.students', $classMunich->id));

        $response->assertStatus(403);
    }
}
