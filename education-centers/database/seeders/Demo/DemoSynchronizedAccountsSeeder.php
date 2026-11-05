<?php

namespace Database\Seeders\Demo;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DomainSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder complémentaire de synchronisation des comptes utilisateurs.
 *
 * Reproduit automatiquement sur toute machine de développement l'intégralité
 * des comptes présents dans la base locale de référence :
 *   - Super Admin officiel (admin@education-centers.de)
 *   - Les 4 Administrateurs de centre officiels (.ma)
 *   - Les 4 Secrétaires de centre (.test)
 *   - Les 2 Professeurs historiques (Berlin & Munich rattachés aux centres marocains)
 *   - Les 22 Professeurs de démonstration Berlin & Munich (rattachés à Tanger et Essaouira)
 *   - Le compte professeur complémentaire anonymisé (prof.sadiki.demo@education-centers.test)
 *
 * Idempotent : ne réinitialise aucun mot de passe existant, ne crée aucun doublon
 * et ne modifie jamais les données réelles existantes.
 *
 * Usage :
 *   php artisan db:seed --class=Database\Seeders\Demo\DemoSynchronizedAccountsSeeder
 */
class DemoSynchronizedAccountsSeeder extends Seeder
{
    use DemoAccountSupport;

    public function run(): void
    {
        DemoAccountFixtures::assertDemoEnvironmentAllowed($this);

        // 1. Domaines d'enseignement
        $this->call(DomainSeeder::class);

        // 2. Secrétaires des 4 centres
        $this->call(DemoSecretaryAccountsSeeder::class);

        // 3. Comptes officiels d'administration (Super Admin + 4 Admins .ma)
        $this->command?->line('  <comment>Comptes officiels d’administration</comment>');
        $adminCreated = 0;
        $adminExisting = 0;

        foreach (DemoAccountFixtures::officialAdminAccounts() as $def) {
            $centerId = null;

            if ($def['center_code']) {
                $center = Center::query()->where('code', $def['center_code'])->first();
                if (! $center) {
                    $this->command?->warn("  ⚠ Centre « {$def['center_code']} » introuvable pour {$def['email']}.");
                    continue;
                }
                $centerId = (int) $center->id;
            }

            $res = $this->ensureDemoAccount($def['email'], $def['name'], $def['role'], $centerId);
            $res['created'] ? $adminCreated++ : $adminExisting++;

            $this->command?->info(
                '    ' . ($res['created'] ? '✔ créé   ' : '· existant ')
                . $def['email'] . '  [' . $def['role'] . ($def['center_code'] ? ' · ' . $def['center_code'] : '') . ']'
            );
        }

        // 4. Professeurs complémentaires (25)
        $this->command?->line('  <comment>Professeurs complémentaires et historiques</comment>');
        $teachersCreated = 0;
        $teachersExisting = 0;

        foreach (DemoAccountFixtures::legacyAndAdditionalTeachers() as $def) {
            $center = Center::query()->where('code', $def['center_code'])->first();

            if (! $center) {
                $this->command?->warn("  ⚠ Centre « {$def['center_code']} » introuvable pour {$def['email']}.");
                continue;
            }

            $extraUserAttrs = [];
            if (! empty($def['domain'])) {
                $extraUserAttrs['domain'] = $def['domain'];
            }
            if (! empty($def['phone'])) {
                $extraUserAttrs['phone'] = $def['phone'];
            }

            $res = $this->ensureDemoAccount(
                $def['email'],
                $def['name'],
                User::ROLE_TEACHER,
                (int) $center->id,
                $extraUserAttrs
            );

            $user = $res['user'];
            $res['created'] ? $teachersCreated++ : $teachersExisting++;

            if (! empty($def['has_profile'])) {
                $teacher = Teacher::query()->updateOrCreate(
                    [
                        'center_id' => (int) $center->id,
                        'teacher_number' => $def['teacher_number'],
                    ],
                    [
                        'user_id' => $user->id,
                        'domain' => $def['domain'],
                        'first_name' => $def['first_name'],
                        'last_name' => $def['last_name'],
                        'date_of_birth' => '1980-01-01',
                        'place_of_birth' => $center->city ?: 'Tanger',
                        'gender' => $def['gender'],
                        'nationality' => 'Marocaine',
                        'address' => 'Avenue de la Formation',
                        'postal_code' => (string) ($center->postal_code ?: '90000'),
                        'city' => $center->city ?: 'Tanger',
                        'country' => 'Morocco',
                        'phone' => $center->phone,
                        'specialization' => DemoFixtures::domainSpecialization($def['domain']),
                        'qualification' => DemoFixtures::domainQualification($def['domain']),
                        'monthly_salary' => $def['monthly_salary'],
                        'hire_date' => $def['hire_date'],
                        'notes' => 'Professeur synchronisé de démonstration.',
                        'status' => 'active',
                    ]
                );

                $user->unsetRelation('teacher');
                $user->syncTeachingDomains([$def['domain']]);
                $this->assignClasses($teacher, $center, [$def['domain']]);
            }

            $this->command?->info(
                '    ' . ($res['created'] ? '✔ créé   ' : '· existant ')
                . $def['email'] . '  [' . $def['domain'] . ' · ' . $def['center_code'] . ']'
            );
        }

        $this->command?->info(
            "✅ Synchronisation terminée : {$adminCreated} admin(s) créé(s), {$teachersCreated} professeur(s) créé(s)."
        );
    }

    /**
     * Attache le professeur aux classes de son centre selon ses domaines.
     *
     * @param list<string> $domains
     */
    private function assignClasses(Teacher $teacher, Center $center, array $domains): void
    {
        foreach ($domains as $domain) {
            $class = ClassRoom::query()
                ->where('classes.center_id', (int) $center->id)
                ->whereHas('formation', fn ($q) => $q->where('domain', $domain))
                ->orderBy('id')
                ->first();

            if (! $class) {
                continue;
            }

            $alreadyLinked = DB::table('class_teacher')
                ->where('class_id', $class->id)
                ->where('teacher_id', $teacher->id)
                ->exists();

            if ($alreadyLinked) {
                continue;
            }

            DB::table('class_teacher')->insert([
                'class_id' => $class->id,
                'teacher_id' => $teacher->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

