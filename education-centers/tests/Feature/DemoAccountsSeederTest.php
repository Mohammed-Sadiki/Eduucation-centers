<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Domain;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\Demo\DemoAccountFixtures;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contrats du seeder UNIQUEMENT des comptes de connexion Demo.
 *
 * Simule le scénario « collègue clone le projet » : base vierge (SQLite en
 * mémoire + migrations), puis `db:seed --class=DemoAccountsSeeder`.
 *
 * Vérifie : comptes Demo complets et wordés sur les rôles/centres, aucune
 * donnée métier, idempotence stricte, jamais d'écrasement des comptes ou
 * centres existants, connexion réelle des trois profils, isolation centres.
 */
class DemoAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Tables métier qui doivent rester VIDES après le seeder. */
    private const BUSINESS_TABLES = [
        'students', 'stagiaires', 'stagiaire_diplomas', 'stagiaire_documents',
        'enrollments', 'payments', 'invoices', 'expenses', 'expense_items',
        'teacher_salaries', 'class_rooms', 'class_teacher', 'formations',
        'schedules', 'attendances', 'evaluations', 'grades', 'certificates',
        'documents', 'announcements', 'conversations', 'messages',
        'bank_transactions', 'accounting_entries',
    ];

    private function seedDemoAccounts(): void
    {
        $this->seed(DemoAccountsSeeder::class);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  1. Périmètre du seeder : comptes + structures minimales uniquement
    // ──────────────────────────────────────────────────────────────────────

    public function test_seeder_creates_exactly_the_demo_accounts_and_reference_centers(): void
    {
        $this->seedDemoAccounts();

        // 1 Super Admin + 4 Admin Centres + 24 Professeurs (6 par centre) = 29.
        $this->assertSame(29, User::query()->count());
        $this->assertSame(1, User::query()->where('role', User::ROLE_SUPER_ADMIN)->count());
        $this->assertSame(4, User::query()->where('role', User::ROLE_CENTER_ADMIN)->count());
        $this->assertSame(24, User::query()->where('role', User::ROLE_TEACHER)->count());

        // Uniquement des e-mails Demo — aucun compte réel inventé.
        $this->assertSame(
            29,
            User::query()->where('email', 'like', '%@' . DemoAccountFixtures::DEMO_EMAIL_DOMAIN)->count()
        );

        // Les 4 centres de référence, et seulement eux.
        $this->assertSame(4, Center::query()->count());
        $this->assertEqualsCanonicalizing(
            ['TANGER', 'AGADIR', 'ESSAOUIRA', 'TETOUANE'],
            Center::query()->pluck('code')->all()
        );

        // Les six domaines (nécessaires aux affectations professeur).
        $this->assertSame(6, Domain::query()->count());
    }

    public function test_seeder_creates_no_business_data(): void
    {
        $this->seedDemoAccounts();

        foreach (self::BUSINESS_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->assertSame(0, DB::table($table)->count(), "Table métier « {$table} » doit rester vide.");
        }

        // Les dossiers Teacher sont limités aux comptes Demo (marqués DEMO-).
        $this->assertSame(
            24,
            Teacher::query()->where('teacher_number', 'like', 'DEMO-%')->count()
        );
    }

    public function test_demo_admins_are_bound_to_their_center_and_super_admin_has_none(): void
    {
        $this->seedDemoAccounts();

        $superAdmin = User::query()->where('email', DemoAccountFixtures::superAdminEmail())->sole();
        $this->assertNull($superAdmin->center_id);

        foreach (DemoAccountFixtures::centers() as $center) {
            $admin = User::query()->where('email', DemoAccountFixtures::centerAdminEmail($center['city']))->sole();
            $centerId = Center::query()->where('code', $center['code'])->value('id');

            $this->assertSame(User::ROLE_CENTER_ADMIN, $admin->role);
            $this->assertSame((int) $centerId, (int) $admin->center_id);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  2. Mots de passe : Hash + variable d'environnement
    // ──────────────────────────────────────────────────────────────────────

    public function test_demo_passwords_are_hashed_and_match_the_fallback_password(): void
    {
        $this->seedDemoAccounts();

        $user = User::query()->where('email', DemoAccountFixtures::superAdminEmail())->sole();

        $this->assertNotSame(DemoAccountFixtures::password(), $user->password);
        $this->assertTrue(str_starts_with($user->password, '$2y$'));
        $this->assertTrue(Hash::check(DemoAccountFixtures::PASSWORD_FALLBACK, $user->password));
    }

    public function test_demo_password_is_configurable_via_env_variable(): void
    {
        $custom = 'MotDePasseDemoPerso2026!';
        $_ENV[DemoAccountFixtures::PASSWORD_ENV] = $custom;
        $_SERVER[DemoAccountFixtures::PASSWORD_ENV] = $custom;

        try {
            $this->seedDemoAccounts();

            foreach (User::query()->where('email', 'like', '%@' . DemoAccountFixtures::DEMO_EMAIL_DOMAIN)->get() as $user) {
                $this->assertTrue(Hash::check($custom, $user->password), "Mot de passe non appliqué : {$user->email}");
            }
        } finally {
            unset($_ENV[DemoAccountFixtures::PASSWORD_ENV], $_SERVER[DemoAccountFixtures::PASSWORD_ENV]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  3. Idempotence & non-destruction des données existantes
    // ──────────────────────────────────────────────────────────────────────

    public function test_seeder_is_idempotent_no_duplicates_on_replay(): void
    {
        $this->seedDemoAccounts();

        $usersBefore = User::query()->orderBy('id')->get(['id', 'email', 'password'])->all();
        $centersBefore = Center::query()->orderBy('id')->get()->all();
        $teachersBefore = Teacher::query()->count();

        $this->seedDemoAccounts();

        $this->assertSame(count($usersBefore), User::query()->count());
        $this->assertSame(4, Center::query()->count());
        $this->assertSame($teachersBefore, Teacher::query()->count());
        $this->assertEquals($usersBefore, User::query()->orderBy('id')->get(['id', 'email', 'password'])->all());
        $this->assertEquals($centersBefore, Center::query()->orderBy('id')->get()->all());
    }

    public function test_seeder_never_touches_real_accounts_or_customized_centers(): void
    {
        // Un centre de référence existe déjà mais avec des infos personnalisées.
        Center::query()->create([
            'name' => 'Centre Tanger Historique',
            'code' => 'TANGER',
            'city' => 'Tanger',
            'address' => 'Medina',
            'country' => 'Morocco',
            'status' => 'active',
        ]);

        // Des comptes réels créés manuellement (hors espace démo).
        $realAdmin = User::query()->create([
            'name' => 'Vrai Directeur',
            'email' => 'directeur@exemple.ma',
            'role' => User::ROLE_SUPER_ADMIN,
            'center_id' => null,
            'status' => User::STATUS_ACTIVE,
            'password' => Hash::make('RealSecret123!'),
        ]);

        $this->seedDemoAccounts();

        // Compte réel intact (rôle, mot de passe, e-mail).
        $realAdmin->refresh();
        $this->assertTrue(Hash::check('RealSecret123!', $realAdmin->password));
        $this->assertSame('Vrai Directeur', $realAdmin->name);

        // Centre existant non écrasé (firstOrCreate, jamais update).
        $tanger = Center::query()->where('code', 'TANGER')->sole();
        $this->assertSame('Centre Tanger Historique', $tanger->name);
        $this->assertSame('Medina', $tanger->address);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  4. Connexion réelle des trois profils Demo
    // ──────────────────────────────────────────────────────────────────────

    public function test_super_admin_demo_can_login(): void
    {
        $this->seedDemoAccounts();

        $response = $this->post(route('login'), [
            'email' => DemoAccountFixtures::superAdminEmail(),
            'password' => DemoAccountFixtures::PASSWORD_FALLBACK,
        ]);

        $this->assertAuthenticatedAs(
            User::query()->where('email', DemoAccountFixtures::superAdminEmail())->sole()
        );
        $response->assertRedirect(route('admin.home'));
    }

    /**
     * Connexion Admin Centre démo : un centre par appel (session fraîche).
     */
    #[DataProvider('centerAdminLoginProvider')]
    public function test_center_admin_demo_can_login(string $city, string $centerCode): void
    {
        $this->seedDemoAccounts();

        $response = $this->post(route('login'), [
            'email' => DemoAccountFixtures::centerAdminEmail($city),
            'password' => DemoAccountFixtures::PASSWORD_FALLBACK,
        ]);

        $expected = User::query()->where('email', DemoAccountFixtures::centerAdminEmail($city))->sole();

        $this->assertAuthenticatedAs($expected);
        $this->assertSame($centerCode, $expected->center?->code);
        $response->assertRedirect();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function centerAdminLoginProvider(): array
    {
        return [
            'Tanger' => ['tanger', 'TANGER'],
            'Agadir' => ['agadir', 'AGADIR'],
            'Essaouira' => ['essaouira', 'ESSAOUIRA'],
            'Tétouane' => ['tetouane', 'TETOUANE'],
        ];
    }

    public function test_teacher_demo_can_login(): void
    {
        $this->seedDemoAccounts();

        $email = DemoAccountFixtures::teacherEmail('tanger', 'informatique');

        $response = $this->post(route('login'), [
            'email' => $email,
            'password' => DemoAccountFixtures::PASSWORD_FALLBACK,
        ]);

        $this->assertAuthenticatedAs(User::query()->where('email', $email)->sole());
        $response->assertRedirect();
    }

    public function test_wrong_password_is_rejected_for_demo_accounts(): void
    {
        $this->seedDemoAccounts();

        $this->post(route('login'), [
            'email' => DemoAccountFixtures::superAdminEmail(),
            'password' => 'mauvais-mot-de-passe',
        ])->assertSessionHasErrors('email');

        $this->assertNull(auth()->user());
    }

    // ──────────────────────────────────────────────────────────────────────
    //  5. Isolation des centres après connexion
    // ──────────────────────────────────────────────────────────────────────

    public function test_center_admin_demo_only_sees_its_own_center_teachers(): void
    {
        $this->seedDemoAccounts();

        $adminTanger = User::query()
            ->where('email', DemoAccountFixtures::centerAdminEmail('tanger'))
            ->sole();

        $response = $this->actingAs($adminTanger)->get(route('teachers.index'));

        $response->assertStatus(200);
        $response->assertSee('prof.demo.tanger.sante@');
        $response->assertDontSee('prof.demo.agadir.sante@');
        $response->assertDontSee('prof.demo.essaouira.sante@');
        $response->assertDontSee('prof.demo.tetouane.sante@');
    }

    public function test_admin_center_demo_cannot_access_centers_global_crud(): void
    {
        $this->seedDemoAccounts();

        $adminAgadir = User::query()
            ->where('email', DemoAccountFixtures::centerAdminEmail('agadir'))
            ->sole();

        $this->actingAs($adminAgadir)->get(route('centers.index'))->assertStatus(403);
    }
}
