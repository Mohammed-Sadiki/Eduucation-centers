<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use App\Services\DomainDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationSyncSelectAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $adminAgadir;
    private User $adminEssaouira;
    private User $secretaryAgadir;
    private Center $centerAgadir;
    private Center $centerEssaouira;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerAgadir = Center::factory()->create([
            'name' => 'Centre Agadir',
            'code' => 'AGA',
        ]);

        $this->centerEssaouira = Center::factory()->create([
            'name' => 'Centre Essaouira',
            'code' => 'ESS',
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'center_id' => null,
        ]);

        $this->adminAgadir = User::factory()->create([
            'role' => 'center_admin',
            'center_id' => $this->centerAgadir->id,
        ]);

        $this->adminEssaouira = User::factory()->create([
            'role' => 'center_admin',
            'center_id' => $this->centerEssaouira->id,
        ]);

        $this->secretaryAgadir = User::factory()->create([
            'role' => 'secretary',
            'center_id' => $this->centerAgadir->id,
        ]);
    }

    /**
     * Test 1, 2 & 3 :
     * 1. Créer une nouvelle formation Informatique.
     * 2. Ouvrir le formulaire Ajouter un étudiant et vérifier qu'Informatique apparaît dans le Select.
     * 3. Vérifier que la formation apparaît uniquement dans les centres autorisés (Agadir vs Essaouira).
     */
    public function test_newly_created_formation_appears_in_student_select_for_authorized_center(): void
    {
        // 1. Super Admin crée une nouvelle formation Informatique pour Agadir
        $response = $this->actingAs($this->superAdmin)->post(route('formations-generales.store'), [
            'center_id' => $this->centerAgadir->id,
            'name' => 'Informatique et Réseaux',
            'annual_price' => 8000,
            'status' => 'active',
        ]);
        $response->assertRedirect();

        $formation = Formation::where('name', 'Informatique et Réseaux')->first();
        $this->assertNotNull($formation);
        $this->assertSame($this->centerAgadir->id, $formation->center_id);

        // 2 & 3. Ouvrir le formulaire Ajouter un étudiant pour Agadir (Secrétaire Agadir)
        $responseAgadir = $this->actingAs($this->secretaryAgadir)
            ->get(route('students.create'));
        $responseAgadir->assertOk();
        $responseAgadir->assertSee('Informatique et Réseaux');

        // Super Admin voit la formation dans les données disponibles
        $responseSuperAdmin = $this->actingAs($this->superAdmin)
            ->get(route('students.create'));
        $responseSuperAdmin->assertOk();
        $responseSuperAdmin->assertSee('Informatique et Réseaux');

        // Secrétaire Essaouira ne doit PAS voir la formation d'Agadir
        $secretaryEssaouira = User::factory()->create([
            'role' => 'secretary',
            'center_id' => $this->centerEssaouira->id,
        ]);
        $responseEssaouira = $this->actingAs($secretaryEssaouira)
            ->get(route('students.create'));
        $responseEssaouira->assertOk();
        $responseEssaouira->assertDontSee('Informatique et Réseaux');
    }

    /**
     * Test : Formation créée pour « Tous les centres » apparaît dans tous les centres.
     */
    public function test_formation_for_all_centers_appears_in_all_center_selects(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('formations-generales.store'), [
            'center_id' => 'all',
            'name' => 'Management de Projets',
            'annual_price' => 6500,
            'status' => 'active',
        ]);
        $response->assertRedirect();

        $formation = Formation::where('name', 'Management de Projets')->first();
        $this->assertNotNull($formation);
        $this->assertNull($formation->center_id);

        // Visible dans le formulaire pour Agadir
        $this->actingAs($this->secretaryAgadir)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('Management de Projets');

        // Visible dans le formulaire pour Essaouira
        $secretaryEssaouira = User::factory()->create([
            'role' => 'secretary',
            'center_id' => $this->centerEssaouira->id,
        ]);
        $this->actingAs($secretaryEssaouira)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('Management de Projets');

        // Visible dans le formulaire de création d'inscription (Secrétaire Agadir)
        $this->actingAs($this->secretaryAgadir)
            ->get(route('enrollments.create'))
            ->assertOk()
            ->assertSee('Management de Projets');
    }

    /**
     * Test 4, 5 & 6 :
     * 4. Inscrire un étudiant à cette formation.
     * 5. Vérifier que l'étudiant apparaît dans le Dashboard Informatique.
     * 6. Vérifier que le compteur Étudiants passe de 0 à 1.
     */
    public function test_student_enrollment_links_to_formation_and_increments_dashboard_stats(): void
    {
        // 1. Création de la formation Informatique
        $this->actingAs($this->superAdmin)->post(route('formations-generales.store'), [
            'center_id' => $this->centerAgadir->id,
            'name' => 'Informatique Avancée',
            'annual_price' => 7000,
            'status' => 'active',
        ]);
        $formation = Formation::where('name', 'Informatique Avancée')->sole();

        // Vérifier les stats initiales (0 étudiants)
        $dashboardService = app(DomainDashboardData::class);
        $initialData = $dashboardService->buildForFormation($formation);
        $this->assertSame(0, $initialData['stats']['students']);
        $this->assertSame(0, $initialData['stats']['enrollments']);

        // 4. Inscrire un étudiant à cette formation via le formulaire d'étudiant
        $studentData = [
            'first_name' => 'Karim',
            'last_name' => 'El Idrissi',
            'center_id' => $this->centerAgadir->id,
            'formation_id' => $formation->id,
            'status' => 'active',
        ];

        $res = $this->actingAs($this->superAdmin)->post(route('students.store'), $studentData);
        $res->assertRedirect();

        $student = Student::where('first_name', 'Karim')->where('last_name', 'El Idrissi')->sole();
        $this->assertSame($this->centerAgadir->id, $student->center_id);

        $enrollment = Enrollment::where('student_id', $student->id)->sole();
        $this->assertSame($formation->id, $enrollment->formation_id);
        $this->assertSame($this->centerAgadir->id, $enrollment->center_id);

        // 5 & 6. Dashboard de la formation : compteur passe de 0 à 1, étudiant visible
        $dashboardData = $dashboardService->buildForFormation($formation);
        $this->assertSame(1, $dashboardData['stats']['students']);
        $this->assertSame(1, $dashboardData['stats']['enrollments']);

        $viewResponse = $this->actingAs($this->adminAgadir)
            ->get(route('formations-generales.show', $formation));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Informatique Avancée');
        $viewResponse->assertSee('El Idrissi');
        $viewResponse->assertSee('Karim');
    }

    /**
     * Test 7 :
     * 7. Vérifier que les anciennes formations continuent à fonctionner.
     */
    public function test_existing_official_and_custom_formations_continue_to_work(): void
    {
        // Créer une formation classique
        $legacyFormation = Formation::factory()->create([
            'center_id' => $this->centerAgadir->id,
            'domain' => 'sante',
            'is_official' => true,
            'official_code' => 'sante',
            'name' => 'Santé et Soins',
            'code' => 'SANTE-01',
            'status' => 'active',
        ]);

        $selection = Formation::selectionForCenters([$this->centerAgadir->id]);
        $ids = array_column($selection, 'id');
        $this->assertContains($legacyFormation->id, $ids);

        // L'inscrire à une formation existante fonctionne
        $res = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'first_name' => 'Nadia',
            'last_name' => 'Bennani',
            'center_id' => $this->centerAgadir->id,
            'formation_id' => $legacyFormation->id,
            'status' => 'active',
        ]);
        $res->assertRedirect();

        $student = Student::where('first_name', 'Nadia')->sole();
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'formation_id' => $legacyFormation->id,
        ]);
    }
}
