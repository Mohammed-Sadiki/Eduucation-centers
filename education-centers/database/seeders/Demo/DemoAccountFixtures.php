<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Définitions partagées par les seeders de comptes de démonstration
 * (administrateurs et professeurs).
 *
 * Ces comptes sont volontairement isolés dans un espace de noms d'e-mails
 * réservé (« *.demo@education-centers.test ») afin de ne jamais entrer en
 * collision avec des comptes réels créés depuis l'interface. Ils sont
 * déterministes : la même exécution produit toujours les mêmes identifiants,
 * ce qui rend les seeders idempotents.
 */
final class DemoAccountFixtures
{
    /**
     * Domaine d'e-mail réservé aux comptes de démonstration.
     */
    public const DEMO_EMAIL_DOMAIN = 'education-centers.test';

    /**
     * Variable d'environnement (non versionnée) portant le mot de passe de démonstration.
     */
    public const PASSWORD_ENV = 'DEMO_DEFAULT_PASSWORD';

    /**
     * Valeur de repli si la variable d'environnement est absente.
     */
    public const PASSWORD_FALLBACK = 'DemoEducation2026!';

    /**
     * Les quatre centres marocains cibles, repérés par leur CODE (jamais par ID,
     * qui varie d'une machine à l'autre).
     *
     * @return list<array{code:string, city:string, label:string}>
     */
    public static function centers(): array
    {
        return [
            ['code' => 'TANGER',    'city' => 'tanger',    'label' => 'Tanger'],
            ['code' => 'AGADIR',    'city' => 'agadir',    'label' => 'Agadir'],
            ['code' => 'ESSAOUIRA', 'city' => 'essaouira', 'label' => 'Essaouira'],
            ['code' => 'TETOUANE',  'city' => 'tetouane',  'label' => 'Tétouane'],
        ];
    }

    public static function superAdminEmail(): string
    {
        return 'superadmin.demo@' . self::DEMO_EMAIL_DOMAIN;
    }

    public static function centerAdminEmail(string $city): string
    {
        return "admin.{$city}.demo@" . self::DEMO_EMAIL_DOMAIN;
    }

    public static function teacherEmail(string $city, string $domain): string
    {
        return "prof.demo.{$city}.{$domain}@" . self::DEMO_EMAIL_DOMAIN;
    }

    public static function secretaryEmail(string $city): string
    {
        return "secretaire.{$city}@" . self::DEMO_EMAIL_DOMAIN;
    }

    /**
     * Les quatre secrétaires de démonstration rattachées aux 4 centres.
     *
     * @return list<array{city: string, code: string, label: string, email: string}>
     */
    public static function secretaries(): array
    {
        return [
            ['city' => 'tanger',    'code' => 'TANGER',    'label' => 'Tanger',    'name' => 'Secrétaire Tanger',    'email' => self::secretaryEmail('tanger')],
            ['city' => 'essaouira', 'code' => 'ESSAOUIRA', 'label' => 'Essaouira', 'name' => 'Secrétaire Essaouira', 'email' => self::secretaryEmail('essaouira')],
            ['city' => 'agadir',    'code' => 'AGADIR',    'label' => 'Agadir',    'name' => 'Secrétaire Agadir',    'email' => self::secretaryEmail('agadir')],
            ['city' => 'tetouane',  'code' => 'TETOUANE',  'label' => 'Tétouane',  'name' => 'Secrétaire Tétouane',  'email' => self::secretaryEmail('tetouane')],
        ];
    }

    /**
     * Comptes d'administration complémentaires (Super Admin principal + 4 admins .ma).
     *
     * @return list<array{email: string, name: string, role: string, center_code: ?string}>
     */
    public static function officialAdminAccounts(): array
    {
        return [
            [
                'email' => 'admin@education-centers.de',
                'name' => 'Super Admin',
                'role' => \App\Models\User::ROLE_SUPER_ADMIN,
                'center_code' => null,
            ],
            [
                'email' => 'admin.tanger@education-centers.ma',
                'name' => 'Admin Centre Tanger',
                'role' => \App\Models\User::ROLE_CENTER_ADMIN,
                'center_code' => 'TANGER',
            ],
            [
                'email' => 'admin.essaouira@education-centers.ma',
                'name' => 'Admin Centre Essaouira',
                'role' => \App\Models\User::ROLE_CENTER_ADMIN,
                'center_code' => 'ESSAOUIRA',
            ],
            [
                'email' => 'admin.agadir@education-centers.ma',
                'name' => 'Admin Centre Agadir',
                'role' => \App\Models\User::ROLE_CENTER_ADMIN,
                'center_code' => 'AGADIR',
            ],
            [
                'email' => 'admin.tetouane@education-centers.ma',
                'name' => 'Admin Centre Tétouane',
                'role' => \App\Models\User::ROLE_CENTER_ADMIN,
                'center_code' => 'TETOUANE',
            ],
        ];
    }

    /**
     * Roster des 25 professeurs complémentaires existants (historiques et personnalisés).
     *
     * @return list<array<string, mixed>>
     */
    public static function legacyAndAdditionalTeachers(): array
    {
        return [
            // 1. Deux professeurs historiques
            [
                'email' => 'prof.berlin@education-centers.de',
                'name' => 'Professeur Berlin',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-000001',
                'first_name' => 'Johannes',
                'last_name' => 'Kohl',
                'gender' => 'male',
                'domain' => 'informatique',
                'monthly_salary' => 7500.00,
                'hire_date' => '2019-03-14',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.munich@education-centers.de',
                'name' => 'Professeur Munich',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-000001',
                'first_name' => 'Greta',
                'last_name' => 'Schneider',
                'gender' => 'female',
                'domain' => 'sante',
                'monthly_salary' => 8500.00,
                'hire_date' => '2018-09-01',
                'has_profile' => true,
            ],

            // 2. Onze professeurs de démonstration Berlin (rattachés à Tanger)
            [
                'email' => 'prof.demo.berlin.01@education-centers.test',
                'name' => 'Prof. Lena Hoffmann',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-01',
                'first_name' => 'Lena',
                'last_name' => 'Hoffmann',
                'gender' => 'female',
                'domain' => 'sante',
                'monthly_salary' => 7200.00,
                'hire_date' => '2018-09-01',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.02@education-centers.test',
                'name' => 'Prof. Karim Benali',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-02',
                'first_name' => 'Karim',
                'last_name' => 'Benali',
                'gender' => 'male',
                'domain' => 'sante',
                'monthly_salary' => 7200.00,
                'hire_date' => '2018-12-07',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.03@education-centers.test',
                'name' => 'Prof. Sofia Weber',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-03',
                'first_name' => 'Sofia',
                'last_name' => 'Weber',
                'gender' => 'female',
                'domain' => 'informatique',
                'monthly_salary' => 7600.00,
                'hire_date' => '2019-06-19',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.04@education-centers.test',
                'name' => 'Prof. Greta Schneider',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-04',
                'first_name' => 'Greta',
                'last_name' => 'Schneider',
                'gender' => 'female',
                'domain' => 'hotellerie',
                'monthly_salary' => 6400.00,
                'hire_date' => '2019-09-24',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.05@education-centers.test',
                'name' => 'Prof. Youssef Amrani',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-05',
                'first_name' => 'Youssef',
                'last_name' => 'Amrani',
                'gender' => 'male',
                'domain' => 'hotellerie',
                'monthly_salary' => 6400.00,
                'hire_date' => '2019-12-30',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.06@education-centers.test',
                'name' => 'Prof. Clara Fischer',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-06',
                'first_name' => 'Clara',
                'last_name' => 'Fischer',
                'gender' => 'female',
                'domain' => 'maconnerie',
                'monthly_salary' => 6200.00,
                'hire_date' => '2020-04-05',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.07@education-centers.test',
                'name' => 'Prof. David Meyer',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-07',
                'first_name' => 'David',
                'last_name' => 'Meyer',
                'gender' => 'male',
                'domain' => 'maconnerie',
                'monthly_salary' => 6200.00,
                'hire_date' => '2020-07-11',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.08@education-centers.test',
                'name' => 'Prof. Nora Klein',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-08',
                'first_name' => 'Nora',
                'last_name' => 'Klein',
                'gender' => 'female',
                'domain' => 'design-interieur',
                'monthly_salary' => 6800.00,
                'hire_date' => '2020-10-16',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.09@education-centers.test',
                'name' => 'Prof. Mehmet Yilmaz',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-09',
                'first_name' => 'Mehmet',
                'last_name' => 'Yilmaz',
                'gender' => 'male',
                'domain' => 'design-interieur',
                'monthly_salary' => 6800.00,
                'hire_date' => '2021-01-21',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.10@education-centers.test',
                'name' => 'Prof. Amelie Schulz',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-10',
                'first_name' => 'Amelie',
                'last_name' => 'Schulz',
                'gender' => 'female',
                'domain' => 'design-graphique',
                'monthly_salary' => 6600.00,
                'hire_date' => '2021-04-28',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.berlin.11@education-centers.test',
                'name' => 'Prof. Tobias Wagner',
                'center_code' => 'TANGER',
                'teacher_number' => 'BER-T-DEMO-11',
                'first_name' => 'Tobias',
                'last_name' => 'Wagner',
                'gender' => 'male',
                'domain' => 'design-graphique',
                'monthly_salary' => 6600.00,
                'hire_date' => '2021-08-03',
                'has_profile' => true,
            ],

            // 3. Onze professeurs de démonstration Munich (rattachés à Essaouira)
            [
                'email' => 'prof.demo.munich.01@education-centers.test',
                'name' => 'Prof. Karim Benali',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-01',
                'first_name' => 'Karim',
                'last_name' => 'Benali',
                'gender' => 'male',
                'domain' => 'sante',
                'monthly_salary' => 7200.00,
                'hire_date' => '2018-12-07',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.02@education-centers.test',
                'name' => 'Prof. Lena Hoffmann',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-02',
                'first_name' => 'Lena',
                'last_name' => 'Hoffmann',
                'gender' => 'female',
                'domain' => 'informatique',
                'monthly_salary' => 7600.00,
                'hire_date' => '2019-03-14',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.03@education-centers.test',
                'name' => 'Prof. Sofia Weber',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-03',
                'first_name' => 'Sofia',
                'last_name' => 'Weber',
                'gender' => 'female',
                'domain' => 'informatique',
                'monthly_salary' => 7600.00,
                'hire_date' => '2019-06-19',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.04@education-centers.test',
                'name' => 'Prof. Johannes Kohl',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-04',
                'first_name' => 'Johannes',
                'last_name' => 'Kohl',
                'gender' => 'male',
                'domain' => 'hotellerie',
                'monthly_salary' => 6400.00,
                'hire_date' => '2019-09-24',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.05@education-centers.test',
                'name' => 'Prof. Youssef Amrani',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-05',
                'first_name' => 'Youssef',
                'last_name' => 'Amrani',
                'gender' => 'male',
                'domain' => 'hotellerie',
                'monthly_salary' => 6400.00,
                'hire_date' => '2019-12-30',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.06@education-centers.test',
                'name' => 'Prof. Clara Fischer',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-06',
                'first_name' => 'Clara',
                'last_name' => 'Fischer',
                'gender' => 'female',
                'domain' => 'maconnerie',
                'monthly_salary' => 6200.00,
                'hire_date' => '2020-04-05',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.07@education-centers.test',
                'name' => 'Prof. David Meyer',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-07',
                'first_name' => 'David',
                'last_name' => 'Meyer',
                'gender' => 'male',
                'domain' => 'maconnerie',
                'monthly_salary' => 6200.00,
                'hire_date' => '2020-07-11',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.08@education-centers.test',
                'name' => 'Prof. Nora Klein',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-08',
                'first_name' => 'Nora',
                'last_name' => 'Klein',
                'gender' => 'female',
                'domain' => 'design-interieur',
                'monthly_salary' => 6800.00,
                'hire_date' => '2020-10-16',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.09@education-centers.test',
                'name' => 'Prof. Mehmet Yilmaz',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-09',
                'first_name' => 'Mehmet',
                'last_name' => 'Yilmaz',
                'gender' => 'male',
                'domain' => 'design-interieur',
                'monthly_salary' => 6800.00,
                'hire_date' => '2021-01-21',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.10@education-centers.test',
                'name' => 'Prof. Amelie Schulz',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-10',
                'first_name' => 'Amelie',
                'last_name' => 'Schulz',
                'gender' => 'female',
                'domain' => 'design-graphique',
                'monthly_salary' => 6600.00,
                'hire_date' => '2021-04-28',
                'has_profile' => true,
            ],
            [
                'email' => 'prof.demo.munich.11@education-centers.test',
                'name' => 'Prof. Tobias Wagner',
                'center_code' => 'ESSAOUIRA',
                'teacher_number' => 'MUN-T-DEMO-11',
                'first_name' => 'Tobias',
                'last_name' => 'Wagner',
                'gender' => 'male',
                'domain' => 'design-graphique',
                'monthly_salary' => 6600.00,
                'hire_date' => '2021-08-03',
                'has_profile' => true,
            ],

            // 4. Compte professeur supplémentaire anonymisé (reproduction fonctionnelle du compte local)
            [
                'email' => 'prof.sadiki.demo@education-centers.test',
                'name' => 'Prof. Mohamed Sadiki (Demo)',
                'center_code' => 'ESSAOUIRA',
                'domain' => 'informatique',
                'phone' => '0675523445',
                'has_profile' => false,
            ],
        ];
    }

    /**
     * Roster de professeurs de démonstration : un par domaine (six domaines),
     * dont deux affectés à un second domaine pour illustrer le multi-domaines.
     *
     * @return list<array<string, mixed>>
     */
    public static function teacherRoster(): array
    {
        return [
            [
                'domain' => 'sante',
                'secondary' => [],
                'first_name' => 'Amina',
                'last_name' => 'Bennani',
                'gender' => 'female',
            ],
            [
                'domain' => 'informatique',
                'secondary' => ['design-graphique'],
                'first_name' => 'Youssef',
                'last_name' => 'El Amrani',
                'gender' => 'male',
            ],
            [
                'domain' => 'hotellerie',
                'secondary' => [],
                'first_name' => 'Salma',
                'last_name' => 'Idrissi',
                'gender' => 'female',
            ],
            [
                'domain' => 'maconnerie',
                'secondary' => [],
                'first_name' => 'Rachid',
                'last_name' => 'Ouazzani',
                'gender' => 'male',
            ],
            [
                'domain' => 'design-interieur',
                'secondary' => ['design-graphique'],
                'first_name' => 'Khadija',
                'last_name' => 'Sabri',
                'gender' => 'female',
            ],
            [
                'domain' => 'design-graphique',
                'secondary' => [],
                'first_name' => 'Omar',
                'last_name' => 'Tazi',
                'gender' => 'male',
            ],
        ];
    }

    /**
     * Catalogue complet des 63 comptes représentés dans les fixtures de démonstration.
     *
     * @return list<array{email: string, name: string, role: string, center_code: ?string, domain: ?string}>
     */
    public static function allSynchronizedAccountFixtures(): array
    {
        $accounts = [];

        // 1. Super Admin Demo (1)
        $accounts[] = [
            'email' => self::superAdminEmail(),
            'name' => 'Super Admin (Demo)',
            'role' => \App\Models\User::ROLE_SUPER_ADMIN,
            'center_code' => null,
            'domain' => null,
        ];

        // 2. Admins Centre Demo (4)
        foreach (self::centers() as $c) {
            $accounts[] = [
                'email' => self::centerAdminEmail($c['city']),
                'name' => 'Admin Centre ' . $c['label'] . ' (Demo)',
                'role' => \App\Models\User::ROLE_CENTER_ADMIN,
                'center_code' => $c['code'],
                'domain' => null,
            ];
        }

        // 3. Professeurs marocains Demo (24)
        foreach (self::centers() as $c) {
            foreach (self::teacherRoster() as $profile) {
                $accounts[] = [
                    'email' => self::teacherEmail($c['city'], $profile['domain']),
                    'name' => 'Prof. ' . $profile['first_name'] . ' ' . $profile['last_name'] . ' (Demo)',
                    'role' => \App\Models\User::ROLE_TEACHER,
                    'center_code' => $c['code'],
                    'domain' => $profile['domain'],
                ];
            }
        }

        // 4. Comptes officiels d'administration (5)
        foreach (self::officialAdminAccounts() as $admin) {
            $accounts[] = [
                'email' => $admin['email'],
                'name' => $admin['name'],
                'role' => $admin['role'],
                'center_code' => $admin['center_code'],
                'domain' => null,
            ];
        }

        // 5. Secrétaires (4)
        foreach (self::secretaries() as $sec) {
            $accounts[] = [
                'email' => $sec['email'],
                'name' => $sec['name'],
                'role' => \App\Models\User::ROLE_SECRETARY,
                'center_code' => $sec['code'],
                'domain' => null,
            ];
        }

        // 6. Professeurs complémentaires (25)
        foreach (self::legacyAndAdditionalTeachers() as $t) {
            $accounts[] = [
                'email' => $t['email'],
                'name' => $t['name'],
                'role' => \App\Models\User::ROLE_TEACHER,
                'center_code' => $t['center_code'],
                'domain' => $t['domain'],
            ];
        }

        return $accounts;
    }

    /**
     * Mot de passe de démonstration lu depuis l'environnement (jamais versionné).
     */
    public static function password(): string
    {
        return (string) env(self::PASSWORD_ENV, self::PASSWORD_FALLBACK);
    }

    /**
     * Garde-fou : les seeders de démonstration sont interdits en production.
     */
    public static function assertDemoEnvironmentAllowed(?Seeder $seeder = null): void
    {
        if (app()->isProduction()) {
            $message = 'Les seeders de comptes de démonstration sont interdits en production.';

            throw new RuntimeException($message);
        }
    }
}
