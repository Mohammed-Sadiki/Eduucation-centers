<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Domain;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Photos des élèves dans le Plan de classe.
 *
 * Le Plan de classe affichait `asset('storage/' . $path)`, une URL du disque
 * PUBLIC, alors que les photos de profil sont enregistrées sur le disque PRIVÉ
 * (storage/app/private/students/…) : l'image était donc toujours cassée et
 * l'icône générique restait seule visible. La fiche « Détails de l'étudiant »,
 * elle, passe par Student::profilePhotoUrl() (contrôle d'existence réel du
 * fichier + route autorisée students.profile-photo).
 *
 * Contrat vérifié ici :
 *  - la photo d'un élève s'affiche dans son avatar, avec exactement la même URL
 *    que sur sa fiche ;
 *  - sans photo, ou avec un chemin de fichier absent → aucune balise img, donc
 *    l'avatar par défaut (aucune image cassée) ;
 *  - chaque élève voit SA photo (aucune inversion entre élèves) ;
 *  - identique pour le chargement initial et l'API AJAX ;
 *  - appliqué aux quatre centres et à toutes les formations ;
 *  - permissions et isolation inter-centres inchangées ;
 *  - design du plan de classe inchangé (taille, cercle, position, nom, pastille
 *    verte, carte professeur, espacements, responsive) ;
 *  - aucun champ photo ajouté, aucun fichier modifié.
 */
class ClassPlanStudentPhotoTest extends TestCase
{
    use RefreshDatabase;

    private const CITIES = [
        'tanger' => ['Centre Tanger', 'TGR', 'Tanger'],
        'agadir' => ['Centre Agadir', 'AGD', 'Agadir'],
        'essaouira' => ['Centre Essaouira', 'ESS', 'Essaouira'],
        'tetouane' => ['Centre Tétouane', 'TET', 'Tétouane'],
    ];

    private User $superAdmin;

    private int $registrationSeq = 0;

    /**
     * Contexte par centre : center, formation, classe, teacher, admin,
     * avecPhoto, sansPhoto, photoCassée.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $contexts = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');

        Domain::firstOrCreate(['slug' => 'sante'], [
            'name' => 'Santé', 'icon' => 'heart', 'color' => 'rose',
            'sort_order' => 1, 'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa-plan@test.ma']);

        foreach (self::CITIES as $key => [$name, $code, $city]) {
            $this->contexts[$key] = $this->seedCenter($name, $code, $city);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Un centre complet : formation, professeur, classe, trois élèves inscrits
     * (photo valide / sans photo / chemin de fichier inexistant).
     *
     * @return array<string, mixed>
     */
    private function seedCenter(string $name, string $code, string $city): array
    {
        $center = Center::factory()->create(['name' => $name, 'code' => $code, 'city' => $city]);
        $admin = User::factory()->centerAdmin($center->id)->create([
            'email' => strtolower($code).'-plan@test.ma',
        ]);

        $formation = Formation::create([
            'center_id' => $center->id,
            'domain' => 'sante',
            'name' => 'Auxiliaire de santé — '.$city,
            'code' => $code.'-SAN',
            'duration_hours' => 300,
            'price' => 3000,
            'status' => 'active',
        ]);

        $teacherUser = User::factory()->teacher($center->id)->create([
            'name' => 'Pr '.$city,
            'domain' => 'sante',
            'formation_id' => $formation->id,
        ]);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'center_id' => $center->id,
            'domain' => 'sante',
            'formation_id' => $formation->id,
            'teacher_number' => $code.'-T-001',
            'first_name' => 'Nadia',
            'last_name' => 'Alaoui',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $class = ClassRoom::create([
            'center_id' => $center->id,
            'formation_id' => $formation->id,
            'name' => 'Groupe A — '.$city,
            'code' => $code.'-A',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        $teacher->classes()->attach($class->id);

        return [
            'center' => $center,
            'admin' => $admin,
            'formation' => $formation,
            'teacher' => $teacher,
            'class' => $class,
            'avecPhoto' => $this->studentWithPhoto($center, $formation, $class, 'Karim', 'Benali'),
            'sansPhoto' => $this->enrolled($center, $formation, $class, 'Sonia', 'El Idrissi'),
            'photoCassée' => $this->studentWithMissingFile($center, $formation, $class, 'Youssef', 'Amrani'),
        ];
    }

    private function enrolled(Center $center, Formation $formation, ClassRoom $class, string $first, string $last): Student
    {
        $student = Student::factory()
            ->forCenter($center)
            ->withoutAccount()
            ->create(['first_name' => $first, 'last_name' => $last, 'status' => 'active']);

        Enrollment::create([
            'center_id' => $center->id,
            'student_id' => $student->id,
            'formation_id' => $formation->id,
            'class_id' => $class->id,
            'status' => Enrollment::STATUS_ACCEPTED,
            'registration_number' => 'INS-'.str_pad((string) (++$this->registrationSeq), 6, '0', STR_PAD_LEFT),
            'application_date' => '2026-10-01',
            'school_year' => '2026-2027',
        ]);

        return $student;
    }

    /**
     * Photo écrite sur le disque privé, dans l'arborescence réelle de
     * StudentController (students/{center}/{student}/profile/avatar.jpg).
     */
    private function studentWithPhoto(Center $center, Formation $formation, ClassRoom $class, string $first, string $last): Student
    {
        $student = $this->enrolled($center, $formation, $class, $first, $last);

        $path = Storage::disk('private')->putFileAs(
            "students/{$center->id}/{$student->id}/profile",
            UploadedFile::fake()->image('avatar.jpg', 160, 160),
            'avatar.jpg'
        );

        $student->update(['profile_photo_path' => $path]);

        return $student->fresh();
    }

    /**
     * Chemin photo enregistré en base mais fichier introuvable sur disque :
     * l'avatar par défaut doit prendre le relais, sans <img>.
     */
    private function studentWithMissingFile(Center $center, Formation $formation, ClassRoom $class, string $first, string $last): Student
    {
        $student = $this->enrolled($center, $formation, $class, $first, $last);

        $student->update([
            'profile_photo_path' => "students/{$center->id}/{$student->id}/profile/disque-efface.jpg",
        ]);

        return $student->fresh();
    }

    /**
     * HTML du plan de classe, URLs normalisées (@json échappe les « / »).
     *
     * @param  array<string, mixed>  $context
     */
    private function classPlanHtml(array $context, User $actor): string
    {
        $response = $this->actingAs($actor)->get(route('class-plan.index', [
            'teacher_id' => $context['teacher']->id,
            'class_id' => $context['class']->id,
        ]));

        $response->assertOk();

        return str_replace('\\/', '/', $response->getContent());
    }

    /** Élèves renvoyés par l'API AJAX du plan de classe. */
    private function apiStudents(array $context, User $actor): Collection
    {
        $response = $this->actingAs($actor)
            ->getJson(route('class-plan.class.students', $context['class']->id))
            ->assertOk();

        return collect($response->json('students'));
    }

    /** Le plan de classe attribue-t-il une URL de photo à cet élève ? */
    private function photoUrlInPlan(string $html, Student $student): bool
    {
        return str_contains($html, '/students/'.$student->id.'/profile-photo');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. La photo s'affiche dans l'avatar
    // ──────────────────────────────────────────────────────────────────────────

    public function test_a_student_with_a_photo_shows_it_in_the_class_plan(): void
    {
        $context = $this->contexts['tanger'];
        $html = $this->classPlanHtml($context, $context['admin']);

        $this->assertTrue(
            $this->photoUrlInPlan($html, $context['avecPhoto']),
            'L\'avatar de l\'élève doit pointer sur sa photo de profil.'
        );
        $this->assertStringNotContainsString(
            '/storage/students/',
            $html,
            'Plus aucune URL du disque public : la photo privée est servie par la route sécurisée.'
        );
    }

    public function test_the_class_plan_uses_the_exact_same_photo_as_the_student_details_page(): void
    {
        $context = $this->contexts['tanger'];
        $student = $context['avecPhoto'];

        $detailsHtml = $this->actingAs($context['admin'])
            ->get(route('students.show', $student))
            ->assertOk()
            ->getContent();

        preg_match('#<img src="([^"]+)"[^>]*class="h-28 w-28#s', $detailsHtml, $m);
        $this->assertNotEmpty($m[1] ?? '', 'La fiche étudiant affiche bien la photo.');

        $planHtml = $this->classPlanHtml($context, $context['admin']);

        $this->assertSame(
            parse_url($m[1], PHP_URL_PATH),
            '/students/'.$student->id.'/profile-photo',
            'Le plan de classe doit reprendre l\'URL de la fiche.'
        );
        $this->assertTrue($this->photoUrlInPlan($planHtml, $student));
    }

    public function test_the_photo_file_is_actually_served_for_the_class_plan_viewer(): void
    {
        $context = $this->contexts['agadir'];
        $student = $context['avecPhoto'];

        foreach (['admin centre' => $context['admin'], 'super admin' => $this->superAdmin] as $label => $actor) {
            $this->actingAs($actor)
                ->get(route('students.profile-photo', $student))
                ->assertOk()
                ->assertHeader('Content-Type', 'image/jpeg');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Fallbacks : pas de photo, fichier introuvable
    // ──────────────────────────────────────────────────────────────────────────

    public function test_a_student_without_photo_gets_no_image_url_at_all(): void
    {
        $context = $this->contexts['tanger'];
        $html = $this->classPlanHtml($context, $context['admin']);

        $this->assertFalse(
            $this->photoUrlInPlan($html, $context['sansPhoto']),
            'Aucune URL de photo ne doit être émise sans photo : l\'avatar par défaut reste affiché.'
        );
    }

    public function test_a_student_with_a_missing_photo_file_falls_back_to_the_default_avatar(): void
    {
        $context = $this->contexts['tanger'];
        $broken = $context['photoCassée'];

        $this->assertNotNull($broken->profile_photo_path, 'Le chemin reste enregistré en base.');
        Storage::disk('private')->assertMissing($broken->profile_photo_path);

        $html = $this->classPlanHtml($context, $context['admin']);

        $this->assertFalse(
            $this->photoUrlInPlan($html, $broken),
            'Fichier introuvable → pas de <img>, donc jamais d\'image cassée.'
        );
        $this->assertStringNotContainsString('disque-efface.jpg', $html);
    }

    public function test_the_api_endpoint_reports_null_for_both_fallback_cases(): void
    {
        $context = $this->contexts['essaouira'];
        $students = $this->apiStudents($context, $context['admin']);

        $byId = $students->keyBy('id');

        $this->assertStringContainsString(
            '/profile-photo',
            $byId[$context['avecPhoto']->id]['profile_photo_url'] ?? '',
            'L\'AJAX doit renvoyer la même URL sécurisée que le rendu initial.'
        );
        $this->assertNull($byId[$context['sansPhoto']->id]['profile_photo_url']);
        $this->assertNull($byId[$context['photoCassée']->id]['profile_photo_url']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Un élève = sa propre photo
    // ──────────────────────────────────────────────────────────────────────────

    public function test_each_student_of_a_class_gets_his_own_photo_and_nobody_elses(): void
    {
        $context = $this->contexts['tanger'];
        $class = $context['class'];
        $formation = $context['formation'];
        $center = $context['center'];

        $others = collect(['Aya', 'Imane', 'Hicham'])
            ->map(fn (string $first) => $this->studentWithPhoto($center, $formation, $class, $first, 'Photogénique'));

        $html = $this->classPlanHtml($context, $context['admin']);
        $api = $this->apiStudents($context, $this->superAdmin)->keyBy('id');

        $withPhoto = $others->push($context['avecPhoto']);

        foreach ($withPhoto as $student) {
            $this->assertTrue(
                $this->photoUrlInPlan($html, $student),
                'Chaque élève photographié doit avoir sa propre URL ('.$student->first_name.').'
            );
            $this->assertSame(
                '/students/'.$student->id.'/profile-photo',
                parse_url($api[$student->id]['profile_photo_url'], PHP_URL_PATH),
                'L\'URL doit référencer l\'élève lui-même, pas un voisin.'
            );
        }

        // Une seule occurrence d'URL de photo par élève photographié.
        $this->assertSame(
            $withPhoto->count(),
            substr_count($html, '/profile-photo'),
            'Pas de doublon ni d\'inversion dans la liste embarquée.'
        );

        $this->assertFalse($this->photoUrlInPlan($html, $context['sansPhoto']));
        $this->assertFalse($this->photoUrlInPlan($html, $context['photoCassée']));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Les quatre centres, toutes formations
    // ──────────────────────────────────────────────────────────────────────────

    #[DataProvider('centerProvider')]
    public function test_photos_display_the_same_way_in_every_center(string $centerKey): void
    {
        $context = $this->contexts[$centerKey];
        $html = $this->classPlanHtml($context, $context['admin']);

        $this->assertTrue($this->photoUrlInPlan($html, $context['avecPhoto']));
        $this->assertFalse($this->photoUrlInPlan($html, $context['sansPhoto']));
        $this->assertFalse($this->photoUrlInPlan($html, $context['photoCassée']));
        $this->assertStringNotContainsString('/storage/students/', $html);
    }

    /** @return array<string, array{0: string}> */
    public static function centerProvider(): array
    {
        return [
            'Tanger' => ['tanger'],
            'Agadir' => ['agadir'],
            'Essaouira' => ['essaouira'],
            'Tétouane' => ['tetouane'],
        ];
    }

    public function test_a_second_class_of_a_second_formation_shows_the_same_behaviour(): void
    {
        $context = $this->contexts['tetouane'];
        $center = $context['center'];

        $formation2 = Formation::create([
            'center_id' => $center->id,
            'domain' => 'sante',
            'name' => 'Soins infirmiers — Tétouane',
            'code' => 'TET-INF',
            'duration_hours' => 400,
            'price' => 4000,
            'status' => 'active',
        ]);

        $class2 = ClassRoom::create([
            'center_id' => $center->id,
            'formation_id' => $formation2->id,
            'name' => 'Groupe B — Tétouane',
            'code' => 'TET-B',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        $context['teacher']->classes()->attach($class2->id);

        $student = $this->studentWithPhoto($center, $formation2, $class2, 'Rachid', 'Belkadi');

        $html = $this->classPlanHtml($context, $context['admin']);

        // Le professeur a deux classes : on cible la nouvelle via le paramètre.
        $htmlWithSecondClass = str_replace('\\/', '/', $this->actingAs($context['admin'])
            ->get(route('class-plan.index', [
                'teacher_id' => $context['teacher']->id,
                'class_id' => $class2->id,
            ]))
            ->assertOk()
            ->getContent());

        $this->assertTrue($this->photoUrlInPlan($htmlWithSecondClass, $student));
        $this->assertStringNotContainsString('/storage/students/', $htmlWithSecondClass);
        $this->assertFileHasNotBeenTouched($student);
    }

    private function assertFileHasNotBeenTouched(Student $student): void
    {
        Storage::disk('private')->assertExists($student->profile_photo_path);
        $this->assertStringContainsString('/profile/avatar.jpg', $student->profile_photo_path);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Permissions et isolation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_the_photo_keeps_the_same_protection_as_the_student_dossier(): void
    {
        $tanger = $this->contexts['tanger'];
        $agadir = $this->contexts['agadir'];
        $url = route('students.profile-photo', $tanger['avecPhoto']);

        $this->actingAs($tanger['admin'])->get($url)->assertOk();
        $this->actingAs($this->superAdmin)->get($url)->assertOk();
        $this->actingAs($agadir['admin'])->get($url)->assertForbidden();
    }

    public function test_the_photo_is_never_served_without_a_session(): void
    {
        // Aucun actingAs préalable : requête réellement invitée.
        $this->get(route('students.profile-photo', $this->contexts['tanger']['avecPhoto']))
            ->assertRedirect();

        $this->assertFalse($this->app['auth']->guard('web')->check());
    }

    public function test_a_center_admin_cannot_read_another_centers_class_plan_or_its_photos(): void
    {
        $tanger = $this->contexts['tanger'];

        $this->actingAs($this->contexts['agadir']['admin'])
            ->getJson(route('class-plan.class.students', $tanger['class']->id))
            ->assertForbidden();

        // La page, elle, ne liste que les professeurs du centre : la classe
        // d'un autre centre n'est jamais embarquée.
        $html = $this->classPlanHtml($this->contexts['agadir'], $this->contexts['agadir']['admin']);
        $this->assertFalse($this->photoUrlInPlan($html, $tanger['avecPhoto']));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Design et données inchangés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_the_class_plan_design_is_untouched_apart_from_the_photo_source(): void
    {
        $blade = file_get_contents(base_path('resources/views/class-plan/index.blade.php'));

        // Avatar : même cercle, même taille, overflow masqué, photo couvrante.
        $this->assertStringContainsString('h-16 w-16 rounded-full', $blade);
        $this->assertStringContainsString('overflow-hidden', $blade);
        $this->assertStringContainsString('h-full w-full object-cover', $blade);

        // Élève : numéro de siège, prénom/nom sous l'avatar, pastille verte.
        $this->assertStringContainsString('text-2xs font-bold text-slate-300', $blade);
        $this->assertStringContainsString('max-w-[90px]', $blade);
        $this->assertStringContainsString('bg-emerald-400', $blade);

        // Carte professeur conservée (initiales, bureau, dimensions).
        $this->assertStringContainsString('x-text="teacherInitials"', $blade);
        $this->assertStringContainsString('min-w-[200px] max-w-xs', $blade);

        // Disposition et responsive de la salle conservés.
        $this->assertStringContainsString('classroom-container', $blade);
        $this->assertStringContainsString(
            'grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6',
            $blade
        );

        // Le repli en cas d'image inaccessible reste câblé.
        $this->assertStringContainsString('onerror="this.style.display=', $blade);
        $this->assertStringContainsString('x-if="student.profile_photo_url"', $blade);
    }

    public function test_no_new_photo_field_and_no_existing_photo_was_modified(): void
    {
        $photoColumns = array_values(array_filter(
            Schema::getColumnListing('students'),
            fn (string $column): bool => str_contains($column, 'photo')
        ));

        $this->assertSame(['profile_photo_path'], $photoColumns, 'Aucun champ photo supplémentaire créé.');

        $student = $this->contexts['tanger']['avecPhoto'];
        $path = $student->profile_photo_path;

        $this->classPlanHtml($this->contexts['tanger'], $this->contexts['tanger']['admin']);
        $this->actingAs($this->contexts['tanger']['admin'])
            ->get(route('students.profile-photo', $student))
            ->assertOk();

        $this->assertSame($path, $student->fresh()->profile_photo_path, 'Le chemin enregistré ne bouge pas.');
        Storage::disk('private')->assertExists($path);
    }

    public function test_the_class_plan_still_lists_only_accepted_students(): void
    {
        $context = $this->contexts['tanger'];

        $pending = Student::factory()->forCenter($context['center'])->withoutAccount()->create([
            'first_name' => 'Ajouté', 'last_name' => 'En attente',
        ]);

        Enrollment::create([
            'center_id' => $context['center']->id,
            'student_id' => $pending->id,
            'formation_id' => $context['formation']->id,
            'class_id' => $context['class']->id,
            'status' => Enrollment::STATUS_PENDING,
            'registration_number' => 'INS-'.str_pad((string) (++$this->registrationSeq), 6, '0', STR_PAD_LEFT),
            'application_date' => '2026-10-01',
            'school_year' => '2026-2027',
        ]);

        $ids = $this->apiStudents($context, $context['admin'])->pluck('id')->all();

        $this->assertNotContains($pending->id, $ids, 'La liste des élèves n\'a pas changé de périmètre.');
        $this->assertContains($context['avecPhoto']->id, $ids);
    }
}
