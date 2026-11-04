<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Formation;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AcademicPerimeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Mission « harmonisation globale » — tests de régression de l'architecture.
 *
 * L'application ne doit plus exposer que l'architecture
 * « Centre → Formation générale → Classe → Élève ». Ces tests verrouillent les
 * six règles obtenues après centralisation dans {@see AcademicPerimeter} :
 *
 *  1. un sélecteur de classes ne propose que les classes de la formation
 *     générale active (« Informatique · Groupe A / Groupe B ») ;
 *  2. une ancienne sous-formation n'est jamais proposé sélectionnable, ni comme
 *     classe, ni comme formation, ni comme libellé visible ;
 *  3. les tableaux (évaluations, élèves) obéissent exactement aux mêmes filtres
 *     que les menus déroulants — le filtrage est fait dans la requête ;
 *  4. isolation stricte entre centres ;
 *  5. un professeur ne voit que ses classes affectées ;
 *  6. les écrites sont rattachées à la formation générale, jamais à l'alias.
 *
 * Les libellés des classes portent encore le nom de l'ancienne sous-formation
 * dans la colonne `name` : c'est précisément ce qui doit rester invisible.
 */
class ArchitectureHarmonizationTest extends TestCase
{
    use RefreshDatabase;

    protected Center $casablanca;

    protected Center $tanger;

    protected Formation $info;

    protected Formation $sante;

    protected Formation $web;

    protected Formation $cyber;

    protected Formation $nurse;

    protected Formation $infoTanger;

    protected ClassRoom $classWebA;

    protected ClassRoom $classCyberB;

    protected ClassRoom $classNurse;

    protected ClassRoom $classTanger;

    protected Evaluation $evalWeb;

    protected Evaluation $evalCyber;

    protected Evaluation $evalNurse;

    protected Evaluation $evalTanger;

    protected Student $studentLea;

    protected Student $studentOmar;

    protected Room $room;

    protected Schedule $scheduleWeb;

    protected Schedule $scheduleNurse;

    protected User $superAdmin;

    protected User $adminCasablanca;

    protected User $adminTanger;

    protected Teacher $teacher;

    protected Teacher $teacherSante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->casablanca = Center::factory()->create(['name' => 'Centre Casablanca', 'code' => 'CAS']);
        $this->tanger = Center::factory()->create(['name' => 'Centre Tanger', 'code' => 'TNG']);

        // Formations générales officielles (la seule hiérarchie visible).
        $this->info = $this->officialFormation($this->casablanca, 'informatique', 'Informatique', 'OFF-INF');
        $this->sante = $this->officialFormation($this->casablanca, 'sante', 'Santé', 'OFF-SAN');
        $this->infoTanger = $this->officialFormation($this->tanger, 'informatique', 'Informatique', 'OFF-INT');

        // Anciennes sous-formations : jamais sélectionnables, seulement des
        // alias techniques des formations générales ci-dessus.
        $this->web = $this->legacyFormation($this->casablanca, 'informatique', 'Développement Web Full Stack', 'LEG-WEB');
        $this->cyber = $this->legacyFormation($this->casablanca, 'informatique', 'Cybersécurité', 'LEG-CYB');
        $this->nurse = $this->legacyFormation($this->casablanca, 'sante', 'Soins infirmiers', 'LEG-NUR');

        // Classes encore rattachées aux alias (données non migrées, cas réel).
        $this->classWebA = $this->legacyClass($this->web, 'Développement Web Full Stack · Groupe A', 'CLS-WEB-A');
        $this->classCyberB = $this->legacyClass($this->cyber, 'Cybersécurité · Groupe B', 'CLS-CYB-B');
        $this->classNurse = $this->legacyClass($this->nurse, 'Soins infirmiers · Groupe A', 'CLS-NUR-A');
        $this->classTanger = $this->officialClass($this->infoTanger, 'Groupe A', 'CLS-TNG-A');

        $this->evalWeb = $this->makeEvaluation($this->classWebA, $this->web, 'Examen Web Session 1');
        $this->evalCyber = $this->makeEvaluation($this->classCyberB, $this->cyber, 'Contrôle Cyber Session 2');
        $this->evalNurse = $this->makeEvaluation($this->classNurse, $this->nurse, 'Oral Soins Session 3');
        $this->evalTanger = $this->makeEvaluation($this->classTanger, $this->infoTanger, 'Examen Tanger Session 1');

        // Élèves : un par formation générale, inscrits via les alias.
        $this->studentLea = Student::factory()->withoutAccount()->forCenter($this->casablanca)->create([
            'student_number' => 'ST-LEA', 'first_name' => 'Lea', 'last_name' => 'Martin',
        ]);
        $this->studentOmar = Student::factory()->withoutAccount()->forCenter($this->casablanca)->create([
            'student_number' => 'ST-OMAR', 'first_name' => 'Omar', 'last_name' => 'Benali',
        ]);

        $this->makeEnrollment($this->studentLea, $this->web, $this->classWebA);
        $this->makeEnrollment($this->studentOmar, $this->nurse, $this->classNurse);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@harmonisation.test']);
        $this->adminCasablanca = User::factory()->centerAdmin($this->casablanca->id)->create(['email' => 'admin-cas@harmonisation.test']);
        $this->adminTanger = User::factory()->centerAdmin($this->tanger->id)->create(['email' => 'admin-tng@harmonisation.test']);

        // Professeur d'informatique n'ayant qu'UNE classe affectée.
        $this->teacher = Teacher::factory()->forCenter($this->casablanca)->create([
            'teacher_number' => 'TEA-INFO', 'domain' => 'informatique',
        ]);
        // Professeur d'une autre formation générale, jamais affecté aux classes
        // d'informatique.
        $this->teacherSante = Teacher::factory()->forCenter($this->casablanca)->create([
            'teacher_number' => 'TEA-SANTE', 'first_name' => 'Nadia', 'last_name' => 'El Amrani', 'domain' => 'sante',
        ]);
        $this->classWebA->teachers()->attach($this->teacher->id);
        $this->evalWeb->update(['teacher_id' => $this->teacher->id]);
        $this->evalCyber->update(['teacher_id' => $this->teacher->id]);

        $this->room = Room::create([
            'center_id' => $this->casablanca->id,
            'name' => 'Salle 1',
            'code' => 'R-1',
            'capacity' => 20,
            'status' => 'active',
        ]);

        // Séances : une par formation générale, à la même date.
        $this->scheduleWeb = $this->makeSchedule($this->classWebA, $this->teacher);
        $this->scheduleNurse = $this->makeSchedule($this->classNurse, $this->teacherSante);

        Attendance::create([
            'center_id' => $this->casablanca->id,
            'student_id' => $this->studentLea->id,
            'class_id' => $this->classWebA->id,
            'schedule_id' => $this->scheduleWeb->id,
            'date' => '2026-11-10',
            'status' => 'present',
        ]);
        Attendance::create([
            'center_id' => $this->casablanca->id,
            'student_id' => $this->studentOmar->id,
            'class_id' => $this->classNurse->id,
            'schedule_id' => $this->scheduleNurse->id,
            'date' => '2026-11-10',
            'status' => 'absent',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  1 & 2. Sélecteur de classes : la formation générale active uniquement
    // ──────────────────────────────────────────────────────────────────────────

    public function test_class_filter_in_formation_space_offers_only_that_formation_classes(): void
    {
        $response = $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->get(route('evaluations.index'))
            ->assertOk();

        /** @var Collection<int, ClassRoom> $classes */
        $classes = $response->viewData('classes');

        $this->assertEqualsCanonicalizing(
            [$this->classWebA->id, $this->classCyberB->id],
            $classes->pluck('id')->all(),
            'Le filtre de classe ne doit proposer que les classes de la formation générale active.'
        );

        $this->assertSame(
            ['Informatique · Groupe A', 'Informatique · Groupe B'],
            $classes->map(fn (ClassRoom $c): string => $c->perimeter_label)->sort()->values()->all()
        );
    }

    public function test_class_filter_never_shows_a_legacy_sub_formation_label(): void
    {
        $html = $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->get(route('evaluations.index'))
            ->assertOk()
            ->getContent();

        foreach (['Développement Web Full Stack', 'Cybersécurité', 'Soins infirmiers'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $html, "L'ancienne sous-formation « {$legacy} » est encore visible.");
        }
    }

    public function test_classes_of_the_whole_center_are_offered_outside_any_formation_space(): void
    {
        $response = $this->actingAs($this->adminCasablanca)
            ->get(route('evaluations.index'))
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->classWebA->id, $this->classCyberB->id, $this->classNurse->id],
            $response->viewData('classes')->pluck('id')->all()
        );

        $this->assertStringNotContainsString(
            'Examen Tanger',
            $response->getContent(),
            'Un Admin Centre ne doit jamais voir les données d\'un autre centre.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  3. Le tableau obéit exactement au filtre (corrigé dans la requête)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_evaluation_table_matches_the_class_filter(): void
    {
        $response = $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->get(route('evaluations.index'))
            ->assertOk();

        $selectable = $response->viewData('classes')->pluck('id')->all();
        $tableClassIds = $response->viewData('evaluations')->collect()
            ->pluck('class_id')
            ->unique()
            ->all();

        $this->assertNotEmpty($tableClassIds);
        $this->assertEmpty(
            array_diff($tableClassIds, $selectable),
            'Le tableau affiche des classes que le filtre ne propose pas : le filtrage n\'est pas dans la requête.'
        );

        $titles = $response->viewData('evaluations')->collect()->pluck('title')->all();
        $this->assertContains('Examen Web Session 1', $titles);
        $this->assertContains('Contrôle Cyber Session 2', $titles);
        $this->assertNotContains('Oral Soins Session 3', $titles);
        $this->assertNotContains('Examen Tanger Session 1', $titles);
    }

    public function test_student_table_respects_the_active_formation_space(): void
    {
        $response = $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->get(route('students.index'))
            ->assertOk();

        $numbers = $response->viewData('students')->collect()->pluck('student_number')->all();

        $this->assertContains('ST-LEA', $numbers, 'L\'élève inscrit dans la formation générale active doit rester visible.');
        $this->assertNotContains('ST-OMAR', $numbers, 'Un élève d\'une autre formation générale ne doit pas apparaître.');
    }

    public function test_super_admin_keeps_global_visibility_outside_any_space(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('evaluations.index'))
            ->assertOk();

        $titles = $response->viewData('evaluations')->collect()->pluck('title')->all();

        foreach (['Examen Web Session 1', 'Contrôle Cyber Session 2', 'Oral Soins Session 3', 'Examen Tanger Session 1'] as $title) {
            $this->assertContains($title, $titles, 'La vue globale du Super Admin ne doit pas être restreinte.');
        }
    }

    public function test_attendance_filter_and_table_share_the_active_formation_space(): void
    {
        $response = $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->get(route('attendances.index', ['date' => '2026-11-10']))
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->classWebA->id, $this->classCyberB->id],
            $response->viewData('classes')->pluck('id')->all()
        );

        $listed = $response->viewData('attendances')->collect()->pluck('class_id')->unique()->all();

        $this->assertSame([$this->classWebA->id], $listed, 'La liste de présences doit suivre exactement le filtre.');
        $this->assertStringNotContainsString('Soins infirmiers', $response->getContent());
    }

    public function test_teacher_list_follows_the_active_formation_space(): void
    {
        // Demande large d'abord : hors espace de formation, tous les professeurs
        // du centre restent accessibles.
        $all = $this->actingAs($this->adminCasablanca)
            ->get(route('teachers.index'))
            ->assertOk()
            ->viewData('teachers')
            ->collect()
            ->pluck('teacher_number')
            ->all();

        $this->assertContains('TEA-INFO', $all);
        $this->assertContains('TEA-SANTE', $all);

        // Dans l'espace « Informatique », seuls les professeurs de cette
        // formation générale sont listés — comme dans le menu déroulant.
        $inInformatique = $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->get(route('teachers.index'))
            ->assertOk()
            ->viewData('teachers')
            ->collect()
            ->pluck('teacher_number')
            ->all();

        $this->assertContains('TEA-INFO', $inInformatique);
        $this->assertNotContains('TEA-SANTE', $inInformatique);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  4. Isolation entre centres, même par requête directe
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_cannot_reach_another_center_rows_by_id(): void
    {
        $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->get(route('evaluations.index', ['class_id' => $this->classTanger->id]))
            ->assertOk()
            ->assertDontSee('Examen Tanger Session 1');

        // Réciproque : l'Admin Tanger ne voit aucune donnée de Casablanca.
        $this->actingAs($this->adminTanger)
            ->get(route('evaluations.index'))
            ->assertOk()
            ->assertSee('Examen Tanger Session 1')
            ->assertDontSee('Examen Web Session 1')
            ->assertDontSee('Oral Soins Session 3');
    }

    public function test_public_catalogue_lists_only_general_formations(): void
    {
        $this->get(route('public.formations'))
            ->assertOk()
            ->assertSee('Informatique')
            ->assertSee('Santé')
            ->assertDontSee('Développement Web Full Stack')
            ->assertDontSee('Cybersécurité')
            ->assertDontSee('Soins infirmiers');
    }

    public function test_public_formation_page_shows_the_classes_of_the_general_formation(): void
    {
        $this->get(route('public.formations.show', $this->info))
            ->assertOk()
            ->assertSee('Groupe A')
            ->assertSee('Groupe B')
            ->assertDontSee('Développement Web Full Stack')
            ->assertDontSee('Cybersécurité');

        // Une ancienne sous-formation n'est plus une page de catalogue : elle
        // n'est plus une formation courante.
        $this->get(route('public.formations.show', $this->web))->assertNotFound();
    }

    public function test_legacy_sub_formations_are_never_selectable_in_finance_forms(): void
    {
        $response = $this->actingAs($this->adminCasablanca)
            ->get(route('payments.create'))
            ->assertOk();

        $names = collect($response->viewData('formations'))->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Informatique', 'Santé'], $names);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  5. Professeur : uniquement ses classes affectées
    // ──────────────────────────────────────────────────────────────────────────

    public function test_teacher_is_limited_to_assigned_classes_and_own_evaluations(): void
    {
        $response = $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->teacher->user)
            ->get(route('evaluations.index'))
            ->assertOk();

        $this->assertSame(
            [$this->classWebA->id],
            $response->viewData('classes')->pluck('id')->all(),
            'Un professeur ne doit voir que les classes qui lui sont affectées.'
        );

        $titles = $response->viewData('evaluations')->collect()->pluck('title')->all();
        $this->assertSame(['Examen Web Session 1'], $titles);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  6. Écritures conformes : le serveur refuse et rattache correctement
    // ──────────────────────────────────────────────────────────────────────────

    public function test_server_rejects_a_class_outside_the_active_formation_space(): void
    {
        $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->post(route('evaluations.store'), [
                'class_id' => $this->classNurse->id,
                'title' => 'Evaluation hors perimetre',
                'type' => 'exam',
                'evaluation_date' => '2026-11-15',
                'max_grade' => 20,
                'coefficient' => 1,
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('class_id');

        $this->assertDatabaseMissing('evaluations', ['title' => 'Evaluation hors perimetre']);
    }

    public function test_server_rejects_a_class_of_another_center(): void
    {
        $this->actingAs($this->adminCasablanca)
            ->post(route('evaluations.store'), [
                'class_id' => $this->classTanger->id,
                'title' => 'Evaluation inter-centres',
                'type' => 'exam',
                'evaluation_date' => '2026-11-15',
                'max_grade' => 20,
                'coefficient' => 1,
                'status' => 'draft',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('evaluations', ['title' => 'Evaluation inter-centres']);
    }

    public function test_new_evaluation_is_attached_to_the_general_formation_not_the_alias(): void
    {
        $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->post(route('evaluations.store'), [
                'class_id' => $this->classWebA->id,
                'title' => 'Nouvelle evaluation',
                'type' => 'control',
                'evaluation_date' => '2026-11-20',
                'max_grade' => 20,
                'coefficient' => 1,
                'status' => 'draft',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('evaluations', [
            'title' => 'Nouvelle evaluation',
            'class_id' => $this->classWebA->id,
            'formation_id' => $this->info->id,
            'center_id' => $this->casablanca->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  7. Restitutions dérivées : calendrier et préremplissages financiers
    // ──────────────────────────────────────────────────────────────────────────

    public function test_schedule_calendar_displays_the_general_formation_and_group(): void
    {
        $response = $this->withSession(['active_domain' => 'informatique'])
            ->actingAs($this->adminCasablanca)
            ->get(route('schedules.index', ['date' => '2026-11-10']))
            ->assertOk();

        $event = collect($response->viewData('events'))->firstWhere('id', (string) $this->scheduleWeb->id);

        $this->assertNotNull($event, 'La séance du jour doit être remontée par le calendrier.');
        $this->assertSame('Informatique · Groupe A', $event['title']);
        $this->assertSame('Informatique · Groupe A', $event['extendedProps']['className']);

        // La séance de l'autre formation générale n'est pas dans cet espace.
        $this->assertNull(collect($response->viewData('events'))->firstWhere('id', (string) $this->scheduleNurse->id));
        $this->assertStringNotContainsString('Développement Web Full Stack', $response->getContent());
    }

    public function test_finance_payment_modal_is_prefilled_with_the_general_formation(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.finance.students'))
            ->assertOk();

        $row = $response->viewData('studentFinancialMap')->get($this->studentLea->id);

        $this->assertNotNull($row, 'L\'élève inscrit doit être proposé dans le modal de paiement.');

        // L'inscription est portée par une ancienne sous-formation : seules la
        // formation générale et sa clé sélectionnable peuvent être renvoyées.
        $this->assertSame($this->info->id, $row['formation_id']);
        $this->assertSame('Informatique', $row['formation_name']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Autorité unique : un seul mécanisme de filtrage pour tous les modules
    // ──────────────────────────────────────────────────────────────────────────

    public function test_perimeter_resolves_legacy_alias_to_its_general_formation(): void
    {
        $perimeter = AcademicPerimeter::for($this->adminCasablanca, $this->casablanca->id, 'informatique');

        $this->assertEqualsCanonicalizing(
            [$this->info->id, $this->web->id, $this->cyber->id],
            $perimeter->formationIds()
        );

        // L'espace de formation ne propose que la formation générale.
        $this->assertSame([$this->info->id], $perimeter->formationsQuery()->pluck('id')->all());
        $this->assertTrue($perimeter->isSelectableFormation($this->info->id));
        $this->assertFalse($perimeter->isSelectableFormation($this->web->id));

        // Filtrer sur l'alias ou sur la formation générale donne le même jeu.
        $this->assertEqualsCanonicalizing($perimeter->formationIds(), $perimeter->formationIds($this->web->id));
        $this->assertTrue($perimeter->allowsClass($this->classWebA->id));
        $this->assertFalse($perimeter->allowsClass($this->classNurse->id));
    }

    public function test_no_visible_label_still_uses_the_word_domaine(): void
    {
        $pattern = "/(?:__|@lang|trans|trans_choice)\(\s*'((?:[^'\\\\]|\\\\.)*)'/u";
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $line) {
                // Les commentaires de Blade ne sont jamais rendus.
                if (str_contains($line, '{{--')) {
                    continue;
                }

                if (preg_match_all($pattern, $line, $matches)) {
                    foreach ($matches[1] as $label) {
                        if (preg_match('/\bdomaine[s]?\b/iu', $label)) {
                            $offenders[] = $file->getFilename().': '.$label;
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Le vocabulaire « Domaine » doit avoir disparu de l'interface : ".implode(' | ', $offenders)
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Fixture
    // ──────────────────────────────────────────────────────────────────────────

    private function officialFormation(Center $center, string $domain, string $name, string $code): Formation
    {
        return Formation::factory()->official($domain)->forCenter($center)->forDomain($domain)->create([
            'name' => $name,
            'code' => $code,
            'status' => 'active',
        ]);
    }

    private function legacyFormation(Center $center, string $domain, string $name, string $code): Formation
    {
        return Formation::factory()->forCenter($center)->forDomain($domain)->create([
            'name' => $name,
            'code' => $code,
            'is_official' => false,
            'official_code' => null,
            'status' => 'active',
        ]);
    }

    private function legacyClass(Formation $formation, string $name, string $code): ClassRoom
    {
        return $this->makeClass($formation, $name, $code);
    }

    private function officialClass(Formation $formation, string $name, string $code): ClassRoom
    {
        return $this->makeClass($formation, $name, $code);
    }

    private function makeClass(Formation $formation, string $name, string $code): ClassRoom
    {
        return ClassRoom::factory()->forFormation($formation)->create([
            'center_id' => $formation->center_id,
            'name' => $name,
            'code' => $code,
            'status' => 'active',
        ]);
    }

    private function makeEvaluation(ClassRoom $class, Formation $formation, string $title): Evaluation
    {
        return Evaluation::create([
            'center_id' => $class->center_id,
            'class_id' => $class->id,
            'formation_id' => $formation->id,
            'title' => $title,
            'type' => 'exam',
            'evaluation_date' => '2026-10-10',
            'max_grade' => 20,
            'coefficient' => 1,
            'status' => 'published',
        ]);
    }

    private function makeEnrollment(Student $student, Formation $formation, ClassRoom $class): Enrollment
    {
        return Enrollment::create([
            'center_id' => $student->center_id,
            'student_id' => $student->id,
            'formation_id' => $formation->id,
            'class_id' => $class->id,
            'registration_number' => 'REG-'.$student->student_number,
            'application_date' => '2026-09-01',
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);
    }

    private function makeSchedule(ClassRoom $class, Teacher $teacher): Schedule
    {
        return Schedule::create([
            'center_id' => $class->center_id,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'room_id' => $this->room->id,
            'date' => '2026-11-10',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'type' => 'course',
            'status' => 'scheduled',
        ]);
    }
}
