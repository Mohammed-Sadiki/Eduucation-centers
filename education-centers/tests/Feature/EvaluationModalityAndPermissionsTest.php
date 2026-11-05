<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Formation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationModalityAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Center $centerA;
    private Center $centerB;
    private User $superAdmin;
    private User $centerAdminA;
    private User $secretaryA;
    private User $teacherUser1;
    private User $teacherUser2;
    private Teacher $teacher1;
    private Teacher $teacher2;
    private Formation $formationA;
    private ClassRoom $classA1;
    private ClassRoom $classA2;
    private Student $studentA1;
    private Student $studentA2;
    private Evaluation $evaluationA1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerA = Center::create([
            'name' => 'Centre Nord',
            'code' => 'CNR',
            'status' => 'active',
        ]);

        $this->centerB = Center::create([
            'name' => 'Centre Sud',
            'code' => 'CSU',
            'status' => 'active',
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
        ]);

        $this->centerAdminA = User::create([
            'name' => 'Admin Centre Nord',
            'email' => 'admin.nord@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CENTER_ADMIN,
            'center_id' => $this->centerA->id,
            'status' => 'active',
        ]);

        $this->secretaryA = User::create([
            'name' => 'Secretaire Nord',
            'email' => 'sec.nord@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerA->id,
            'status' => 'active',
        ]);

        $this->teacherUser1 = User::create([
            'name' => 'Professeur Un',
            'email' => 'prof1@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_TEACHER,
            'center_id' => $this->centerA->id,
            'status' => 'active',
        ]);

        $this->teacher1 = Teacher::create([
            'user_id' => $this->teacherUser1->id,
            'center_id' => $this->centerA->id,
            'teacher_number' => 'TEA-001',
            'first_name' => 'Professeur',
            'last_name' => 'Un',
            'status' => 'active',
        ]);

        $this->teacherUser2 = User::create([
            'name' => 'Professeur Deux',
            'email' => 'prof2@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_TEACHER,
            'center_id' => $this->centerA->id,
            'status' => 'active',
        ]);

        $this->teacher2 = Teacher::create([
            'user_id' => $this->teacherUser2->id,
            'center_id' => $this->centerA->id,
            'teacher_number' => 'TEA-002',
            'first_name' => 'Professeur',
            'last_name' => 'Deux',
            'status' => 'active',
        ]);

        $this->formationA = Formation::create([
            'center_id' => $this->centerA->id,
            'domain' => 'informatique',
            'name' => 'Développement Web',
            'code' => 'DEV-WEB',
            'duration_weeks' => 24,
            'duration_hours' => 600,
            'price' => 12000,
            'currency' => 'MAD',
            'status' => 'active',
        ]);

        $this->classA1 = ClassRoom::create([
            'center_id' => $this->centerA->id,
            'formation_id' => $this->formationA->id,
            'name' => 'Classe A1 Web',
            'code' => 'CLS-A1',
            'capacity' => 20,
            'status' => 'active',
        ]);

        $this->classA2 = ClassRoom::create([
            'center_id' => $this->centerA->id,
            'formation_id' => $this->formationA->id,
            'name' => 'Classe A2 Web',
            'code' => 'CLS-A2',
            'capacity' => 20,
            'status' => 'active',
        ]);

        // teacher1 is assigned to classA1 only. teacher2 is assigned to classA2 only.
        $this->classA1->teachers()->attach($this->teacher1->id);
        $this->classA2->teachers()->attach($this->teacher2->id);

        $this->studentA1 = Student::create([
            'center_id' => $this->centerA->id,
            'student_number' => 'STU-001',
            'first_name' => 'Karim',
            'last_name' => 'Alaoui',
            'status' => 'active',
        ]);

        $this->studentA2 = Student::create([
            'center_id' => $this->centerA->id,
            'student_number' => 'STU-002',
            'first_name' => 'Salma',
            'last_name' => 'Bennani',
            'status' => 'active',
        ]);

        Enrollment::create([
            'center_id' => $this->centerA->id,
            'student_id' => $this->studentA1->id,
            'formation_id' => $this->formationA->id,
            'class_id' => $this->classA1->id,
            'registration_number' => 'INS-A1',
            'application_date' => now()->toDateString(),
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);

        Enrollment::create([
            'center_id' => $this->centerA->id,
            'student_id' => $this->studentA2->id,
            'formation_id' => $this->formationA->id,
            'class_id' => $this->classA2->id,
            'registration_number' => 'INS-A2',
            'application_date' => now()->toDateString(),
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);

        $this->evaluationA1 = Evaluation::create([
            'center_id' => $this->centerA->id,
            'class_id' => $this->classA1->id,
            'formation_id' => $this->formationA->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Contrôle Algorithmes',
            'type' => 'control',
            'modality' => 'in_person',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'published',
        ]);
    }

    public function test_super_admin_can_create_evaluations_with_modality(): void
    {
        $payloadPresentiel = [
            'center_id' => $this->centerA->id,
            'class_id' => $this->classA1->id,
            'title' => 'Examen Présentiel Final',
            'type' => 'exam',
            'modality' => 'in_person',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 2,
            'status' => 'draft',
        ];

        $res1 = $this->actingAs($this->superAdmin)->post(route('evaluations.store'), $payloadPresentiel);
        $res1->assertRedirect();

        $eval1 = Evaluation::where('title', 'Examen Présentiel Final')->first();
        $this->assertNotNull($eval1);
        $this->assertSame('in_person', $eval1->modality);
        $this->assertSame('Présentiel', $eval1->modality_label);

        $payloadDistance = [
            'center_id' => $this->centerA->id,
            'class_id' => $this->classA1->id,
            'title' => 'Examen En Ligne Final',
            'type' => 'exam',
            'modality' => 'online',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1.5,
            'status' => 'draft',
        ];

        $res2 = $this->actingAs($this->superAdmin)->post(route('evaluations.store'), $payloadDistance);
        $res2->assertRedirect();

        $eval2 = Evaluation::where('title', 'Examen En Ligne Final')->first();
        $this->assertNotNull($eval2);
        $this->assertSame('online', $eval2->modality);
        $this->assertSame('À distance', $eval2->modality_label);
    }

    public function test_super_admin_can_update_and_delete_evaluation(): void
    {
        $updatePayload = [
            'title' => 'Contrôle Algorithmes MAJ',
            'type' => 'control',
            'modality' => 'online',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'published',
        ];

        $res = $this->actingAs($this->superAdmin)->put(route('evaluations.update', $this->evaluationA1), $updatePayload);
        $res->assertRedirect(route('evaluations.show', $this->evaluationA1));
        $this->assertSame('online', $this->evaluationA1->fresh()->modality);
        $this->assertSame('Contrôle Algorithmes MAJ', $this->evaluationA1->fresh()->title);

        $delRes = $this->actingAs($this->superAdmin)->delete(route('evaluations.destroy', $this->evaluationA1));
        $delRes->assertRedirect(route('evaluations.index'));
        $this->assertNull(Evaluation::find($this->evaluationA1->id));
    }

    public function test_teacher_can_create_evaluation_only_for_assigned_class(): void
    {
        // Teacher 1 creates evaluation for class A1 (assigned)
        $validPayload = [
            'class_id' => $this->classA1->id,
            'title' => 'Évaluation PHP Prof 1',
            'type' => 'control',
            'modality' => 'online',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'draft',
        ];

        $res = $this->actingAs($this->teacherUser1)->post(route('evaluations.store'), $validPayload);
        $res->assertRedirect();

        $created = Evaluation::where('title', 'Évaluation PHP Prof 1')->first();
        $this->assertNotNull($created);
        $this->assertSame($this->teacher1->id, (int) $created->teacher_id);
        $this->assertSame('online', $created->modality);

        // Teacher 1 attempts to create evaluation for class A2 (unassigned)
        $invalidPayload = [
            'class_id' => $this->classA2->id,
            'title' => 'Évaluation Intrusion Prof 1',
            'type' => 'control',
            'modality' => 'in_person',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'draft',
        ];

        $resForbidden = $this->actingAs($this->teacherUser1)->post(route('evaluations.store'), $invalidPayload);
        $resForbidden->assertForbidden();
    }

    public function test_teacher_cannot_access_or_modify_other_teacher_evaluation(): void
    {
        // Teacher 2 owns an evaluation in class A2
        $evalTeacher2 = Evaluation::create([
            'center_id' => $this->centerA->id,
            'class_id' => $this->classA2->id,
            'formation_id' => $this->formationA->id,
            'teacher_id' => $this->teacher2->id,
            'title' => 'Évaluation Prof 2',
            'type' => 'exam',
            'modality' => 'in_person',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'published',
        ]);

        // Teacher 1 cannot view teacher 2's evaluation
        $this->actingAs($this->teacherUser1)->get(route('evaluations.show', $evalTeacher2))->assertForbidden();

        // Teacher 1 cannot edit teacher 2's evaluation
        $this->actingAs($this->teacherUser1)->get(route('evaluations.edit', $evalTeacher2))->assertForbidden();

        // Teacher 1 cannot update teacher 2's evaluation
        $this->actingAs($this->teacherUser1)->put(route('evaluations.update', $evalTeacher2), [
            'title' => 'Piratage',
            'type' => 'exam',
            'modality' => 'online',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'published',
        ])->assertForbidden();
    }

    public function test_teacher_can_grade_only_enrolled_students(): void
    {
        // Grading student A1 (enrolled in class A1) succeeds
        $res = $this->actingAs($this->teacherUser1)->post(route('evaluations.grades.save', $this->evaluationA1), [
            'grades' => [
                ['student_id' => $this->studentA1->id, 'grade' => 17.5, 'comment' => 'Très bon travail'],
            ],
        ]);
        $res->assertRedirect(route('evaluations.show', $this->evaluationA1));

        $grade = Grade::where('evaluation_id', $this->evaluationA1->id)->where('student_id', $this->studentA1->id)->first();
        $this->assertNotNull($grade);
        $this->assertEquals(17.5, $grade->grade);

        // Grading student A2 (enrolled in class A2, NOT in class A1) fails with 403
        $resForbidden = $this->actingAs($this->teacherUser1)->post(route('evaluations.grades.save', $this->evaluationA1), [
            'grades' => [
                ['student_id' => $this->studentA2->id, 'grade' => 15],
            ],
        ]);
        $resForbidden->assertForbidden();
    }

    public function test_center_admin_has_read_only_access(): void
    {
        // Center admin CAN view index and show
        $this->actingAs($this->centerAdminA)->get(route('evaluations.index'))->assertOk();
        $this->actingAs($this->centerAdminA)->get(route('evaluations.show', $this->evaluationA1))->assertOk();

        // Center admin CANNOT create
        $this->actingAs($this->centerAdminA)->get(route('evaluations.create'))->assertForbidden();
        $this->actingAs($this->centerAdminA)->post(route('evaluations.store'), [
            'class_id' => $this->classA1->id,
            'title' => 'Test Admin Creation',
            'type' => 'exam',
            'modality' => 'in_person',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'draft',
        ])->assertForbidden();

        // Center admin CANNOT edit, update, delete or grade
        $this->actingAs($this->centerAdminA)->get(route('evaluations.edit', $this->evaluationA1))->assertForbidden();
        $this->actingAs($this->centerAdminA)->put(route('evaluations.update', $this->evaluationA1), [
            'title' => 'Test Admin Edit',
            'type' => 'exam',
            'modality' => 'in_person',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'draft',
        ])->assertForbidden();
        $this->actingAs($this->centerAdminA)->delete(route('evaluations.destroy', $this->evaluationA1))->assertForbidden();
        $this->actingAs($this->centerAdminA)->post(route('evaluations.grades.save', $this->evaluationA1), [
            'grades' => [['student_id' => $this->studentA1->id, 'grade' => 10]],
        ])->assertForbidden();
    }

    public function test_secretary_has_read_only_access(): void
    {
        // Secretary CAN view index and show
        $this->actingAs($this->secretaryA)->get(route('evaluations.index'))->assertOk();
        $this->actingAs($this->secretaryA)->get(route('evaluations.show', $this->evaluationA1))->assertOk();

        // Secretary CANNOT create
        $this->actingAs($this->secretaryA)->get(route('evaluations.create'))->assertForbidden();
        $this->actingAs($this->secretaryA)->post(route('evaluations.store'), [
            'class_id' => $this->classA1->id,
            'title' => 'Test Secretary Creation',
            'type' => 'exam',
            'modality' => 'in_person',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'draft',
        ])->assertForbidden();

        // Secretary CANNOT edit, update, delete or grade
        $this->actingAs($this->secretaryA)->get(route('evaluations.edit', $this->evaluationA1))->assertForbidden();
        $this->actingAs($this->secretaryA)->put(route('evaluations.update', $this->evaluationA1), [
            'title' => 'Test Sec Edit',
            'type' => 'exam',
            'modality' => 'in_person',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'draft',
        ])->assertForbidden();
        $this->actingAs($this->secretaryA)->delete(route('evaluations.destroy', $this->evaluationA1))->assertForbidden();
        $this->actingAs($this->secretaryA)->post(route('evaluations.grades.save', $this->evaluationA1), [
            'grades' => [['student_id' => $this->studentA1->id, 'grade' => 10]],
        ])->assertForbidden();
    }

    public function test_modality_displayed_in_index_and_filtered_correctly(): void
    {
        $evalOnline = Evaluation::create([
            'center_id' => $this->centerA->id,
            'class_id' => $this->classA1->id,
            'formation_id' => $this->formationA->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Projet Distance Web',
            'type' => 'project',
            'modality' => 'online',
            'evaluation_date' => now()->toDateString(),
            'max_grade' => 20,
            'coefficient' => 2,
            'status' => 'published',
        ]);

        // Index shows column header Modalité and values
        $response = $this->actingAs($this->teacherUser1)->get(route('evaluations.index'));
        $response->assertOk()
            ->assertSee('Modalité')
            ->assertSee('Présentiel')
            ->assertSee('À distance')
            ->assertSee('Contrôle Algorithmes')
            ->assertSee('Projet Distance Web');

        // Filter by in_person
        $resInPerson = $this->actingAs($this->teacherUser1)->get(route('evaluations.index', ['modality' => 'in_person']));
        $resInPerson->assertOk()
            ->assertSee('Contrôle Algorithmes')
            ->assertDontSee('Projet Distance Web');

        // Filter by online
        $resOnline = $this->actingAs($this->teacherUser1)->get(route('evaluations.index', ['modality' => 'online']));
        $resOnline->assertOk()
            ->assertSee('Projet Distance Web')
            ->assertDontSee('Contrôle Algorithmes');

        // Show view displays modality
        $showRes = $this->actingAs($this->teacherUser1)->get(route('evaluations.show', $evalOnline));
        $showRes->assertOk()->assertSee('À distance');
    }
}
