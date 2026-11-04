<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 — Portail Admin Centre piloté par domaine (réutilisation du module
 * Super Admin, restreint au centre) + isolation stricte des données.
 *
 * Les domaines forment un catalogue COMMUN : les six domaines sont disponibles
 * pour chaque centre, même sans formation. L'isolation porte sur les DONNÉES
 * (forçage du center_id), jamais sur le catalogue.
 *
 * Vérifie :
 *  - l'Admin Centre voit les six domaines communs sur son portail ;
 *  - un domaine sans formation dans son centre reste accessible (état vide) ;
 *  - le dashboard domaine ne montre que les données de son centre + domaine ;
 *  - le Super Admin garde l'accès à tous les domaines.
 */
class CenterAdminDomainPortalTest extends TestCase
{
    use RefreshDatabase;

    private Center $centerA;

    private Center $centerB;

    private User $adminA;

    private User $superAdmin;

    private Formation $formationSanteA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerA = Center::create(['name' => 'Centre A', 'code' => 'CA', 'address' => 'A', 'status' => 'active']);
        $this->centerB = Center::create(['name' => 'Centre B', 'code' => 'CB', 'address' => 'B', 'status' => 'active']);

        $this->adminA = User::factory()->centerAdmin($this->centerA->id)->create();
        $this->superAdmin = User::factory()->superAdmin()->create();

        // Centre A propose uniquement « sante » et « informatique ».
        $this->formationSanteA = Formation::create([
            'center_id' => $this->centerA->id, 'domain' => 'sante',
            'name' => 'Infirmier A', 'code' => 'INF-A', 'status' => 'active',
        ]);
        Formation::create([
            'center_id' => $this->centerA->id, 'domain' => 'informatique',
            'name' => 'Dev A', 'code' => 'DEV-A', 'status' => 'active',
        ]);
        // Centre B propose « hotellerie » (que le Centre A n'a pas).
        $formationHotelB = Formation::create([
            'center_id' => $this->centerB->id, 'domain' => 'hotellerie',
            'name' => 'Hotel B', 'code' => 'HOT-B', 'status' => 'active',
        ]);

        // Étudiant Centre A / sante.
        $classA = ClassRoom::create([
            'center_id' => $this->centerA->id, 'formation_id' => $this->formationSanteA->id,
            'name' => 'Classe A', 'code' => 'CA-1', 'capacity' => 30, 'status' => 'active',
        ]);
        $this->makeStudent($this->centerA, 'S-A-1', $this->formationSanteA->id, $classA->id);

        // Étudiant Centre B / hotellerie (étranger au Centre A).
        $classB = ClassRoom::create([
            'center_id' => $this->centerB->id, 'formation_id' => $formationHotelB->id,
            'name' => 'Classe B', 'code' => 'CB-1', 'capacity' => 30, 'status' => 'active',
        ]);
        $this->makeStudent($this->centerB, 'S-B-1', $formationHotelB->id, $classB->id);
    }

    private function makeStudent(Center $center, string $number, int $formationId, int $classId): Student
    {
        $user = User::factory()->create(['role' => 'student', 'center_id' => $center->id]);
        $student = Student::create([
            'user_id' => $user->id, 'center_id' => $center->id, 'student_number' => $number,
            'first_name' => 'Eleve', 'last_name' => 'X', 'date_of_birth' => '2000-01-01',
            'gender' => 'male', 'status' => 'active',
        ]);
        Enrollment::create([
            'center_id' => $center->id, 'student_id' => $student->id,
            'formation_id' => $formationId, 'class_id' => $classId,
            'registration_number' => 'E-'.$number, 'application_date' => '2026-01-01', 'status' => 'accepted',
        ]);

        return $student;
    }

    public function test_center_admin_sees_all_six_common_domains_on_picker(): void
    {
        $domains = $this->actingAs($this->adminA)
            ->get(route('center-admin.domaines'))
            ->assertOk()
            ->viewData('domains');

        $slugs = collect($domains)->pluck('slug')->all();

        sort($slugs);

        // Catalogue commun : les six domaines sont disponibles pour chaque centre,
        // y compris ceux où le centre n'a encore aucune formation (ex : hotellerie).
        $this->assertSame([
            'design-graphique',
            'design-interieur',
            'hotellerie',
            'informatique',
            'maconnerie',
            'sante',
        ], $slugs);
    }

    public function test_center_admin_domain_dashboard_ok_for_own_domain(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('center-admin.domain.dashboard', 'sante'))
            ->assertOk()
            ->assertSee('Santé');
    }

    public function test_center_admin_can_open_common_domain_without_formation_shows_empty_state(): void
    {
        // « hotellerie » est un domaine commun : accessible au Centre A même sans
        // formation, avec un état vide (aucune donnée du Centre B fuite).
        $response = $this->actingAs($this->adminA)
            ->get(route('center-admin.domain.dashboard', 'hotellerie'))
            ->assertOk()
            ->assertSee('Aucune formation générale pour le moment');

        $stats = $response->viewData('stats');
        // Aucune formation/étudiant hotellerie dans le Centre A => périmètre vide.
        $this->assertSame(0, $stats['formations']);
        $this->assertSame(0, $stats['students']);
    }

    public function test_center_admin_dashboard_stats_are_center_and_domain_scoped(): void
    {
        $stats = $this->actingAs($this->adminA)
            ->get(route('center-admin.domain.dashboard', 'sante'))
            ->viewData('stats');

        // Un seul étudiant : celui du Centre A / sante.
        $this->assertSame(1, $stats['students']);
        $this->assertSame(1, $stats['enrollments']);
    }

    public function test_center_admin_list_is_isolated_from_other_center(): void
    {
        $numbers = $this->actingAs($this->adminA)
            ->withSession(['active_domain' => 'sante'])
            ->get(route('students.index'))
            ->viewData('students')
            ->pluck('student_number')
            ->all();

        $this->assertContains('S-A-1', $numbers);
        $this->assertNotContains('S-B-1', $numbers);
    }

    public function test_super_admin_still_sees_all_center_domains(): void
    {
        // Le Super Admin conserve l'accès au domaine hotellerie (Centre B).
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'hotellerie'))
            ->assertOk()
            ->assertSee('Hôtellerie');
    }
}
