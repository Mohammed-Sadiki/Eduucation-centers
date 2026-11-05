<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\Demo\DemoAccountFixtures;
use Database\Seeders\Demo\DemoSynchronizedAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoAccountsSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_seeder_seeds_all_fixtures_without_errors(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        $expectedFixtures = DemoAccountFixtures::allSynchronizedAccountFixtures();
        $this->assertCount(63, $expectedFixtures);

        foreach ($expectedFixtures as $fixture) {
            $user = User::query()->where('email', $fixture['email'])->first();

            $this->assertNotNull($user, "L'utilisateur [{$fixture['email']}] devrait exister dans la base.");
            $this->assertSame($fixture['role'], $user->role);

            if ($fixture['center_code'] !== null) {
                $this->assertNotNull($user->center, "Le centre pour [{$fixture['email']}] devrait exister.");
                $this->assertSame($fixture['center_code'], $user->center->code);
            } else {
                $this->assertNull($user->center_id);
            }
        }
    }

    public function test_coverage_of_all_roles_centers_and_domains(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        // 1. Couverture des rôles
        $rolesInDb = User::query()->distinct()->pluck('role')->all();
        $this->assertContains(User::ROLE_SUPER_ADMIN, $rolesInDb);
        $this->assertContains(User::ROLE_CENTER_ADMIN, $rolesInDb);
        $this->assertContains(User::ROLE_SECRETARY, $rolesInDb);
        $this->assertContains(User::ROLE_TEACHER, $rolesInDb);

        // 2. Couverture des centres
        $centerCodes = Center::query()->pluck('code')->all();
        $this->assertContains('TANGER', $centerCodes);
        $this->assertContains('ESSAOUIRA', $centerCodes);
        $this->assertContains('AGADIR', $centerCodes);
        $this->assertContains('TETOUANE', $centerCodes);

        foreach (['TANGER', 'ESSAOUIRA', 'AGADIR', 'TETOUANE'] as $code) {
            $center = Center::where('code', $code)->firstOrFail();
            $this->assertTrue(User::where('center_id', $center->id)->where('role', User::ROLE_CENTER_ADMIN)->exists());
            $this->assertTrue(User::where('center_id', $center->id)->where('role', User::ROLE_SECRETARY)->exists());
            $this->assertTrue(User::where('center_id', $center->id)->where('role', User::ROLE_TEACHER)->exists());
        }

        // 3. Couverture des 6 domaines d'enseignement
        $expectedDomains = ['sante', 'informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'];
        $teacherDomainsInDb = User::where('role', User::ROLE_TEACHER)
            ->whereHas('teacher', fn ($q) => $q->whereNotNull('domain'))
            ->with('teacher')
            ->get()
            ->pluck('teacher.domain')
            ->unique()
            ->all();

        foreach ($expectedDomains as $domain) {
            $this->assertContains($domain, $teacherDomainsInDb, "Le domaine [{$domain}] doit être couvert par les professeurs.");
        }
    }

    public function test_demo_accounts_can_authenticate(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        $defaultPassword = DemoAccountFixtures::password();

        // 1. Super Admin
        $superAdmin = User::where('email', 'superadmin.demo@education-centers.test')->firstOrFail();
        $this->assertTrue(Auth::attempt(['email' => $superAdmin->email, 'password' => $defaultPassword]));
        Auth::logout();

        // 2. Admin officiel
        $officialAdmin = User::where('email', 'admin.tanger@education-centers.ma')->firstOrFail();
        $this->assertTrue(Auth::attempt(['email' => $officialAdmin->email, 'password' => $defaultPassword]));
        Auth::logout();

        // 3. Secrétaire
        $secretary = User::where('email', 'secretaire.tanger@education-centers.test')->firstOrFail();
        $this->assertTrue(Auth::attempt(['email' => $secretary->email, 'password' => $defaultPassword]));
        Auth::logout();

        // 4. Professeur
        $teacher = User::where('email', 'prof.demo.tanger.informatique@education-centers.test')->firstOrFail();
        $this->assertTrue(Auth::attempt(['email' => $teacher->email, 'password' => $defaultPassword]));
        Auth::logout();

        // 5. Professeur synchronisé
        $syncedTeacher = User::where('email', 'prof.demo.berlin.01@education-centers.test')->firstOrFail();
        $this->assertTrue(Auth::attempt(['email' => $syncedTeacher->email, 'password' => $defaultPassword]));
        Auth::logout();

        // 6. Test d'authentification HTTP via route login
        $response = $this->post(route('login'), [
            'email' => 'secretaire.tanger@education-centers.test',
            'password' => $defaultPassword,
        ]);
        $response->assertRedirect(route('admin.home'));
        $this->assertAuthenticated();
    }

    public function test_seeder_is_idempotent_and_creates_no_duplicates(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $countFirstRun = User::count();

        // Deuxième exécution
        $this->seed(DemoAccountsSeeder::class);
        $countSecondRun = User::count();

        $this->assertSame($countFirstRun, $countSecondRun, 'Une seconde exécution ne doit créer aucun doublon.');
    }

    public function test_existing_passwords_are_never_modified(): void
    {
        $tanger = Center::firstOrCreate(['code' => 'TANGER'], [
            'name' => 'Centre Tanger',
            'city' => 'Tanger',
            'country' => 'Morocco',
            'status' => 'active',
        ]);

        // Création préalable d'un compte avec un mot de passe spécifique
        $customPassword = 'MySecretPassword123!';
        $existingUser = User::create([
            'name' => 'Custom Admin Tanger',
            'email' => 'admin.tanger.demo@education-centers.test',
            'role' => User::ROLE_CENTER_ADMIN,
            'center_id' => $tanger->id,
            'password' => Hash::make($customPassword),
            'status' => User::STATUS_ACTIVE,
        ]);

        $originalHash = $existingUser->password;

        $this->seed(DemoAccountsSeeder::class);

        $refreshed = $existingUser->fresh();
        $this->assertSame($originalHash, $refreshed->password, 'Le hash de mot de passe existant ne doit pas être écrasé.');
        $this->assertTrue(Hash::check($customPassword, $refreshed->password));
    }

    public function test_seeder_blocked_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Les seeders de comptes de démonstration sont interdits en production.');

        $seeder = new DemoAccountsSeeder();
        $seeder->run();
    }
}
