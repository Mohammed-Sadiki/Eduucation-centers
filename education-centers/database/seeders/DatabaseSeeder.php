<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Ordre important :
     * 1. Centers d'abord (les users y feront référence via center_id)
     * 2. Users ensuite
     */
    public function run(): void
    {
        // Structure canonique uniformisée de la plateforme :
        // 4 Centres, 1 Super Admin, 4 Admin Centre, 4 Secrétaires, 24 Formations et 24 Professeurs
        $this->call(PlatformStructureSeeder::class);

        // 0. Domaines de formation (portail Super Admin) — lus dynamiquement depuis la base
        $this->call(DomainSeeder::class);

        // 1. Créer les centres de démonstration
        $this->call(CenterSeeder::class);

        // 2. Créer un utilisateur Super Admin de démonstration (role = super_admin, center_id = null)
        $devPassword = config('app.dev_admin_password', 'EducationAdmin2026!');

        User::updateOrCreate(
            ['email' => 'admin@education-centers.de'],
            [
                'name'      => 'Super Admin',
                'password'  => \Illuminate\Support\Facades\Hash::make($devPassword),
                'role'      => User::ROLE_SUPER_ADMIN,
                'center_id' => null, // Super Admin : accès à tous les centres
            ]
        );

        // 3. Récupérer les centres Berlin et Munich
        $berlinCenter = \App\Models\Center::where('code', 'BERLIN')->first();
        $munichCenter = \App\Models\Center::where('code', 'MUNICH')->first();

        // 4. Admin Centre Berlin
        if ($berlinCenter) {
            $berlinUser = User::firstOrNew(['email' => 'admin.berlin@education-centers.de']);
            $berlinUser->name = 'Admin Centre Berlin';
            $berlinUser->password = \Illuminate\Support\Facades\Hash::make($devPassword);
            $berlinUser->role = User::ROLE_CENTER_ADMIN;
            $berlinUser->center_id = $berlinCenter->id;
            $berlinUser->save();
        }

        // 5. Admin Centre Munich
        if ($munichCenter) {
            $munichUser = User::firstOrNew(['email' => 'admin.munich@education-centers.de']);
            $munichUser->name = 'Admin Centre Munich';
            $munichUser->password = \Illuminate\Support\Facades\Hash::make($devPassword);
            $munichUser->role = User::ROLE_CENTER_ADMIN;
            $munichUser->center_id = $munichCenter->id;
            $munichUser->save();
        }

        $this->command->info('✅ Comptes de démonstration créés : Super Admin, Admin Berlin, Admin Munich.');

        // 6. Professeur Berlin
        if ($berlinCenter) {
            $teacherBerlin = User::firstOrNew(['email' => 'prof.berlin@education-centers.de']);
            $teacherBerlin->name = 'Professeur Berlin';
            $teacherBerlin->password = \Illuminate\Support\Facades\Hash::make($devPassword);
            $teacherBerlin->role = User::ROLE_TEACHER;
            $teacherBerlin->center_id = $berlinCenter->id;
            $teacherBerlin->status = 'active';
            $teacherBerlin->save();
        }

        // 7. Professeur Munich
        if ($munichCenter) {
            $teacherMunich = User::firstOrNew(['email' => 'prof.munich@education-centers.de']);
            $teacherMunich->name = 'Professeur Munich';
            $teacherMunich->password = \Illuminate\Support\Facades\Hash::make($devPassword);
            $teacherMunich->role = User::ROLE_TEACHER;
            $teacherMunich->center_id = $munichCenter->id;
            $teacherMunich->status = 'active';
            $teacherMunich->save();
        }

        // 7 bis. Second professeur Berlin — classes distinctes pour un scénario
        // réaliste (chaque professeur ne voit que ses propres classes).
        if ($berlinCenter) {
            $teacherBerlin2 = User::firstOrNew(['email' => 'prof.berlin2@education-centers.de']);
            $teacherBerlin2->name = 'Professeur Berlin 2';
            $teacherBerlin2->password = \Illuminate\Support\Facades\Hash::make($devPassword);
            $teacherBerlin2->role = User::ROLE_TEACHER;
            $teacherBerlin2->center_id = $berlinCenter->id;
            $teacherBerlin2->status = 'active';
            $teacherBerlin2->save();
        }

        // 8. Élève Berlin
        if ($berlinCenter) {
            $studentBerlin = User::firstOrNew(['email' => 'eleve.berlin@education-centers.de']);
            $studentBerlin->name = 'Élève Berlin';
            $studentBerlin->password = \Illuminate\Support\Facades\Hash::make($devPassword);
            $studentBerlin->role = User::ROLE_STUDENT;
            $studentBerlin->center_id = $berlinCenter->id;
            $studentBerlin->status = 'active';
            $studentBerlin->save();
        }

        // 9. Élève Munich
        if ($munichCenter) {
            $studentMunich = User::firstOrNew(['email' => 'eleve.munich@education-centers.de']);
            $studentMunich->name = 'Élève Munich';
            $studentMunich->password = \Illuminate\Support\Facades\Hash::make($devPassword);
            $studentMunich->role = User::ROLE_STUDENT;
            $studentMunich->center_id = $munichCenter->id;
            $studentMunich->status = 'active';
            $studentMunich->save();
        }

        // 10. Dossiers étudiants pour Berlin et Munich
        if ($berlinCenter && isset($studentBerlin)) {
            \App\Models\Student::updateOrCreate(
                ['user_id' => $studentBerlin->id],
                [
                    'center_id'                  => $berlinCenter->id,
                    'student_number'             => 'BER-000001',
                    'first_name'                 => 'Hiba',
                    'last_name'                  => 'Berlin',
                    'date_of_birth'              => '2002-05-14',
                    'place_of_birth'             => 'Berlin',
                    'gender'                     => 'female',
                    'nationality'                => 'Allemande',
                    'address'                    => 'Friedrichstraße 42',
                    'postal_code'                => '10117',
                    'city'                       => 'Berlin',
                    'country'                    => 'Germany',
                    'phone'                      => '+49 30 11223344',
                    'emergency_contact_name'     => 'Klaus Schmidt',
                    'emergency_contact_phone'    => '+49 170 1234567',
                    'emergency_contact_relation' => 'Parent',
                    'notes'                      => 'Dossier complet. Inscription validée.',
                    'status'                     => 'active',
                ]
            );
        }

        if ($munichCenter && isset($studentMunich)) {
            \App\Models\Student::updateOrCreate(
                ['user_id' => $studentMunich->id],
                [
                    'center_id'                  => $munichCenter->id,
                    'student_number'             => 'MUN-000001',
                    'first_name'                 => 'Maximilian',
                    'last_name'                  => 'Munich',
                    'date_of_birth'              => '2001-09-21',
                    'place_of_birth'             => 'München',
                    'gender'                     => 'male',
                    'nationality'                => 'Allemande',
                    'address'                    => 'Maximilianstraße 15',
                    'postal_code'                => '80539',
                    'city'                       => 'München',
                    'country'                    => 'Germany',
                    'phone'                      => '+49 89 99887766',
                    'emergency_contact_name'     => 'Anna Weber',
                    'emergency_contact_phone'    => '+49 171 7654321',
                    'emergency_contact_relation' => 'Mère',
                    'notes'                      => 'Dossier administratif en règle.',
                    'status'                     => 'active',
                ]
            );
        }

        // 11. Dossiers professeurs pour Berlin et Munich
        if ($berlinCenter && isset($teacherBerlin)) {
            \App\Models\Teacher::updateOrCreate(
                ['user_id' => $teacherBerlin->id],
                [
                    'center_id'      => $berlinCenter->id,
                    'teacher_number' => 'BER-T-000001',
                    'first_name'     => 'Johannes',
                    'last_name'      => 'Kohl',
                    'date_of_birth'  => '1985-03-12',
                    'place_of_birth' => 'Berlin',
                    'gender'         => 'male',
                    'nationality'    => 'Allemande',
                    'address'        => 'Alexanderplatz 7',
                    'postal_code'    => '10178',
                    'city'           => 'Berlin',
                    'country'        => 'Germany',
                    'phone'          => '+49 30 55443322',
                    'specialization' => 'Allemand & Intégration',
                    'qualification'  => 'Master en Linguistique Appliquée',
                    'hire_date'      => '2022-09-01',
                    'notes'          => 'Formateur certifié TELC.',
                    'status'         => 'active',
                ]
            );
        }

        if ($berlinCenter && isset($teacherBerlin2)) {
            \App\Models\Teacher::updateOrCreate(
                ['user_id' => $teacherBerlin2->id],
                [
                    'center_id'      => $berlinCenter->id,
                    'teacher_number' => 'BER-T-000002',
                    'first_name'     => 'Hanna',
                    'last_name'      => 'Weiss',
                    'date_of_birth'  => '1990-07-22',
                    'place_of_birth' => 'Potsdam',
                    'gender'         => 'female',
                    'nationality'    => 'Allemande',
                    'address'        => 'Potsdamer Platz 3',
                    'postal_code'    => '14467',
                    'city'           => 'Berlin',
                    'country'        => 'Germany',
                    'phone'          => '+49 30 22334455',
                    'specialization' => 'Allemand langue vivante',
                    'qualification'  => 'Master en Didactique des Langues',
                    'hire_date'      => '2024-02-01',
                    'notes'          => 'Cours du soir : groupe A1-02 uniquement.',
                    'status'         => 'active',
                ]
            );
        }

        if ($munichCenter && isset($teacherMunich)) {
            \App\Models\Teacher::updateOrCreate(
                ['user_id' => $teacherMunich->id],
                [
                    'center_id'      => $munichCenter->id,
                    'teacher_number' => 'MUN-T-000001',
                    'first_name'     => 'Greta',
                    'last_name'      => 'Schneider',
                    'date_of_birth'  => '1988-11-05',
                    'place_of_birth' => 'Augsburg',
                    'gender'         => 'female',
                    'nationality'    => 'Allemande',
                    'address'        => 'Leopoldstraße 28',
                    'postal_code'    => '80802',
                    'city'           => 'München',
                    'country'        => 'Germany',
                    'phone'          => '+49 89 66554433',
                    'specialization' => 'Sciences & Mathématiques',
                    'qualification'  => 'Doctorat en Sciences de l’Éducation',
                    'hire_date'      => '2023-01-15',
                    'notes'          => 'Coordinatrice pédagogique.',
                    'status'         => 'active',
                ]
            );
        }

        // 12. Formations de démonstration pour Berlin et Munich
        if ($berlinCenter) {
            \App\Models\Formation::updateOrCreate(
                ['center_id' => $berlinCenter->id, 'code' => 'DE-A1'],
                [
                    'name'           => 'Deutsch A1 — Débutant',
                    'description'    => 'Apprentissage des bases de la langue allemande pour débutants (A1 selon le CECRL).',
                    'duration_hours' => 120,
                    'duration_weeks' => 8,
                    'price'          => 450.00,
                    'currency'       => 'MAD',
                    'status'         => 'active',
                ]
            );

            \App\Models\Formation::updateOrCreate(
                ['center_id' => $berlinCenter->id, 'code' => 'DE-B1'],
                [
                    'name'           => 'Deutsch B1 — Intermédiaire',
                    'description'    => 'Perfectionnement et préparation aux examens de citoyenneté et de niveau B1.',
                    'duration_hours' => 160,
                    'duration_weeks' => 10,
                    'price'          => 590.00,
                    'currency'       => 'MAD',
                    'status'         => 'active',
                ]
            );

            \App\Models\Formation::updateOrCreate(
                ['center_id' => $berlinCenter->id, 'code' => 'IT-BASIC'],
                [
                    'name'           => 'Informatique Basics',
                    'description'    => 'Initiation à l’informatique, outils bureautiques et compétences numériques de base.',
                    'duration_hours' => 60,
                    'duration_weeks' => 4,
                    'price'          => 320.00,
                    'currency'       => 'MAD',
                    'status'         => 'active',
                ]
            );
        }

        if ($munichCenter) {
            // Même code 'DE-A1' autorisé pour un centre différent !
            \App\Models\Formation::updateOrCreate(
                ['center_id' => $munichCenter->id, 'code' => 'DE-A1'],
                [
                    'name'           => 'Deutsch A1 — Grundkurs München',
                    'description'    => 'Cours intensif d’allemand débutant adapté au marché bavarois.',
                    'duration_hours' => 130,
                    'duration_weeks' => 8,
                    'price'          => 490.00,
                    'currency'       => 'MAD',
                    'status'         => 'active',
                ]
            );

            \App\Models\Formation::updateOrCreate(
                ['center_id' => $munichCenter->id, 'code' => 'EN-B2'],
                [
                    'name'           => 'English B2 — Advanced Communication',
                    'description'    => 'Perfectionnement de l’anglais professionnel et prise de parole en public.',
                    'duration_hours' => 100,
                    'duration_weeks' => 6,
                    'price'          => 520.00,
                    'currency'       => 'MAD',
                    'status'         => 'active',
                ]
            );
        }

        // 13. Classes de démonstration pour Berlin et Munich
        if ($berlinCenter) {
            $berlinDeA1 = \App\Models\Formation::where('center_id', $berlinCenter->id)->where('code', 'DE-A1')->first();
            $berlinDeB1 = \App\Models\Formation::where('center_id', $berlinCenter->id)->where('code', 'DE-B1')->first();

            if ($berlinDeA1) {
                \App\Models\ClassRoom::updateOrCreate(
                    ['center_id' => $berlinCenter->id, 'code' => 'A1-01'],
                    [
                        'formation_id' => $berlinDeA1->id,
                        'name'         => 'Groupe A1-01 — Matin',
                        'description'  => 'Cours intensifs du matin de 09h00 à 12h30.',
                        'capacity'     => 18,
                        'start_date'   => '2026-11-02',
                        'end_date'     => '2026-12-22',
                        'status'       => 'active',
                    ]
                );

                \App\Models\ClassRoom::updateOrCreate(
                    ['center_id' => $berlinCenter->id, 'code' => 'A1-02'],
                    [
                        'formation_id' => $berlinDeA1->id,
                        'name'         => 'Groupe A1-02 — Soir',
                        'description'  => 'Cours du soir de 18h00 à 20h30.',
                        'capacity'     => 15,
                        'start_date'   => '2026-11-09',
                        'end_date'     => '2026-12-30',
                        'status'       => 'active',
                    ]
                );
            }

            if ($berlinDeB1) {
                \App\Models\ClassRoom::updateOrCreate(
                    ['center_id' => $berlinCenter->id, 'code' => 'B1-01'],
                    [
                        'formation_id' => $berlinDeB1->id,
                        'name'         => 'Groupe B1-01 — Après-midi',
                        'description'  => 'Perfectionnement B1 de 14h00 à 17h15.',
                        'capacity'     => 20,
                        'start_date'   => '2026-11-16',
                        'end_date'     => '2027-01-25',
                        'status'       => 'active',
                    ]
                );
            }
        }

        if ($munichCenter) {
            $munichDeA1 = \App\Models\Formation::where('center_id', $munichCenter->id)->where('code', 'DE-A1')->first();
            $munichEnB2 = \App\Models\Formation::where('center_id', $munichCenter->id)->where('code', 'EN-B2')->first();

            if ($munichDeA1) {
                // Même code 'A1-01' autorisé à Munich dans un centre distinct !
                \App\Models\ClassRoom::updateOrCreate(
                    ['center_id' => $munichCenter->id, 'code' => 'A1-01'],
                    [
                        'formation_id' => $munichDeA1->id,
                        'name'         => 'Groupe München A1-01',
                        'description'  => 'Cours A1 Munich centre.',
                        'capacity'     => 16,
                        'start_date'   => '2026-11-02',
                        'end_date'     => '2026-12-22',
                        'status'       => 'active',
                    ]
                );
            }

            if ($munichEnB2) {
                \App\Models\ClassRoom::updateOrCreate(
                    ['center_id' => $munichCenter->id, 'code' => 'B2-01'],
                    [
                        'formation_id' => $munichEnB2->id,
                        'name'         => 'Groupe English B2-01',
                        'description'  => 'English advanced communication Munich.',
                        'capacity'     => 14,
                        'start_date'   => '2026-11-23',
                        'end_date'     => '2027-01-15',
                        'status'       => 'active',
                    ]
                );
            }
        }

        // 14. Affectations de démonstration : Professeurs -> Classes
        // Scénario réaliste : chaque classe a un professeur distinct ;
        // aucun professeur n'est affecté à toutes les classes du centre.
        if ($berlinCenter) {
            $teacherBerlinModel = \App\Models\Teacher::where('center_id', $berlinCenter->id)->where('teacher_number', 'BER-T-000001')->first();
            $teacherBerlin2Model = \App\Models\Teacher::where('center_id', $berlinCenter->id)->where('teacher_number', 'BER-T-000002')->first();
            $berlinClassA1_01 = \App\Models\ClassRoom::where('center_id', $berlinCenter->id)->where('code', 'A1-01')->first();
            $berlinClassA1_02 = \App\Models\ClassRoom::where('center_id', $berlinCenter->id)->where('code', 'A1-02')->first();
            $berlinClassB1_01 = \App\Models\ClassRoom::where('center_id', $berlinCenter->id)->where('code', 'B1-01')->first();
        
            if ($teacherBerlinModel && $berlinClassA1_01) {
                $berlinClassA1_01->teachers()->syncWithoutDetaching([$teacherBerlinModel->id]);
            }
        
            if ($teacherBerlinModel && $berlinClassB1_01) {
                $berlinClassB1_01->teachers()->syncWithoutDetaching([$teacherBerlinModel->id]);
            }
        
            // A1-02 (cours du soir) est confié au second professeur berlin.
            if ($teacherBerlin2Model && $berlinClassA1_02) {
                $berlinClassA1_02->teachers()->syncWithoutDetaching([$teacherBerlin2Model->id]);
            }
        }

        if ($munichCenter) {
            $teacherMunichModel = \App\Models\Teacher::where('center_id', $munichCenter->id)->first();
            $munichClassA1_01 = \App\Models\ClassRoom::where('center_id', $munichCenter->id)->where('code', 'A1-01')->first();

            if ($teacherMunichModel && $munichClassA1_01) {
                $munichClassA1_01->teachers()->syncWithoutDetaching([$teacherMunichModel->id]);
            }
        }

        // 15. Salles de formation de démonstration
        if ($berlinCenter) {
            \App\Models\Room::updateOrCreate(
                ['center_id' => $berlinCenter->id, 'code' => 'ROOM-01'],
                [
                    'name'        => 'Salle Berlin 01',
                    'capacity'    => 20,
                    'floor'       => '1er étage',
                    'building'    => 'Bâtiment Principal',
                    'equipment'   => 'Vidéoprojecteur, Tableau blanc, Wi-Fi',
                    'description' => 'Grande salle de cours pour formations linguistiques.',
                    'status'      => 'active',
                ]
            );

            \App\Models\Room::updateOrCreate(
                ['center_id' => $berlinCenter->id, 'code' => 'ROOM-02'],
                [
                    'name'        => 'Salle Berlin 02',
                    'capacity'    => 15,
                    'floor'       => '2ème étage',
                    'building'    => 'Bâtiment Principal',
                    'equipment'   => 'Écran interactif, Système audio',
                    'description' => 'Salle pour cours en petits groupes.',
                    'status'      => 'active',
                ]
            );

            \App\Models\Room::updateOrCreate(
                ['center_id' => $berlinCenter->id, 'code' => 'ROOM-03'],
                [
                    'name'        => 'Salle informatique',
                    'capacity'    => 12,
                    'floor'       => 'Rez-de-chaussée',
                    'building'    => 'Annexe Tech',
                    'equipment'   => '12 PC connectés, Réseau haut débit, Projecteur',
                    'description' => 'Laboratoire informatique pour formations IT.',
                    'status'      => 'active',
                ]
            );

            // Salle inactive pour tests de statut
            \App\Models\Room::updateOrCreate(
                ['center_id' => $berlinCenter->id, 'code' => 'ROOM-RENOV'],
                [
                    'name'        => 'Salle en Rénovation',
                    'capacity'    => 10,
                    'floor'       => 'Sous-sol',
                    'building'    => 'Annexe Tech',
                    'equipment'   => 'En cours d’aménagement',
                    'description' => 'Fermée temporairement pour travaux.',
                    'status'      => 'inactive',
                ]
            );
        }

        if ($munichCenter) {
            // Même code 'ROOM-01' autorisé à Munich dans un centre distinct !
            \App\Models\Room::updateOrCreate(
                ['center_id' => $munichCenter->id, 'code' => 'ROOM-01'],
                [
                    'name'        => 'Salle Munich 01',
                    'capacity'    => 20,
                    'floor'       => 'Rez-de-chaussée',
                    'building'    => 'Bâtiment Bayern',
                    'equipment'   => 'Vidéoprojecteur HD, Climatisation, Wi-Fi',
                    'description' => 'Salle polyvalente principale.',
                    'status'      => 'active',
                ]
            );

            \App\Models\Room::updateOrCreate(
                ['center_id' => $munichCenter->id, 'code' => 'ROOM-02'],
                [
                    'name'        => 'Salle Munich 02',
                    'capacity'    => 15,
                    'floor'       => '1er étage',
                    'building'    => 'Bâtiment Bayern',
                    'equipment'   => 'Tableau interactif, 10 postes portables',
                    'description' => 'Salle pour ateliers et communication.',
                    'status'      => 'active',
                ]
            );
        }

        // 16. Séances d'emplois du temps (Schedules) pour Berlin et Munich
        if ($berlinCenter) {
            $classA1 = \App\Models\ClassRoom::where('center_id', $berlinCenter->id)->where('code', 'A1-01')->first();
            $classB1 = \App\Models\ClassRoom::where('center_id', $berlinCenter->id)->where('code', 'B1-01')->first();
            $teacherJohannes = \App\Models\Teacher::where('center_id', $berlinCenter->id)->where('teacher_number', 'BER-T-000001')->first();
            $room1 = \App\Models\Room::where('center_id', $berlinCenter->id)->where('code', 'ROOM-01')->first();
            $room2 = \App\Models\Room::where('center_id', $berlinCenter->id)->where('code', 'ROOM-02')->first();

            if ($classA1 && $teacherJohannes && $room1) {
                // Séance 1 : cours programmé
                \App\Models\Schedule::firstOrCreate(
                    [
                        'center_id'  => $berlinCenter->id,
                        'class_id'   => $classA1->id,
                        'teacher_id' => $teacherJohannes->id,
                        'room_id'    => $room1->id,
                        'date'       => '2026-10-20',
                        'start_time' => '09:00:00',
                    ],
                    [
                        'end_time'    => '11:00:00',
                        'title'       => 'Grammaire & Vocabulaire Fondamental',
                        'type'        => 'course',
                        'description' => 'Introduction aux structures de base et conversation guidée.',
                        'status'      => 'scheduled',
                    ]
                );

                // Séance terminée
                \App\Models\Schedule::firstOrCreate(
                    [
                        'center_id'  => $berlinCenter->id,
                        'class_id'   => $classA1->id,
                        'teacher_id' => $teacherJohannes->id,
                        'room_id'    => $room1->id,
                        'date'       => '2026-10-18',
                        'start_time' => '09:00:00',
                    ],
                    [
                        'end_time'    => '11:00:00',
                        'title'       => 'Évaluation diagnostique A1',
                        'type'        => 'exam',
                        'description' => 'Test de positionnement initial des apprenants.',
                        'status'      => 'completed',
                    ]
                );

                // Séance annulée
                \App\Models\Schedule::firstOrCreate(
                    [
                        'center_id'  => $berlinCenter->id,
                        'class_id'   => $classA1->id,
                        'teacher_id' => $teacherJohannes->id,
                        'room_id'    => $room1->id,
                        'date'       => '2026-10-19',
                        'start_time' => '14:00:00',
                    ],
                    [
                        'end_time'    => '16:00:00',
                        'title'       => 'Atelier phonétique allemand',
                        'type'        => 'workshop',
                        'description' => 'Séance annulée pour cause de maintenance électrique.',
                        'status'      => 'cancelled',
                    ]
                );
            }

            if ($classB1 && $teacherJohannes && $room2) {
                // Séance 2 : créneau consécutif (11:00 -> 13:00) pour tester l'absence de conflit adjacent
                \App\Models\Schedule::firstOrCreate(
                    [
                        'center_id'  => $berlinCenter->id,
                        'class_id'   => $classB1->id,
                        'teacher_id' => $teacherJohannes->id,
                        'room_id'    => $room2->id,
                        'date'       => '2026-10-20',
                        'start_time' => '11:00:00',
                    ],
                    [
                        'end_time'    => '13:00:00',
                        'title'       => 'Perfectionnement B1 — Expression Écrite',
                        'type'        => 'course',
                        'description' => 'Rédaction de lettres professionnelles et argumentaires.',
                        'status'      => 'scheduled',
                    ]
                );
            }
        }

        if ($munichCenter) {
            $classMunA1 = \App\Models\ClassRoom::where('center_id', $munichCenter->id)->where('code', 'A1-01')->first();
            $teacherGreta = \App\Models\Teacher::where('center_id', $munichCenter->id)->where('teacher_number', 'MUN-T-000001')->first();
            $roomMun1 = \App\Models\Room::where('center_id', $munichCenter->id)->where('code', 'ROOM-01')->first();

            if ($classMunA1 && $teacherGreta && $roomMun1) {
                \App\Models\Schedule::firstOrCreate(
                    [
                        'center_id'  => $munichCenter->id,
                        'class_id'   => $classMunA1->id,
                        'teacher_id' => $teacherGreta->id,
                        'room_id'    => $roomMun1->id,
                        'date'       => '2026-10-20',
                        'start_time' => '10:00:00',
                    ],
                    [
                        'end_time'    => '12:00:00',
                        'title'       => 'Séance Munich Débutants',
                        'type'        => 'course',
                        'description' => 'Module immersif Munich.',
                        'status'      => 'scheduled',
                    ]
                );
            }
        }

        $this->command->info('✅ Plateforme initialisée : Centres, Utilisateurs, Dossiers Élèves/Profs, Formations, Classes, Affectations, Salles & Emplois du temps.');

        // ─────────────────────────────────────────────────────────────────
        // 17. INSCRIPTIONS (Enrollments) — liées aux étudiants et formations
        // ─────────────────────────────────────────────────────────────────
        $superAdmin = \App\Models\User::where('role', \App\Models\User::ROLE_SUPER_ADMIN)->first();

        if ($berlinCenter) {
            $berlinStudentModel = \App\Models\Student::where('center_id', $berlinCenter->id)->first();
            $berlinDeA1         = \App\Models\Formation::where('center_id', $berlinCenter->id)->where('code', 'DE-A1')->first();
            $berlinDeB1         = \App\Models\Formation::where('center_id', $berlinCenter->id)->where('code', 'DE-B1')->first();
            $berlinClassA1      = \App\Models\ClassRoom::where('center_id', $berlinCenter->id)->where('code', 'A1-01')->first();
            $berlinClassB1      = \App\Models\ClassRoom::where('center_id', $berlinCenter->id)->where('code', 'B1-01')->first();

            if ($berlinStudentModel && $berlinDeA1) {
                \App\Models\Enrollment::updateOrCreate(
                    ['center_id' => $berlinCenter->id, 'student_id' => $berlinStudentModel->id, 'formation_id' => $berlinDeA1->id, 'school_year' => '2026/2027'],
                    [
                        'class_id'            => $berlinClassA1?->id,
                        'registration_number' => 'BERLIN-INS-000001',
                        'application_date'    => '2026-10-01',
                        'status'              => 'accepted',
                        'notes'               => 'Inscription validée après entretien de positionnement.',
                        'decision_date'       => '2026-10-05',
                        'decision_notes'      => 'Niveau confirmé A1. Intégration dans le groupe matin.',
                        'accepted_at'         => '2026-10-05 10:00:00',
                    ]
                );
            }

            if ($berlinStudentModel && $berlinDeB1) {
                \App\Models\Enrollment::updateOrCreate(
                    ['center_id' => $berlinCenter->id, 'student_id' => $berlinStudentModel->id, 'formation_id' => $berlinDeB1->id, 'school_year' => '2026/2027'],
                    [
                        'class_id'            => $berlinClassB1?->id,
                        'registration_number' => 'BERLIN-INS-000002',
                        'application_date'    => '2026-10-10',
                        'status'              => 'pending',
                        'notes'               => 'Demande d\'inscription en attente de traitement.',
                    ]
                );
            }
        }

        if ($munichCenter) {
            $munichStudentModel = \App\Models\Student::where('center_id', $munichCenter->id)->first();
            $munichDeA1         = \App\Models\Formation::where('center_id', $munichCenter->id)->where('code', 'DE-A1')->first();
            $munichEnB2         = \App\Models\Formation::where('center_id', $munichCenter->id)->where('code', 'EN-B2')->first();
            $munichClassA1      = \App\Models\ClassRoom::where('center_id', $munichCenter->id)->where('code', 'A1-01')->first();

            if ($munichStudentModel && $munichDeA1) {
                \App\Models\Enrollment::updateOrCreate(
                    ['center_id' => $munichCenter->id, 'student_id' => $munichStudentModel->id, 'formation_id' => $munichDeA1->id, 'school_year' => '2026/2027'],
                    [
                        'class_id'            => $munichClassA1?->id,
                        'registration_number' => 'MUNICH-INS-000001',
                        'application_date'    => '2026-10-02',
                        'status'              => 'accepted',
                        'notes'               => 'Dossier complet. Inscription confirmée.',
                        'decision_date'       => '2026-10-07',
                        'decision_notes'      => 'Accepté dans le groupe A1-01 Munich.',
                        'accepted_at'         => '2026-10-07 09:30:00',
                    ]
                );
            }

            if ($munichStudentModel && $munichEnB2) {
                \App\Models\Enrollment::updateOrCreate(
                    ['center_id' => $munichCenter->id, 'student_id' => $munichStudentModel->id, 'formation_id' => $munichEnB2->id, 'school_year' => '2026/2027'],
                    [
                        'registration_number' => 'MUNICH-INS-000002',
                        'application_date'    => '2026-10-12',
                        'status'              => 'under_review',
                        'notes'               => 'Dossier en cours d\'examen par l\'administration.',
                    ]
                );
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // 18. PAIEMENTS ÉTUDIANTS — en MAD, variés (payé, partiel, en attente)
        // ─────────────────────────────────────────────────────────────────
        if ($berlinCenter) {
            $berlinStudentModel = \App\Models\Student::where('center_id', $berlinCenter->id)->first();
            $berlinDeA1         = \App\Models\Formation::where('center_id', $berlinCenter->id)->where('code', 'DE-A1')->first();
            $berlinDeB1         = \App\Models\Formation::where('center_id', $berlinCenter->id)->where('code', 'DE-B1')->first();
            $enrollA1           = \App\Models\Enrollment::where('center_id', $berlinCenter->id)
                                    ->where('student_id', $berlinStudentModel?->id)
                                    ->where('formation_id', $berlinDeA1?->id)
                                    ->first();

            if ($berlinStudentModel && $berlinDeA1) {
                // Paiement 1 : acompte initial (payé)
                \App\Models\Payment::updateOrCreate(
                    ['reference' => 'PAY-BERLIN-000001'],
                    [
                        'center_id'    => $berlinCenter->id,
                        'student_id'   => $berlinStudentModel->id,
                        'formation_id' => $berlinDeA1->id,
                        'enrollment_id'=> $enrollA1?->id,
                        'school_year'  => '2026/2027',
                        'domain'       => $berlinDeA1->domain,
                        'amount'       => 2250.00,
                        'currency'     => 'MAD',
                        'payment_date' => '2026-10-06',
                        'method'       => 'bank_transfer',
                        'status'       => 'paid',
                        'notes'        => '1ère tranche — acompte inscription A1.',
                        'recorded_by'  => $superAdmin?->id,
                    ]
                );

                // Paiement 2 : 2ème tranche (payé)
                \App\Models\Payment::updateOrCreate(
                    ['reference' => 'PAY-BERLIN-000002'],
                    [
                        'center_id'    => $berlinCenter->id,
                        'student_id'   => $berlinStudentModel->id,
                        'formation_id' => $berlinDeA1->id,
                        'enrollment_id'=> $enrollA1?->id,
                        'school_year'  => '2026/2027',
                        'domain'       => $berlinDeA1->domain,
                        'amount'       => 2250.00,
                        'currency'     => 'MAD',
                        'payment_date' => '2026-11-01',
                        'method'       => 'cash',
                        'status'       => 'paid',
                        'notes'        => '2ème tranche — solde formation A1.',
                        'recorded_by'  => $superAdmin?->id,
                    ]
                );
            }

            // Paiement 3 : formation B1 — partiellement payé (en attente du solde)
            if ($berlinStudentModel && $berlinDeB1) {
                \App\Models\Payment::updateOrCreate(
                    ['reference' => 'PAY-BERLIN-000003'],
                    [
                        'center_id'    => $berlinCenter->id,
                        'student_id'   => $berlinStudentModel->id,
                        'formation_id' => $berlinDeB1->id,
                        'school_year'  => '2026/2027',
                        'domain'       => $berlinDeB1->domain,
                        'amount'       => 1500.00,
                        'currency'     => 'MAD',
                        'payment_date' => '2026-10-15',
                        'method'       => 'card',
                        'status'       => 'paid',
                        'notes'        => 'Acompte partiel B1 — solde à venir.',
                        'recorded_by'  => $superAdmin?->id,
                    ]
                );
            }
        }

        if ($munichCenter) {
            $munichStudentModel = \App\Models\Student::where('center_id', $munichCenter->id)->first();
            $munichDeA1         = \App\Models\Formation::where('center_id', $munichCenter->id)->where('code', 'DE-A1')->first();
            $munichEnB2         = \App\Models\Formation::where('center_id', $munichCenter->id)->where('code', 'EN-B2')->first();
            $enrollMunA1        = \App\Models\Enrollment::where('center_id', $munichCenter->id)
                                    ->where('student_id', $munichStudentModel?->id)
                                    ->where('formation_id', $munichDeA1?->id)
                                    ->first();

            if ($munichStudentModel && $munichDeA1) {
                // Paiement complet en une fois — virement
                \App\Models\Payment::updateOrCreate(
                    ['reference' => 'PAY-MUNICH-000001'],
                    [
                        'center_id'    => $munichCenter->id,
                        'student_id'   => $munichStudentModel->id,
                        'formation_id' => $munichDeA1->id,
                        'enrollment_id'=> $enrollMunA1?->id,
                        'school_year'  => '2026/2027',
                        'domain'       => $munichDeA1->domain,
                        'amount'       => 4900.00,
                        'currency'     => 'MAD',
                        'payment_date' => '2026-10-08',
                        'method'       => 'bank_transfer',
                        'status'       => 'paid',
                        'notes'        => 'Paiement intégral formation A1 München.',
                        'recorded_by'  => $superAdmin?->id,
                    ]
                );
            }

            if ($munichStudentModel && $munichEnB2) {
                // Paiement en attente
                \App\Models\Payment::updateOrCreate(
                    ['reference' => 'PAY-MUNICH-000002'],
                    [
                        'center_id'    => $munichCenter->id,
                        'student_id'   => $munichStudentModel->id,
                        'formation_id' => $munichEnB2->id,
                        'school_year'  => '2026/2027',
                        'domain'       => $munichEnB2->domain,
                        'amount'       => 2600.00,
                        'currency'     => 'MAD',
                        'payment_date' => '2026-10-14',
                        'method'       => 'cash',
                        'status'       => 'pending',
                        'notes'        => 'Acompte English B2 — en attente de validation.',
                        'recorded_by'  => $superAdmin?->id,
                    ]
                );
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // 18b. FACTURES / ÉCHÉANCIERS ÉTUDIANTS — alimente « Mes paiements »
        //      Trois états visuels : régularisé, dépassé (alerte), à venir.
        //      Un état « dépassé » n'est posé que si la due_date est passée.
        // ─────────────────────────────────────────────────────────────────
        if ($berlinCenter) {
            $berlinStudentModel = \App\Models\Student::where('center_id', $berlinCenter->id)->first();
            $berlinDeA1         = \App\Models\Formation::where('center_id', $berlinCenter->id)->where('code', 'DE-A1')->first();
            $enrollA1           = \App\Models\Enrollment::where('center_id', $berlinCenter->id)
                                    ->where('student_id', $berlinStudentModel?->id)
                                    ->where('formation_id', $berlinDeA1?->id)
                                    ->first();

            if ($berlinStudentModel && $berlinDeA1) {
                $invoices = [
                    ['number' => 'INV-BERLIN-000001', 'amount' => 3000.00, 'due' => now()->subDays(45)->toDateString(), 'status' => 'paid'],  // régularisée
                    ['number' => 'INV-BERLIN-000002', 'amount' => 3000.00, 'due' => now()->subDays(15)->toDateString(), 'status' => 'sent'],  // dépassée → alerte
                    ['number' => 'INV-BERLIN-000003', 'amount' => 3000.00, 'due' => now()->addDays(20)->toDateString(), 'status' => 'sent'],  // à venir
                ];

                foreach ($invoices as $inv) {
                    \App\Models\Invoice::updateOrCreate(
                        ['invoice_number' => $inv['number']],
                        [
                            'center_id'     => $berlinCenter->id,
                            'student_id'    => $berlinStudentModel->id,
                            'enrollment_id' => $enrollA1?->id,
                            'formation_id'  => $berlinDeA1->id,
                            'total_amount'  => $inv['amount'],
                            'currency'      => 'MAD',
                            'invoice_date'  => now()->subDays(60)->toDateString(),
                            'due_date'      => $inv['due'],
                            'status'        => $inv['status'],
                            'notes'         => 'Échéancier formation A1 (démonstration).',
                            'created_by'    => $superAdmin?->id,
                        ]
                    );
                }
            }
        }

        if ($munichCenter) {
            $munichStudentModel = \App\Models\Student::where('center_id', $munichCenter->id)->first();
            $munichDeA1         = \App\Models\Formation::where('center_id', $munichCenter->id)->where('code', 'DE-A1')->first();
            $enrollMunA1        = \App\Models\Enrollment::where('center_id', $munichCenter->id)
                                    ->where('student_id', $munichStudentModel?->id)
                                    ->where('formation_id', $munichDeA1?->id)
                                    ->first();

            if ($munichStudentModel && $munichDeA1) {
                // Élève entièrement à jour : échéance unique régularisée, sans retard.
                \App\Models\Invoice::updateOrCreate(
                    ['invoice_number' => 'INV-MUNICH-000001'],
                    [
                        'center_id'     => $munichCenter->id,
                        'student_id'    => $munichStudentModel->id,
                        'enrollment_id' => $enrollMunA1?->id,
                        'formation_id'  => $munichDeA1->id,
                        'total_amount'  => 4900.00,
                        'currency'      => 'MAD',
                        'invoice_date'  => now()->subDays(40)->toDateString(),
                        'due_date'      => now()->subDays(5)->toDateString(),
                        'status'        => 'paid',
                        'notes'         => 'Échéance réglée — élève à jour (démonstration).',
                        'created_by'    => $superAdmin?->id,
                    ]
                );
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // 19. SALAIRES PROFESSEURS — multi-mois, statuts variés (payé, partiel, impayé)
        // ─────────────────────────────────────────────────────────────────
        if ($berlinCenter) {
            $teacherJohannes = \App\Models\Teacher::where('center_id', $berlinCenter->id)
                                ->where('teacher_number', 'BER-T-000001')->first();

            if ($teacherJohannes) {
                $johannesSalary = 7500.00;

                // Mettre à jour le salaire mensuel configurable du professeur
                $teacherJohannes->update(['monthly_salary' => $johannesSalary, 'domain' => 'informatique']);

                $salaryMonths = [
                    ['month' => 8, 'year' => 2026, 'paid' => 0,               'status' => 'unpaid',  'date' => null,          'method' => null,                'scheduled' => now()->subDays(20)->toDateString(), 'note' => 'Août 2026 — date prévue dépassée, non versé → en retard.'],
                    ['month' => 9, 'year' => 2026, 'paid' => $johannesSalary, 'status' => 'paid',    'date' => '2026-09-30', 'method' => 'Virement bancaire', 'scheduled' => '2026-09-30', 'note' => 'Salaire Septembre 2026.'],
                    ['month' => 10,'year' => 2026, 'paid' => $johannesSalary, 'status' => 'paid',    'date' => '2026-10-31', 'method' => 'Virement bancaire', 'scheduled' => '2026-10-31', 'note' => 'Salaire Octobre 2026.'],
                    ['month' => 11,'year' => 2026, 'paid' => 3750.00,          'status' => 'partial', 'date' => null,          'method' => 'Espèces',           'scheduled' => now()->addDays(15)->toDateString(), 'note' => 'Acompte Novembre — solde à venir, date prévue non atteinte.'],
                    ['month' => 12,'year' => 2026, 'paid' => 0,                'status' => 'unpaid',  'date' => null,          'method' => null,                'scheduled' => null, 'note' => 'Décembre — sans date prévue : jamais « en retard ».'],
                ];

                foreach ($salaryMonths as $s) {
                    \App\Models\TeacherSalary::updateOrCreate(
                        [
                            'center_id'  => $berlinCenter->id,
                            'teacher_id' => $teacherJohannes->id,
                            'month'      => $s['month'],
                            'year'       => $s['year'],
                        ],
                        [
                            'domain'                => $teacherJohannes->domain,
                            'school_year'           => '2026/2027',
                            'expected_amount'       => $johannesSalary,
                            'paid_amount'           => $s['paid'],
                            'currency'              => 'MAD',
                            'status'                => $s['status'],
                            'payment_date'          => $s['date'],
                            'scheduled_payment_date'=> $s['scheduled'],
                            'payment_method'        => $s['method'],
                            'notes'                 => $s['note'],
                            'recorded_by'           => $superAdmin?->id,
                        ]
                    );
                }
            }
        }

        if ($munichCenter) {
            $teacherGreta = \App\Models\Teacher::where('center_id', $munichCenter->id)
                            ->where('teacher_number', 'MUN-T-000001')->first();

            if ($teacherGreta) {
                $gretaSalary = 8500.00;

                // Mettre à jour le salaire mensuel configurable
                $teacherGreta->update(['monthly_salary' => $gretaSalary, 'domain' => 'sante']);

                $salaryMonths = [
                    ['month' => 9,  'year' => 2026, 'paid' => $gretaSalary, 'status' => 'paid',    'date' => '2026-09-28', 'method' => 'Virement bancaire', 'scheduled' => '2026-09-28', 'note' => 'Salaire Septembre 2026.'],
                    ['month' => 10, 'year' => 2026, 'paid' => $gretaSalary, 'status' => 'paid',    'date' => '2026-10-29', 'method' => 'Virement bancaire', 'scheduled' => '2026-10-29', 'note' => 'Salaire Octobre 2026.'],
                    ['month' => 11, 'year' => 2026, 'paid' => 4250.00,       'status' => 'partial', 'date' => null,          'method' => 'Virement bancaire', 'scheduled' => now()->addDays(10)->toDateString(), 'note' => 'Moitié Novembre versée — reste à payer, date prévue non atteinte.'],
                    ['month' => 12, 'year' => 2026, 'paid' => 0,              'status' => 'unpaid',  'date' => null,          'method' => null,                'scheduled' => null, 'note' => 'Décembre — en attente, sans date prévue.'],
                ];

                foreach ($salaryMonths as $s) {
                    \App\Models\TeacherSalary::updateOrCreate(
                        [
                            'center_id'  => $munichCenter->id,
                            'teacher_id' => $teacherGreta->id,
                            'month'      => $s['month'],
                            'year'       => $s['year'],
                        ],
                        [
                            'domain'                => $teacherGreta->domain,
                            'school_year'           => '2026/2027',
                            'expected_amount'       => $gretaSalary,
                            'paid_amount'           => $s['paid'],
                            'currency'              => 'MAD',
                            'status'                => $s['status'],
                            'payment_date'          => $s['date'],
                            'scheduled_payment_date'=> $s['scheduled'],
                            'payment_method'        => $s['method'],
                            'notes'                 => $s['note'],
                            'recorded_by'           => $superAdmin?->id,
                        ]
                    );
                }
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // 20. STAGIAIRES — données de démonstration réalistes
        // ─────────────────────────────────────────────────────────────────
        $stagiairesData = [
            // Berlin — Stagiaires
            [
                'center_code'    => 'BERLIN',
                'first_name'     => 'Fatima',
                'last_name'      => 'El Amrani',
                'date_of_birth'  => '1998-03-21',
                'place_of_birth' => 'Casablanca',
                'gender'         => 'female',
                'nationality'    => 'Marocaine',
                'address'        => 'Goethestraße 12, Berlin',
                'phone'          => '+49 30 22334455',
                'email'          => 'fatima.elamrani@email.de',
                'cin_number'     => 'BK123456',
                'cin_issued_date'=> '2018-06-10',
                'cin_issued_place'=> 'Casablanca',
                'cin_recto_path' => 'stagiaires/cin/demo_recto.jpg',
                'stage_start_date'=> '2026-10-01',
                'stage_end_date' => '2027-03-31',
                'stage_type'     => 'Stage de qualification',
                'stage_price'    => 9500.00,
                'currency'       => 'MAD',
                'status'         => 'en_cours',
                'notes'          => 'Stagiaire motivée. Résultats en progrès constants.',
            ],
            [
                'center_code'    => 'BERLIN',
                'first_name'     => 'Youssef',
                'last_name'      => 'Benali',
                'date_of_birth'  => '2000-07-14',
                'place_of_birth' => 'Marrakech',
                'gender'         => 'male',
                'nationality'    => 'Marocaine',
                'address'        => 'Berliner Allee 5, Berlin',
                'phone'          => '+49 30 55667788',
                'email'          => 'youssef.benali@email.de',
                'cin_number'     => 'WA654321',
                'cin_issued_date'=> '2020-02-15',
                'cin_issued_place'=> 'Marrakech',
                'cin_recto_path' => 'stagiaires/cin/demo_recto.jpg',
                'stage_start_date'=> '2026-11-01',
                'stage_end_date' => '2027-04-30',
                'stage_type'     => 'Stage de perfectionnement',
                'stage_price'    => 10500.00,
                'currency'       => 'MAD',
                'status'         => 'nouveau',
                'notes'          => 'Dossier déposé. Stage commençant en novembre.',
            ],
            [
                'center_code'    => 'BERLIN',
                'first_name'     => 'Nadia',
                'last_name'      => 'Khalil',
                'date_of_birth'  => '1996-11-30',
                'place_of_birth' => 'Fès',
                'gender'         => 'female',
                'nationality'    => 'Marocaine',
                'address'        => 'Friedrichstraße 88, Berlin',
                'phone'          => '+49 30 99887766',
                'email'          => 'nadia.khalil@email.de',
                'cin_number'     => 'FE789012',
                'cin_issued_date'=> '2016-08-20',
                'cin_issued_place'=> 'Fès',
                'cin_recto_path' => 'stagiaires/cin/demo_recto.jpg',
                'stage_start_date'=> '2026-06-01',
                'stage_end_date' => '2026-09-30',
                'stage_type'     => 'Stage de qualification',
                'stage_price'    => 8000.00,
                'currency'       => 'MAD',
                'status'         => 'termine',
                'notes'          => 'Stage terminé avec succès. Certificat délivré.',
            ],
            // Munich — Stagiaires
            [
                'center_code'    => 'MUNICH',
                'first_name'     => 'Omar',
                'last_name'      => 'Tazi',
                'date_of_birth'  => '1999-05-08',
                'place_of_birth' => 'Rabat',
                'gender'         => 'male',
                'nationality'    => 'Marocaine',
                'address'        => 'Leopoldstraße 44, München',
                'phone'          => '+49 89 11223344',
                'email'          => 'omar.tazi@email.de',
                'cin_number'     => 'AA112233',
                'cin_issued_date'=> '2019-04-01',
                'cin_issued_place'=> 'Rabat',
                'cin_recto_path' => 'stagiaires/cin/demo_recto.jpg',
                'stage_start_date'=> '2026-10-15',
                'stage_end_date' => '2027-04-15',
                'stage_type'     => 'Stage d\'initiation',
                'stage_price'    => 7500.00,
                'currency'       => 'MAD',
                'status'         => 'en_cours',
                'notes'          => 'Stage bien entamé. Présence régulière.',
            ],
            [
                'center_code'    => 'MUNICH',
                'first_name'     => 'Salma',
                'last_name'      => 'Benkirane',
                'date_of_birth'  => '2001-09-19',
                'place_of_birth' => 'Agadir',
                'gender'         => 'female',
                'nationality'    => 'Marocaine',
                'address'        => 'Maximilianstraße 100, München',
                'phone'          => '+49 89 44556677',
                'email'          => 'salma.benkirane@email.de',
                'cin_number'     => 'JA334455',
                'cin_issued_date'=> '2021-01-10',
                'cin_issued_place'=> 'Agadir',
                'cin_recto_path' => 'stagiaires/cin/demo_recto.jpg',
                'stage_start_date'=> '2026-09-01',
                'stage_end_date' => '2027-02-28',
                'stage_type'     => 'Stage de qualification',
                'stage_price'    => 11000.00,
                'currency'       => 'MAD',
                'status'         => 'en_cours',
                'notes'          => 'Excellente progression. Assiduité remarquable.',
            ],
        ];

        foreach ($stagiairesData as $sd) {
            $centerCode = $sd['center_code'];
            unset($sd['center_code']);

            $center = \App\Models\Center::where('code', $centerCode)->first();
            if (! $center) continue;

            \App\Models\Stagiaire::updateOrCreate(
                [
                    'center_id'  => $center->id,
                    'cin_number' => $sd['cin_number'],
                ],
                array_merge($sd, ['center_id' => $center->id])
            );
        }

        // ─────────────────────────────────────────────────────────────────
        // 21. Mise à jour des prix annuels de formations et domaines
        //     (pour que le dashboard financier affiche des montants réels)
        // ─────────────────────────────────────────────────────────────────
        $formationPrices = [
            // Berlin
            ['center_code' => 'BERLIN', 'code' => 'DE-A1',    'annual_price' => 9000.00,  'domain' => 'informatique'],
            ['center_code' => 'BERLIN', 'code' => 'DE-B1',    'annual_price' => 11800.00, 'domain' => 'informatique'],
            ['center_code' => 'BERLIN', 'code' => 'IT-BASIC', 'annual_price' => 6400.00,  'domain' => 'informatique'],
            // Munich
            ['center_code' => 'MUNICH', 'code' => 'DE-A1',    'annual_price' => 9800.00,  'domain' => 'sante'],
            ['center_code' => 'MUNICH', 'code' => 'EN-B2',    'annual_price' => 10400.00, 'domain' => 'sante'],
        ];

        foreach ($formationPrices as $fp) {
            $center = \App\Models\Center::where('code', $fp['center_code'])->first();
            if (! $center) continue;

            \App\Models\Formation::where('center_id', $center->id)
                ->where('code', $fp['code'])
                ->update([
                    'annual_price' => $fp['annual_price'],
                    'domain'       => $fp['domain'],
                ]);
        }

        $this->command->info('✅ Données démo complètes : Inscriptions, Paiements étudiants, Salaires professeurs, Stagiaires & Prix formations.');
    }
}






