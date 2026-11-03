<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Photo de profil des étudiants — page « Détails de l'étudiant ».
 *
 * Cause réelle du bug : StudentController::store() enregistre la photo sur le
 * disque PRIVÉ (storage/app/private) alors que la vue construisait
 * asset('storage/' . $path), une URL du disque PUBLIC (storage/app/public,
 * exposé par le lien public/storage). L'URL ne désignait donc jamais le fichier
 * → image cassée affichée sous forme de texte alternatif.
 *
 * Correction : la photo privée est servie par la route sécurisée
 * students.profile-photo (même politique que la page détails), la vue n'affiche
 * <img> que si le fichier existe réellement (sinon l'avatar par défaut, design
 * inchangé), et le disque public conserve son URL directe pour les fichiers qui
 * y sont réellement stockés.
 *
 * Aucune donnée, aucun fichier, aucun formulaire n'est modifié : l'affichage
 * uniquement.
 *
 * Note sur les comparaisons d'URL : route() est construit depuis APP_URL
 * (http://localhost:8000) alors que le HTML rendu part de l'hôte de la requête
 * (http://localhost). On compare donc les chemins, comme dans les autres tests
 * multi-rôles du projet.
 */
class StudentProfilePhotoDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Center $berlin;

    private Center $munich;

    private User $superAdmin;

    private User $adminBerlin;

    private User $adminMunich;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');

        $this->berlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->munich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin  = User::factory()->superAdmin()->create(['email' => 'sa-photo@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->berlin->id)->create(['email' => 'ab-photo@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->munich->id)->create(['email' => 'am-photo@test.de']);
    }

    /**
     * Dossier étudiant avec photo réellement écrite sur le disque privé, dans
     * l'arborescence utilisée par StudentController::store().
     */
    private function studentWithPhoto(Center $center, string $lastName = 'Benali'): Student
    {
        $student = Student::factory()
            ->forCenter($center)
            ->withoutAccount()
            ->create(['first_name' => 'Karim', 'last_name' => $lastName]);

        $path = Storage::disk('private')->putFileAs(
            "students/{$center->id}/{$student->id}/profile",
            UploadedFile::fake()->image('avatar.jpg', 160, 160),
            'avatar.jpg'
        );

        $student->update(['profile_photo_path' => $path]);

        return $student->fresh();
    }

    /**
     * attribut src de la balise <img> de la photo, sur la page détails.
     * Vide quand aucun <img> photo n'est rendu.
     */
    private function photoSrc(Student $student, User $as): string
    {
        $html = $this->actingAs($as)
            ->get(route('students.show', $student))
            ->assertOk()
            ->getContent();

        preg_match('#<img src="([^"]+)"[^>]*class="h-28 w-28#s', $html, $m);

        return $m[1] ?? '';
    }

    /**
     * Le src rendu est-il le même chemin que l'URL attendue (hôte ignoré) ?
     */
    private function assertPhotoSrc(Student $student, User $as, string $expectedUrl): string
    {
        $src = $this->photoSrc($student, $as);

        $this->assertNotSame('', $src, 'La balise <img> de la photo doit être rendue.');
        $this->assertSame(
            parse_url($expectedUrl, PHP_URL_PATH),
            parse_url($src, PHP_URL_PATH),
            'L’URL de la photo affichée ne correspond pas à l’URL attendue.'
        );

        return $src;
    }

    /**
     * Le bloc avatar par défaut (initiales) est-il rendu ?
     */
    private function hasInitialsAvatar(string $html): bool
    {
        return str_contains($html, 'rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-700');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. La photo privée est servie et s'affiche
    // ──────────────────────────────────────────────────────────────────────────

    public function test_private_photo_is_displayed_through_the_secured_route(): void
    {
        $student = $this->studentWithPhoto($this->berlin);

        $src = $this->assertPhotoSrc($student, $this->adminBerlin, route('students.profile-photo', $student));
        $this->assertStringNotContainsString('/storage/', $src, 'Une photo privée ne doit plus passer par l’URL du disque public.');

        $response = $this->actingAs($this->adminBerlin)
            ->get($src)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // C'est bien le fichier enregistré qui est servi.
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(basename($student->profile_photo_path), $response->baseResponse->getFile()->getFilename());
    }

    public function test_super_admin_can_also_view_the_photo(): void
    {
        $student = $this->studentWithPhoto($this->berlin, 'Ziani');

        $this->assertPhotoSrc($student, $this->superAdmin, route('students.profile-photo', $student));
        $this->actingAs($this->superAdmin)
            ->get(route('students.profile-photo', $student))
            ->assertOk();
    }

    public function test_details_page_never_builds_a_public_storage_url_for_a_private_photo(): void
    {
        $student = $this->studentWithPhoto($this->berlin);

        $html = $this->actingAs($this->adminBerlin)
            ->get(route('students.show', $student))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(asset('storage/' . $student->profile_photo_path), $html, 'L’ancienne URL cassée ne doit plus être produite.');
        $this->assertStringNotContainsString('src="' . url('/storage/students/'), $html);
    }

    public function test_guest_is_redirected_to_login_by_the_photo_route(): void
    {
        $student = $this->studentWithPhoto($this->berlin, 'Privee');

        // La photo est une donnée personnelle : jamais accessible sans session.
        $this->get(route('students.profile-photo', $student))
            ->assertRedirect();

        $this->assertFalse(
            $this->app['auth']->guard('web')->check(),
            'Aucune session ne doit être active pour ce test d’accès invité.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Après un ajout puis une modification (item 7)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_photo_displays_after_being_uploaded_through_the_create_form(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'center_id'              => $this->berlin->id,
            'first_name'             => 'Awa',
            'last_name'              => 'Diallo',
            'date_of_birth'          => '2000-05-10',
            'nationality'            => 'Malienne',
            'phone'                  => '+49 30 1112233',
            'whatsapp'               => '+49 170 9998877',
            'emergency_contact_phone' => '+49 170 5554433',
            'profile_photo'          => UploadedFile::fake()->image('photo.jpg', 200, 200),
        ]);

        $response->assertRedirect();

        $student = Student::firstWhere('last_name', 'Diallo');
        $this->assertNotNull($student->profile_photo_path);
        Storage::disk('private')->assertExists($student->profile_photo_path);

        // La page « Détails de l'étudiant » affiche la photo qui vient d'être ajoutée.
        $src = $this->assertPhotoSrc($student, $this->superAdmin, route('students.profile-photo', $student));
        $this->actingAs($this->superAdmin)->get($src)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_photo_is_still_displayed_after_the_file_is_updated(): void
    {
        $student = $this->studentWithPhoto($this->berlin, 'El Amrani');
        $storedPath = $student->profile_photo_path;

        $this->actingAs($this->adminBerlin)
            ->put(route('students.update', $student), [
                'first_name'   => 'Youssef',
                'last_name'    => 'El Amrani',
                'date_of_birth' => '2001-03-08',
                'gender'       => 'male',
                'nationality'  => 'Marocaine',
                'phone'        => '+212 600 112233',
                'status'       => 'active',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('students.show', $student));

        // Ni le chemin ni le fichier ne bougent lors d'une modification.
        $this->assertSame($storedPath, $student->fresh()->profile_photo_path);
        Storage::disk('private')->assertExists($storedPath);

        $updated = $student->fresh();
        $src = $this->assertPhotoSrc($updated, $this->adminBerlin, route('students.profile-photo', $updated));
        $this->actingAs($this->adminBerlin)->get($src)->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Aucune photo valide → avatar par défaut, jamais d'image cassée
    // ──────────────────────────────────────────────────────────────────────────

    public function test_student_without_photo_shows_the_default_avatar(): void
    {
        $student = Student::factory()->forCenter($this->berlin)->withoutAccount()->create([
            'first_name' => 'Nadia',
            'last_name'  => 'SansPhoto',
        ]);
        $this->assertNull($student->profile_photo_path);

        $html = $this->actingAs($this->adminBerlin)
            ->get(route('students.show', $student))
            ->assertOk()
            ->getContent();

        $this->assertFalse(str_contains($html, 'profile-photo'), 'Aucune balise photo ne doit être générée sans photo.');
        $this->assertStringNotContainsString('<img src="' . url('/storage/students/'), $html);
        $this->assertTrue($this->hasInitialsAvatar($html), 'L’avatar par défaut (initiales) doit être affiché.');
    }

    public function test_dangling_path_without_file_falls_back_to_the_default_avatar(): void
    {
        $student = $this->studentWithPhoto($this->berlin, 'Fantome');
        $missing = 'students/' . $this->berlin->id . '/' . $student->id . '/profile/disque-a-l-ancienne.jpg';
        $student->update(['profile_photo_path' => $missing]);

        // Le fichier référencé n'existe sur aucun disque.
        $this->assertFalse($student->fresh()->hasProfilePhoto());

        $html = $this->actingAs($this->adminBerlin)
            ->get(route('students.show', $student))
            ->assertOk()
            ->getContent();

        $this->assertTrue($this->hasInitialsAvatar($html), 'Avatar propre au lieu d’une image cassée.');
        $this->assertStringNotContainsString('profile-photo', $html);

        $this->actingAs($this->adminBerlin)
            ->get(route('students.profile-photo', $student))
            ->assertNotFound();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Fichier réellement stocké sur le disque public → URL du disque public
    // ──────────────────────────────────────────────────────────────────────────

    public function test_photo_actually_on_the_public_disk_uses_the_public_disk_url(): void
    {
        $student = Student::factory()->forCenter($this->berlin)->withoutAccount()->create([
            'first_name' => 'Omar',
            'last_name'  => 'Public',
        ]);

        $path = Storage::disk('public')->putFileAs(
            "students/{$this->berlin->id}/{$student->id}/profile",
            UploadedFile::fake()->image('legacy.jpg', 120, 120),
            'legacy.jpg'
        );
        $student->update(['profile_photo_path' => $path]);
        $student = $student->fresh();

        $this->assertSame('public', $student->profilePhotoDisk());
        $this->assertSame(Storage::disk('public')->url($path), $student->profilePhotoUrl());
        $this->assertStringContainsString('/storage/' . $path, $student->profilePhotoUrl());

        // La page affiche directement l'URL du disque public (pas de route).
        $src = $this->assertPhotoSrc($student, $this->adminBerlin, Storage::disk('public')->url($path));
        $this->assertStringNotContainsString('profile-photo', $src);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Permissions et isolation multi-centres
    // ──────────────────────────────────────────────────────────────────────────

    public function test_photo_route_respects_center_isolation(): void
    {
        $student = $this->studentWithPhoto($this->berlin);
        $url = route('students.profile-photo', $student);

        $this->actingAs($this->adminBerlin)->get($url)->assertOk();
        $this->actingAs($this->adminMunich)->get($url)->assertForbidden();
        $this->actingAs($this->superAdmin)->get($url)->assertOk();
    }

    public function test_displaying_the_photo_never_touches_the_data_or_the_files(): void
    {
        $student = $this->studentWithPhoto($this->berlin, 'Preserve');
        $path = $student->profile_photo_path;

        $this->actingAs($this->adminBerlin)->get(route('students.show', $student))->assertOk();
        $this->actingAs($this->adminBerlin)->get(route('students.profile-photo', $student))->assertOk();

        Storage::disk('private')->assertExists($path);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Karim',
            'last_name' => 'Preserve',
            'profile_photo_path' => $path,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Les quatre centres marocains (item 8)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_photos_display_in_the_four_moroccan_centers(): void
    {
        $centers = [
            ['name' => 'Centre Tanger',    'code' => 'TGR'],
            ['name' => 'Centre Agadir',    'code' => 'AGD'],
            ['name' => 'Centre Essaouira', 'code' => 'ESS'],
            ['name' => 'Centre Tétouane',  'code' => 'TET'],
        ];

        foreach ($centers as $centerData) {
            $center = Center::factory()->create($centerData);
            $admin = User::factory()->centerAdmin($center->id)
                ->create(['email' => strtolower($centerData['code']) . '-photo@test.ma']);

            $student = $this->studentWithPhoto($center, 'Etudiant' . $centerData['code']);

            $src = $this->assertPhotoSrc($student, $admin, route('students.profile-photo', $student));
            $this->actingAs($admin)->get($src)
                ->assertOk()
                ->assertHeader('Content-Type', 'image/jpeg');

            Storage::disk('private')->assertExists($student->profile_photo_path);
        }
    }
}
