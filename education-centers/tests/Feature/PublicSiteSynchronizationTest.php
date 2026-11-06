<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use App\Support\AcademicPerimeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerTanger;
    protected Center $centerEssaouira;
    protected Center $centerAgadir;
    protected Center $centerTetouane;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerTanger = Center::create([
            'name' => 'Centre Tanger',
            'code' => 'TNG',
            'city' => 'Tanger',
            'status' => 'active',
        ]);

        $this->centerEssaouira = Center::create([
            'name' => 'Centre Essaouira',
            'code' => 'ESS',
            'city' => 'Essaouira',
            'status' => 'active',
        ]);

        $this->centerAgadir = Center::create([
            'name' => 'Centre Agadir',
            'code' => 'AGA',
            'city' => 'Agadir',
            'status' => 'active',
        ]);

        $this->centerTetouane = Center::create([
            'name' => 'Centre Tétouane',
            'code' => 'TET',
            'city' => 'Tétouane',
            'status' => 'active',
        ]);
    }

    /**
     * 1. Les 4 centres affichent le nombre réel d'étudiants inscrits provenant de la DB.
     */
    public function test_public_home_displays_real_student_counts_for_each_center(): void
    {
        // 3 étudiants à Tanger
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['center_id' => $this->centerTanger->id, 'role' => User::ROLE_STUDENT]);
            Student::factory()->create(['user_id' => $user->id, 'center_id' => $this->centerTanger->id, 'status' => 'active']);
        }

        // 2 étudiants à Essaouira
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create(['center_id' => $this->centerEssaouira->id, 'role' => User::ROLE_STUDENT]);
            Student::factory()->create(['user_id' => $user->id, 'center_id' => $this->centerEssaouira->id, 'status' => 'active']);
        }

        // 0 étudiant à Agadir et Tétouane

        $response = $this->get(route('home'));
        $response->assertOk();

        $centers = $response->viewData('centers');
        $this->assertCount(4, $centers);

        $tanger = $centers->firstWhere('id', $this->centerTanger->id);
        $this->assertEquals(3, $tanger->students_count);

        $essaouira = $centers->firstWhere('id', $this->centerEssaouira->id);
        $this->assertEquals(2, $essaouira->students_count);

        $agadir = $centers->firstWhere('id', $this->centerAgadir->id);
        $this->assertEquals(0, $agadir->students_count);

        $tetouane = $centers->firstWhere('id', $this->centerTetouane->id);
        $this->assertEquals(0, $tetouane->students_count);

        // Statistique globale dans le bandeau
        $stats = $response->viewData('stats');
        $this->assertEquals(4, $stats['centers']);
        $this->assertEquals(5, $stats['students']);
    }

    /**
     * 2. Le nombre de formations affiché correspond exactement aux formations disponibles en DB.
     */
    public function test_formations_count_matches_real_database_formations(): void
    {
        // Créer 2 formations officielles
        Formation::create([
            'center_id' => $this->centerTanger->id,
            'name' => 'Santé Tanger',
            'code' => 'OF-TNG-SANTE',
            'domain' => 'sante',
            'is_official' => true,
            'status' => 'active',
        ]);

        Formation::create([
            'center_id' => $this->centerEssaouira->id,
            'name' => 'Informatique Essaouira',
            'code' => 'OF-ESS-INFO',
            'domain' => 'informatique',
            'is_official' => true,
            'status' => 'active',
        ]);

        $response = $this->get(route('home'));
        $response->assertOk();

        $stats = $response->viewData('stats');
        $this->assertEquals(2, $stats['formations']);
    }

    /**
     * 3. Formations populaires : uniquement les formations réelles en base de données.
     */
    public function test_popular_formations_only_display_real_existing_formations(): void
    {
        $f1 = Formation::create([
            'center_id' => $this->centerTanger->id,
            'name' => 'Santé Tanger Populaire',
            'code' => 'OF-TNG-SANTE',
            'domain' => 'sante',
            'is_official' => true,
            'status' => 'active',
        ]);

        $f2 = Formation::create([
            'center_id' => $this->centerEssaouira->id,
            'name' => 'Informatique Essaouira Populaire',
            'code' => 'OF-ESS-INFO',
            'domain' => 'informatique',
            'is_official' => true,
            'status' => 'active',
        ]);

        $response = $this->get(route('home'));
        $response->assertOk();

        $formations = $response->viewData('formations');
        $this->assertNotEmpty($formations);

        foreach ($formations as $formation) {
            $this->assertInstanceOf(Formation::class, $formation);
            $this->assertDatabaseHas('formations', [
                'id' => $formation->id,
                'name' => $formation->name,
                'status' => 'active',
            ]);
        }

        $response->assertSee('Santé Tanger Populaire');
        $response->assertSee('Informatique Essaouira Populaire');
    }

    /**
     * 4. Synchronisation automatique : toute nouvelle formation ajoutée apparaît automatiquement sur le site public.
     */
    public function test_newly_added_formation_appears_automatically_on_public_site(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'center_id' => null,
        ]);

        // Ajout d'une nouvelle formation générale personnalisée (comme fait depuis le dashboard)
        $newFormationData = [
            'center_id' => $this->centerTanger->id,
            'name' => 'Intelligence Artificielle & Robotique',
            'annual_price' => 18000,
            'currency' => 'MAD',
            'status' => 'active',
            'duration_hours' => 300,
            'duration_weeks' => 30,
        ];

        $this->actingAs($superAdmin)
            ->post(route('formations-generales.store'), $newFormationData)
            ->assertRedirect(route('formations-generales.index', ['center_id' => $this->centerTanger->id]));

        // Vérification immédiate sur le site public sans aucune modification manuelle
        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertSee('Intelligence Artificielle & Robotique');

        // Vérification sur la page catalogue /offres-formations
        $catalogue = $this->get(route('public.formations'));
        $catalogue->assertOk();
        $catalogue->assertSee('Intelligence Artificielle & Robotique');

        // Vérification sur la page du centre /centres/{center}
        $centerPage = $this->get(route('public.centers.show', $this->centerTanger));
        $centerPage->assertOk();
        $centerPage->assertSee('Intelligence Artificielle & Robotique');
    }
}
