<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PlatformStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformStructureSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Vérification des effectifs exacts après exécution du seeder.
     */
    public function test_platform_structure_seeder_creates_exact_counts(): void
    {
        $this->seed(PlatformStructureSeeder::class);

        // 4 centres
        $this->assertEquals(4, Center::count());
        $this->assertEquals(4, Center::where('status', 'active')->count());

        // 1 Super Admin
        $this->assertEquals(1, User::where('role', User::ROLE_SUPER_ADMIN)->count());
        $superAdmin = User::where('role', User::ROLE_SUPER_ADMIN)->first();
        $this->assertNull($superAdmin->center_id);
        $this->assertTrue(Hash::check(PlatformStructureSeeder::DEFAULT_PASSWORD, $superAdmin->password));

        // 4 Admin Centre (1 par centre)
        $this->assertEquals(4, User::where('role', User::ROLE_CENTER_ADMIN)->count());
        foreach (Center::all() as $center) {
            $this->assertEquals(1, User::where('role', User::ROLE_CENTER_ADMIN)->where('center_id', $center->id)->count(), "Center {$center->name} must have exactly 1 admin.");
        }

        // 4 Secrétaires (1 par centre)
        $this->assertEquals(4, User::where('role', User::ROLE_SECRETARY)->count());
        foreach (Center::all() as $center) {
            $this->assertEquals(1, User::where('role', User::ROLE_SECRETARY)->where('center_id', $center->id)->count(), "Center {$center->name} must have exactly 1 secretary.");
        }

        // 24 formations au total (exactement 6 par centre)
        $this->assertEquals(24, Formation::where('is_official', true)->count());
        foreach (Center::all() as $center) {
            $this->assertEquals(6, Formation::where('center_id', $center->id)->where('is_official', true)->count(), "Center {$center->name} must have exactly 6 official formations.");
        }

        // Au minimum 6 professeurs par centre, chacun lié à son centre et à sa formation
        $this->assertGreaterThanOrEqual(24, Teacher::count());
        foreach (Center::all() as $center) {
            $teachers = Teacher::where('center_id', $center->id)->get();
            $this->assertGreaterThanOrEqual(6, $teachers->count(), "Center {$center->name} must have at least 6 teachers.");

            foreach ($teachers as $teacher) {
                $this->assertEquals($center->id, $teacher->center_id);
                $this->assertNotNull($teacher->user_id);
                // Le compte User correspondant est bien rattaché au même centre
                $this->assertEquals($center->id, $teacher->user->center_id);
                $this->assertEquals(User::ROLE_TEACHER, $teacher->user->role);
            }
        }

        // Vérification de l'unicité stricte des 24 noms et prénoms (aucun doublon)
        $fullNames = Teacher::all()->map(fn ($t) => trim($t->first_name . ' ' . $t->last_name));
        $this->assertEquals(24, $fullNames->unique()->count(), "Les 24 professeurs doivent tous avoir des noms et prénoms uniques sans aucun doublon.");

        // Chaque formation officielle a un professeur responsable assigné
        foreach (Formation::where('is_official', true)->get() as $formation) {
            $this->assertNotNull($formation->teacher_id, "Formation {$formation->name} in center {$formation->center_id} must have a responsible teacher.");
            $this->assertEquals($formation->center_id, $formation->teacher->center_id);
        }
    }

    /**
     * 2. Idempotence : relancer le seeder ne crée pas de doublons.
     */
    public function test_platform_structure_seeder_is_strictly_idempotent(): void
    {
        // Première exécution
        $this->seed(PlatformStructureSeeder::class);

        // Seconde exécution consécutive
        $this->seed(PlatformStructureSeeder::class);

        // Nombres strictement identiques
        $this->assertEquals(4, Center::count());
        $this->assertEquals(1, User::where('role', User::ROLE_SUPER_ADMIN)->count());
        $this->assertEquals(4, User::where('role', User::ROLE_CENTER_ADMIN)->count());
        $this->assertEquals(4, User::where('role', User::ROLE_SECRETARY)->count());
        $this->assertEquals(24, Formation::where('is_official', true)->count());
        $this->assertEquals(24, Teacher::count());
    }

    /**
     * 3. Connexion de tous les comptes créés avec le mot de passe initial.
     */
    public function test_all_canonical_accounts_can_authenticate(): void
    {
        $this->seed(PlatformStructureSeeder::class);

        $accounts = [
            'admin@education-centers.de',
            'admin.tanger@education-centers.ma',
            'admin.essaouira@education-centers.ma',
            'admin.agadir@education-centers.ma',
            'admin.tetouane@education-centers.ma',
            'secretaire.tanger@education-centers.test',
            'secretaire.essaouira@education-centers.test',
            'secretaire.agadir@education-centers.test',
            'secretaire.tetouane@education-centers.test',
        ];

        foreach ($accounts as $email) {
            $response = $this->post(route('login'), [
                'email' => $email,
                'password' => PlatformStructureSeeder::DEFAULT_PASSWORD,
            ]);

            $response->assertSessionHasNoErrors();
            $this->assertAuthenticated();

            // Déconnexion avant le compte suivant
            $this->post(route('logout'));
            $this->assertGuest();
        }
    }
}

