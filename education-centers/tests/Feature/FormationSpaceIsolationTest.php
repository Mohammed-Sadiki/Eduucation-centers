<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Certificate;
use App\Models\ClassRoom;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Formation;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationSpaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $centerAdmin;
    private Center $center;
    private Teacher $teacher;
    private Formation $formationMaconnerie;
    private Formation $formationInformatique;

    protected function setUp(): void
    {
        parent::setUp();

        $this->center = Center::factory()->create([
            'name' => 'Centre Agadir',
            'code' => 'AGA',
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'center_id' => null,
        ]);

        $this->centerAdmin = User::factory()->create([
            'role' => 'center_admin',
            'center_id' => $this->center->id,
        ]);

        $this->teacher = Teacher::factory()->create([
            'center_id' => $this->center->id,
        ]);

        $this->formationMaconnerie = Formation::factory()->create([
            'center_id' => $this->center->id,
            'name' => 'Maçonnerie Générale',
            'domain' => 'maconnerie',
            'official_code' => 'maconnerie',
            'is_official' => true,
            'status' => 'active',
        ]);

        $this->formationInformatique = Formation::factory()->create([
            'center_id' => $this->center->id,
            'name' => 'Informatique et Réseaux',
            'domain' => null,
            'official_code' => null,
            'is_official' => true,
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Visiter le dashboard d'une formation active positionne le contexte de session
     * et affiche le nom dynamique de la formation.
     */
    public function test_visiting_formation_dashboard_sets_session_and_renders_dynamically(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('formations-generales.show', $this->formationInformatique));

        $response->assertOk();
        $response->assertSessionHas('active_formation_id', $this->formationInformatique->id);
        $response->assertSee('Informatique et Réseaux');
    }

    /**
     * Test 2: Isolation des classes entre formations.
     * Les classes de Maçonnerie ne doivent pas apparaître dans l'espace Informatique.
     */
    public function test_classes_are_isolated_by_active_formation(): void
    {
        $classMaconnerie = ClassRoom::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationMaconnerie->id,
            'name' => 'Groupe Maçonnerie A',
            'code' => 'MAC-A',
            'status' => 'active',
        ]);

        $classInformatique = ClassRoom::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationInformatique->id,
            'name' => 'Groupe Informatique 1',
            'code' => 'INFO-1',
            'status' => 'active',
        ]);

        // Simuler la navigation dans l'espace Informatique
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_formation_id' => $this->formationInformatique->id])
            ->get(route('classes.index'));

        $response->assertOk();
        $response->assertSee('Groupe Informatique 1');
        $response->assertDontSee('Groupe Maçonnerie A');
    }

    /**
     * Test 3: Isolation des inscriptions entre formations.
     */
    public function test_enrollments_are_isolated_by_active_formation(): void
    {
        $student1 = Student::factory()->create(['center_id' => $this->center->id]);
        $student2 = Student::factory()->create(['center_id' => $this->center->id]);

        $enrollmentMaconnerie = Enrollment::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationMaconnerie->id,
            'student_id' => $student1->id,
            'status' => 'accepted',
        ]);

        $enrollmentInformatique = Enrollment::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationInformatique->id,
            'student_id' => $student2->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_formation_id' => $this->formationInformatique->id])
            ->get(route('enrollments.index'));

        $response->assertOk();
        $response->assertSee($student2->first_name);
        $response->assertDontSee($student1->first_name);
    }

    /**
     * Test 4: Isolation des emplois du temps (schedules) et présences (attendances).
     */
    public function test_schedules_and_attendances_are_isolated_by_active_formation(): void
    {
        $classMaconnerie = ClassRoom::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationMaconnerie->id,
        ]);

        $classInformatique = ClassRoom::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationInformatique->id,
        ]);

        $room = Room::create([
            'center_id' => $this->center->id,
            'name' => 'Salle Standard',
            'code' => 'ROOM-STD',
            'status' => 'active',
            'capacity' => 20,
        ]);

        $scheduleMaconnerie = Schedule::create([
            'center_id' => $this->center->id,
            'teacher_id' => $this->teacher->id,
            'class_id' => $classMaconnerie->id,
            'room_id' => $room->id,
            'title' => 'Cours Maçonnerie Brique',
            'date' => '2026-11-10',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'type' => 'course',
            'status' => 'scheduled',
        ]);

        $scheduleInformatique = Schedule::create([
            'center_id' => $this->center->id,
            'teacher_id' => $this->teacher->id,
            'class_id' => $classInformatique->id,
            'room_id' => $room->id,
            'title' => 'Cours Algorithmique',
            'date' => '2026-11-10',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'type' => 'course',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_formation_id' => $this->formationInformatique->id])
            ->get(route('schedules.index'));

        $response->assertOk();
        $response->assertSee('Cours Algorithmique');
        $response->assertDontSee('Cours Maçonnerie Brique');
    }

    /**
     * Test 5: Isolation des évaluations et certificats.
     */
    public function test_evaluations_and_certificates_are_isolated_by_active_formation(): void
    {
        $classMaconnerie = ClassRoom::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationMaconnerie->id,
        ]);

        $classInformatique = ClassRoom::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationInformatique->id,
        ]);

        $evalMaconnerie = Evaluation::create([
            'center_id' => $this->center->id,
            'class_id' => $classMaconnerie->id,
            'formation_id' => $this->formationMaconnerie->id,
            'title' => 'Examen Pratique Maçonnerie',
            'type' => 'exam',
            'evaluation_date' => '2026-10-10',
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'published',
        ]);

        $evalInformatique = Evaluation::create([
            'center_id' => $this->center->id,
            'class_id' => $classInformatique->id,
            'formation_id' => $this->formationInformatique->id,
            'title' => 'Examen Programmation Web',
            'type' => 'exam',
            'evaluation_date' => '2026-10-10',
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_formation_id' => $this->formationInformatique->id])
            ->get(route('evaluations.index'));

        $response->assertOk();
        $response->assertSee('Examen Programmation Web');
        $response->assertDontSee('Examen Pratique Maçonnerie');
    }

    /**
     * Test 6: Isolation des salles (rooms) selon les activités de la formation.
     */
    public function test_rooms_are_isolated_to_formations_with_activities_in_active_context(): void
    {
        $roomMaconnerie = Room::create([
            'center_id' => $this->center->id,
            'name' => 'Atelier Maçonnerie 1',
            'code' => 'MAC-ROOM-1',
            'status' => 'active',
            'capacity' => 25,
        ]);

        $roomInformatique = Room::create([
            'center_id' => $this->center->id,
            'name' => 'Labo Informatique 2',
            'code' => 'INFO-ROOM-2',
            'status' => 'active',
            'capacity' => 30,
        ]);

        $classMaconnerie = ClassRoom::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationMaconnerie->id,
        ]);

        $classInformatique = ClassRoom::factory()->create([
            'center_id' => $this->center->id,
            'formation_id' => $this->formationInformatique->id,
        ]);

        Schedule::create([
            'center_id' => $this->center->id,
            'teacher_id' => $this->teacher->id,
            'class_id' => $classInformatique->id,
            'room_id' => $roomInformatique->id,
            'title' => 'Cours Info',
            'date' => '2026-11-10',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'type' => 'course',
            'status' => 'scheduled',
        ]);

        Schedule::create([
            'center_id' => $this->center->id,
            'teacher_id' => $this->teacher->id,
            'class_id' => $classMaconnerie->id,
            'room_id' => $roomMaconnerie->id,
            'title' => 'Cours Maco',
            'date' => '2026-11-10',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'type' => 'course',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_formation_id' => $this->formationInformatique->id])
            ->get(route('rooms.index'));

        $response->assertOk();
        $response->assertSee('Labo Informatique 2');
        $response->assertDontSee('Atelier Maçonnerie 1');
    }

    /**
     * Test 7: Une nouvelle formation sans aucune donnée affiche des listes vides / compteurs à zéro.
     */
    public function test_empty_formation_displays_empty_lists(): void
    {
        $emptyFormation = Formation::factory()->create([
            'center_id' => $this->center->id,
            'name' => 'Nouvelle Formation Vide',
            'is_official' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_formation_id' => $emptyFormation->id])
            ->get(route('classes.index'));

        $response->assertOk();
        // Ne doit afficher aucune des classes d'autres formations
        $response->assertDontSee('Groupe Maçonnerie A');
        $response->assertDontSee('Groupe Informatique 1');
    }

    /**
     * Test 8: Sortir de l'espace formation (page accueil ou sélection) réinitialise la session.
     */
    public function test_leaving_formation_space_clears_session(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_formation_id' => $this->formationInformatique->id])
            ->get(route('formations-generales.index'));

        $response->assertOk();
        $response->assertSessionMissing('active_formation_id');
    }

    /**
     * Test 9: Accès non autorisé (cross-center) via manipulation d'URL est bloqué.
     */
    public function test_cross_center_access_is_blocked_for_center_admin(): void
    {
        $otherCenter = Center::factory()->create(['name' => 'Centre Autre', 'code' => 'AUT']);
        $otherFormation = Formation::factory()->create([
            'center_id' => $otherCenter->id,
            'name' => 'Formation Autre Centre',
            'is_official' => true,
            'status' => 'active',
        ]);

        // CenterAdmin d'Agadir tente d'accéder au dashboard de la formation du Centre Autre
        $response = $this->actingAs($this->centerAdmin)
            ->get(route('formations-generales.show', $otherFormation));

        // Doit être 403 Forbidden
        $response->assertForbidden();
    }
}
