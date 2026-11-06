<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de structure canonique et uniformisée de la plateforme Education Centers.
 *
 * Structure garantie :
 * - 4 Centres de référence (Tanger, Essaouira, Agadir, Tétouane)
 * - 1 Super Admin unique
 * - 4 Administrateurs de centre (1 par centre)
 * - 4 Secrétaires (1 par centre)
 * - Exactement 6 formations par centre (24 formations officielles au total)
 * - Au minimum 6 professeurs par centre (24 professeurs au total), chacun rattaché à son centre et à une formation
 *
 * Idempotent : utilise updateOrCreate et firstOrCreate. Ne crée pas de doublons lors d'exécutions répétées.
 */
class PlatformStructureSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'EducationAdmin2026!';

    public function run(): void
    {
        $password = config('app.dev_admin_password', self::DEFAULT_PASSWORD);
        $hashedPassword = Hash::make($password);

        // ────────────────────────────────────────────────────────────
        // 1. Domaines de référence
        // ────────────────────────────────────────────────────────────
        $this->call(DomainSeeder::class);

        // ────────────────────────────────────────────────────────────
        // 2. Les 4 Centres
        // ────────────────────────────────────────────────────────────
        $this->call(CenterSeeder::class);

        $tanger = Center::where('code', 'TANGER')->firstOrFail();
        $essaouira = Center::where('code', 'ESSAOUIRA')->firstOrFail();
        $agadir = Center::where('code', 'AGADIR')->firstOrFail();
        $tetouane = Center::where('code', 'TETOUANE')->firstOrFail();

        $centers = [$tanger, $essaouira, $agadir, $tetouane];

        // ────────────────────────────────────────────────────────────
        // 3. Super Admin unique (1 seul pour toute la plateforme)
        // ────────────────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@education-centers.de'],
            [
                'name' => 'Super Admin',
                'password' => $hashedPassword,
                'role' => User::ROLE_SUPER_ADMIN,
                'center_id' => null,
                'status' => 'active',
                'locale' => 'fr',
            ]
        );

        // ────────────────────────────────────────────────────────────
        // 4. Les 4 Administrateurs de centre (1 par centre)
        // ────────────────────────────────────────────────────────────
        $adminDefinitions = [
            [
                'email' => 'admin.tanger@education-centers.ma',
                'name' => 'Admin Centre Tanger',
                'center_id' => $tanger->id,
            ],
            [
                'email' => 'admin.essaouira@education-centers.ma',
                'name' => 'Admin Centre Essaouira',
                'center_id' => $essaouira->id,
            ],
            [
                'email' => 'admin.agadir@education-centers.ma',
                'name' => 'Admin Centre Agadir',
                'center_id' => $agadir->id,
            ],
            [
                'email' => 'admin.tetouane@education-centers.ma',
                'name' => 'Admin Centre Tétouane',
                'center_id' => $tetouane->id,
            ],
        ];

        foreach ($adminDefinitions as $def) {
            User::updateOrCreate(
                ['email' => $def['email']],
                [
                    'name' => $def['name'],
                    'password' => $hashedPassword,
                    'role' => User::ROLE_CENTER_ADMIN,
                    'center_id' => $def['center_id'],
                    'status' => 'active',
                    'locale' => 'fr',
                ]
            );
        }

        // ────────────────────────────────────────────────────────────
        // 5. Les 4 Secrétaires (1 par centre)
        // ────────────────────────────────────────────────────────────
        $secretaryDefinitions = [
            [
                'email' => 'secretaire.tanger@education-centers.test',
                'name' => 'Secrétaire Tanger',
                'center_id' => $tanger->id,
            ],
            [
                'email' => 'secretaire.essaouira@education-centers.test',
                'name' => 'Secrétaire Essaouira',
                'center_id' => $essaouira->id,
            ],
            [
                'email' => 'secretaire.agadir@education-centers.test',
                'name' => 'Secrétaire Agadir',
                'center_id' => $agadir->id,
            ],
            [
                'email' => 'secretaire.tetouane@education-centers.test',
                'name' => 'Secrétaire Tétouane',
                'center_id' => $tetouane->id,
            ],
        ];

        foreach ($secretaryDefinitions as $def) {
            User::updateOrCreate(
                ['email' => $def['email']],
                [
                    'name' => $def['name'],
                    'password' => $hashedPassword,
                    'role' => User::ROLE_SECRETARY,
                    'center_id' => $def['center_id'],
                    'status' => 'active',
                    'locale' => 'fr',
                ]
            );
        }

        // ────────────────────────────────────────────────────────────
        // 5 bis. Nettoyage des doublons éventuels d'administrateurs
        // ────────────────────────────────────────────────────────────
        $canonicalEmails = [
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

        // Supprimer les comptes doublons d'admin qui ne correspondent pas aux emails canoniques
        $duplicateAdmins = User::whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_CENTER_ADMIN, User::ROLE_SECRETARY])
            ->whereNotIn('email', $canonicalEmails)
            ->get();

        foreach ($duplicateAdmins as $dup) {
            // Remapper les messages sur le compte canonique correspondant
            $targetUser = match ($dup->role) {
                User::ROLE_SUPER_ADMIN => User::where('email', 'admin@education-centers.de')->first(),
                User::ROLE_CENTER_ADMIN => User::where('center_id', $dup->center_id)->where('role', User::ROLE_CENTER_ADMIN)->first(),
                User::ROLE_SECRETARY => User::where('center_id', $dup->center_id)->where('role', User::ROLE_SECRETARY)->first(),
                default => null,
            };

            if ($targetUser) {
                \App\Models\Message::where('sender_id', $dup->id)->update(['sender_id' => $targetUser->id]);
            }
            \App\Models\ConversationParticipant::where('user_id', $dup->id)->delete();
            \App\Models\AppNotification::where('user_id', $dup->id)->delete();
            $dup->delete();
        }

        // ────────────────────────────────────────────────────────────
        // 6. Les 24 Formations officielles (exactement 6 par centre)
        // ────────────────────────────────────────────────────────────
        $this->call(OfficialFormationsSeeder::class);

        // Nettoyage des éventuelles formations de test fantaisistes créées hors standard
        Formation::where('is_official', true)
            ->whereNull('official_code')
            ->whereIn('name', ['ddddd', 'mrugheorijgsmlvdpor', 'heba'])
            ->delete();

        // ────────────────────────────────────────────────────────────
        // 7. Professeurs : 6 professeurs uniques par centre (24 professeurs avec 24 noms/prénoms distincts sans doublons)
        // ────────────────────────────────────────────────────────────
        $domainDefinitions = DomainSeeder::domains(); // 6 domaines

        // 24 profils uniques (nom, prénom et genre distincts pour chaque centre et chaque formation)
        $teacherProfilesByCenter = [
            'TANGER' => [
                'sante' => ['first_name' => 'Amina', 'last_name' => 'Bennani', 'gender' => 'female'],
                'informatique' => ['first_name' => 'Youssef', 'last_name' => 'El Amrani', 'gender' => 'male'],
                'hotellerie' => ['first_name' => 'Salma', 'last_name' => 'Idrissi', 'gender' => 'female'],
                'maconnerie' => ['first_name' => 'Rachid', 'last_name' => 'Ouazzani', 'gender' => 'male'],
                'design-interieur' => ['first_name' => 'Khadija', 'last_name' => 'Sabri', 'gender' => 'female'],
                'design-graphique' => ['first_name' => 'Omar', 'last_name' => 'Tazi', 'gender' => 'male'],
            ],
            'ESSAOUIRA' => [
                'sante' => ['first_name' => 'Fatima-Zahra', 'last_name' => 'Chraibi', 'gender' => 'female'],
                'informatique' => ['first_name' => 'Mehdi', 'last_name' => 'Alami', 'gender' => 'male'],
                'hotellerie' => ['first_name' => 'Nouhaila', 'last_name' => 'Benzakour', 'gender' => 'female'],
                'maconnerie' => ['first_name' => 'Tariq', 'last_name' => 'Berrada', 'gender' => 'male'],
                'design-interieur' => ['first_name' => 'Zineb', 'last_name' => 'Mansouri', 'gender' => 'female'],
                'design-graphique' => ['first_name' => 'Anas', 'last_name' => 'Fassi', 'gender' => 'male'],
            ],
            'AGADIR' => [
                'sante' => ['first_name' => 'Meryem', 'last_name' => 'Tahiri', 'gender' => 'female'],
                'informatique' => ['first_name' => 'Hamza', 'last_name' => 'Boukhriss', 'gender' => 'male'],
                'hotellerie' => ['first_name' => 'Asmaa', 'last_name' => 'Slaoui', 'gender' => 'female'],
                'maconnerie' => ['first_name' => 'Karim', 'last_name' => 'Chaoui', 'gender' => 'male'],
                'design-interieur' => ['first_name' => 'Houda', 'last_name' => 'Naciri', 'gender' => 'female'],
                'design-graphique' => ['first_name' => 'Bilal', 'last_name' => 'Belkacem', 'gender' => 'male'],
            ],
            'TETOUANE' => [
                'sante' => ['first_name' => 'Ibtissam', 'last_name' => 'Daoudi', 'gender' => 'female'],
                'informatique' => ['first_name' => 'Reda', 'last_name' => 'Cherkaoui', 'gender' => 'male'],
                'hotellerie' => ['first_name' => 'Kenza', 'last_name' => 'Filali', 'gender' => 'female'],
                'maconnerie' => ['first_name' => 'Nabil', 'last_name' => 'Lahlou', 'gender' => 'male'],
                'design-interieur' => ['first_name' => 'Soukaina', 'last_name' => 'Guessous', 'gender' => 'female'],
                'design-graphique' => ['first_name' => 'Marouane', 'last_name' => 'Zerouali', 'gender' => 'male'],
            ],
        ];

        foreach ($centers as $center) {
            $citySlug = strtolower($center->city);
            $centerProfiles = $teacherProfilesByCenter[strtoupper($center->code)] ?? [];

            foreach ($domainDefinitions as $index => $domainDef) {
                $domainSlug = $domainDef['slug'];
                $profile = $centerProfiles[$domainSlug] ?? [
                    'first_name' => 'Professeur',
                    'last_name' => strtoupper($center->code) . ' ' . ($index + 1),
                    'gender' => 'male',
                ];

                // Formation officielle correspondante dans ce centre
                $formation = Formation::where('center_id', $center->id)
                    ->where('is_official', true)
                    ->where('official_code', $domainSlug)
                    ->first();

                // 1. Compte User du professeur
                $teacherEmail = sprintf('prof.%s.%s@education-centers.test', $citySlug, str_replace('-', '', $domainSlug));

                $user = User::updateOrCreate(
                    ['email' => $teacherEmail],
                    [
                        'name' => $profile['first_name'] . ' ' . $profile['last_name'],
                        'password' => $hashedPassword,
                        'role' => User::ROLE_TEACHER,
                        'center_id' => $center->id,
                        'status' => 'active',
                        'locale' => 'fr',
                    ]
                );

                // 2. Dossier Teacher
                $teacherNumber = sprintf('T-%s-%02d', strtoupper($center->code), $index + 1);

                $teacher = Teacher::updateOrCreate(
                    [
                        'center_id' => $center->id,
                        'teacher_number' => $teacherNumber,
                    ],
                    [
                        'user_id' => $user->id,
                        'formation_id' => $formation?->id,
                        'domain' => $domainSlug,
                        'first_name' => $profile['first_name'],
                        'last_name' => $profile['last_name'],
                        'gender' => $profile['gender'],
                        'status' => 'active',
                        'specialization' => $domainDef['name'],
                    ]
                );

                // Lier également le professeur à la formation comme responsable
                if ($formation && $teacher) {
                    $formation->update(['teacher_id' => $teacher->id]);
                }
            }
        }

        $this->command?->info('✅ Structure canonique initialisée avec succès : 4 centres, 1 Super Admin, 4 Admin Centre, 4 Secrétaires, 24 Formations et 24 Professeurs.');
    }
}
