<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Centralisation de la création des élèves dans la page « Domaines de formation ».
 *
 * Vérifie : bouton déplacé (pages internes sans bouton, page Domaines avec),
 * terminologie « Élèves », cascade centre → domaine → formation, et isolation
 * multi-centres strictement appliquée côté serveur (non contournable par une
 * requête HTTP modifiée).
 */
class StudentCreationCentralizationTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;
    protected Center $centerMunich;
    protected User $superAdmin;
    protected User $adminBerlin;
    protected User $adminMunich;
    protected Formation $formationSanteBerlin;
    protected Formation $formationSanteMunich;
    protected Formation $formationInfoBerlin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->centerMunich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['email' => 'am@test.de']);

        $this->formationSanteBerlin = Formation::factory()->forCenter($this->centerBerlin)->forDomain('sante')->official('sante')->create();
        $this->formationSanteMunich = Formation::factory()->forCenter($this->centerMunich)->forDomain('sante')->official('sante')->create();
        $this->formationInfoBerlin = Formation::factory()->forCenter($this->centerBerlin)->forDomain('informatique')->official('informatique')->create();
    }

    private function newStudentUser(Center $center, string $name, string $email): User
    {
        return User::factory()->student($center->id)->create(['name' => $name, 'email' => $email]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Déplacement du bouton + terminologie
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_sees_add_student_button_on_domaines_page(): void
    {
        // Le bouton a été déplacé de la page Domaines vers la carte Formations
        // de la page d'accueil admin (admin.home).
        $this->actingAs($this->superAdmin)
            ->get(route('admin.home'))
            ->assertOk()
            ->assertSee('btn-add-student-domaines', false)
            ->assertSee('Ajouter un élève')
            ->assertSee(route('students.create'), false);
    }

    public function test_center_admin_sees_add_student_button_on_domaines_page(): void
    {
        foreach ([$this->adminBerlin, $this->adminMunich] as $admin) {
            $this->actingAs($admin)
                ->get(route('admin.home'))
                ->assertOk()
                ->assertSee('btn-add-student-ca-domaines', false)
                ->assertSee('Ajouter un élève');
        }
    }

    public function test_domaines_page_still_shows_the_six_domain_cards(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domaines'))
            ->assertOk();

        foreach (['sante', 'informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'] as $slug) {
            $response->assertSee($slug, false);
        }
    }

    public function test_students_list_no_longer_shows_create_button(): void
    {
        foreach ([$this->superAdmin, $this->adminBerlin] as $admin) {
            $this->actingAs($admin)
                ->get(route('students.index'))
                ->assertOk()
                ->assertDontSee('btn-create-student', false)
                ->assertDontSee('Nouveau dossier élève');
        }
    }

    public function test_students_list_title_is_renamed_to_eleves(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Élèves')
            ->assertDontSee('Dossiers Élèves');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Formulaire : contexte selon le rôle
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_create_form_shows_center_selection(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('name="center_id"', false)
            ->assertDontSee('name="domain"', false)
            ->assertSee('name="formation_id"', false)
            ->assertSee('Centre Berlin')
            ->assertSee('Centre Munich');
    }

    public function test_center_admin_create_form_hides_center_selection(): void
    {
        foreach ([[$this->adminBerlin, 'Centre Berlin'], [$this->adminMunich, 'Centre Munich']] as [$admin, $centerName]) {
            $this->actingAs($admin)
                ->get(route('students.create'))
                ->assertOk()
                ->assertDontSee('name="center_id"', false)
                ->assertSee('Automatique', false)
                ->assertSee($centerName);
        }
    }

    public function test_center_admin_create_form_only_lists_own_center_formations(): void
    {
        $response = $this->actingAs($this->adminBerlin)
            ->get(route('students.create'))
            ->assertOk();

        // Options serveur : uniquement les formations de Berlin.
        $content = $response->getContent();
        $this->assertStringContainsString('value="'.$this->formationSanteBerlin->id.'"', $content);
        $this->assertStringNotContainsString('value="'.$this->formationSanteMunich->id.'"', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Enregistrement : cascade + inscription à la formation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_student_with_valid_center_domain_formation(): void
    {
        $user = $this->newStudentUser($this->centerMunich, 'Sophie Test', 'sophie@test.de');

        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'user_id' => $user->id,
            'center_id' => $this->centerMunich->id,
            'domain' => 'sante',
            'formation_id' => $this->formationSanteMunich->id,
            'first_name' => 'Sophie',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $student = Student::where('user_id', $user->id)->first();
        $this->assertNotNull($student);
        $this->assertSame($this->centerMunich->id, $student->center_id);

        $enrollment = Enrollment::where('student_id', $student->id)->first();
        $this->assertNotNull($enrollment);
        $this->assertSame($this->formationSanteMunich->id, $enrollment->formation_id);
        $this->assertSame($this->centerMunich->id, $enrollment->center_id);
        $this->assertSame(Enrollment::STATUS_PENDING, $enrollment->status);
        $this->assertStringStartsWith('MUN-INS-', $enrollment->registration_number);
    }

    public function test_student_can_still_be_created_without_formation(): void
    {
        $user = $this->newStudentUser($this->centerBerlin, 'Jean Test', 'jean@test.de');

        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'user_id' => $user->id,
            'center_id' => $this->centerBerlin->id,
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('students', ['user_id' => $user->id, 'center_id' => $this->centerBerlin->id]);
        $this->assertSame(0, Enrollment::count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Sécurité : rien ne vient du navigateur sans vérification serveur
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_can_create_student_auto_enrolled_in_own_center(): void
    {
        $user = $this->newStudentUser($this->centerBerlin, 'Mia Berlin', 'mia@test.de');

        // Aucun center_id transmis : le centre Berlin est déduit du compte connecté.
        $response = $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'user_id' => $user->id,
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'first_name' => 'Mia',
            'last_name' => 'Berlin',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $student = Student::where('user_id', $user->id)->first();
        $this->assertNotNull($student);
        $this->assertSame($this->centerBerlin->id, $student->center_id);
        $this->assertStringStartsWith('BER-', $student->student_number);

        $enrollment = Enrollment::where('student_id', $student->id)->first();
        $this->assertNotNull($enrollment);
        $this->assertSame($this->formationSanteBerlin->id, $enrollment->formation_id);
        $this->assertSame($this->centerBerlin->id, $enrollment->center_id);
        $this->assertStringStartsWith('BER-INS-', $enrollment->registration_number);
    }

    public function test_center_admin_cannot_use_formation_of_another_center(): void
    {
        $user = $this->newStudentUser($this->centerBerlin, 'Clara Test', 'clara@test.de');

        // Admin Berlin tente d'inscrire à une formation de Munich (requête modifiée).
        $response = $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'user_id' => $user->id,
            'center_id' => $this->centerMunich->id, // tentative d'injection
            'domain' => 'sante',
            'formation_id' => $this->formationSanteMunich->id,
            'first_name' => 'Clara',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('formation_id');
        $this->assertDatabaseMissing('students', ['user_id' => $user->id]);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_center_admin_create_is_forced_to_own_center(): void
    {
        $user = $this->newStudentUser($this->centerMunich, 'Lukas Test', 'lukas@test.de');

        // Admin Munich tente de créer le dossier en injectant le centre Berlin.
        $response = $this->actingAs($this->adminMunich)->post(route('students.store'), [
            'user_id' => $user->id,
            'center_id' => $this->centerBerlin->id,
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'first_name' => 'Lukas',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        // Le center_id injecté est ignoré : le centre effectif reste Munich.
        // La formation de Berlin est donc refusée, et aucune création ne passe.
        $response->assertSessionHasErrors('formation_id');
        $this->assertDatabaseMissing('students', ['user_id' => $user->id]);
    }

    public function test_super_admin_cannot_use_formation_outside_selected_center(): void
    {
        $user = $this->newStudentUser($this->centerBerlin, 'Anna Test', 'anna2@test.de');

        // Formation de Munich transmise avec un compte/centre Berlin.
        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'user_id' => $user->id,
            'center_id' => $this->centerBerlin->id,
            'domain' => 'sante',
            'formation_id' => $this->formationSanteMunich->id,
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('formation_id');
        $this->assertDatabaseMissing('students', ['user_id' => $user->id]);
    }

    public function test_formation_domain_mismatch_is_rejected(): void
    {
        $user = $this->newStudentUser($this->centerBerlin, 'Bob Test', 'bob@test.de');

        // Domaine « informatique » associé à une formation Santé.
        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'user_id' => $user->id,
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'formation_id' => $this->formationSanteBerlin->id,
            'first_name' => 'Bob',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('formation_id');
        $this->assertDatabaseMissing('students', ['user_id' => $user->id]);
    }

    public function test_inactive_formation_is_rejected(): void
    {
        $inactive = Formation::factory()->forCenter($this->centerBerlin)->forDomain('sante')->inactive()->create();
        $user = $this->newStudentUser($this->centerBerlin, 'Eve Test', 'eve@test.de');

        $response = $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'user_id' => $user->id,
            'domain' => 'sante',
            'formation_id' => $inactive->id,
            'first_name' => 'Eve',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('formation_id');
        $this->assertDatabaseMissing('students', ['user_id' => $user->id]);
    }

    public function test_unknown_domain_slug_is_rejected(): void
    {
        $user = $this->newStudentUser($this->centerBerlin, 'Tom Test', 'tom@test.de');

        $response = $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'user_id' => $user->id,
            'domain' => 'domaine-inexistant',
            'first_name' => 'Tom',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('domain');
        $this->assertDatabaseMissing('students', ['user_id' => $user->id]);
    }

    public function test_teacher_and_student_cannot_reach_centralized_creation(): void
    {
        $teacher = User::factory()->teacher($this->centerBerlin->id)->create(['email' => 't@test.de']);
        $studentUser = $this->newStudentUser($this->centerBerlin, 'Kid Test', 'kid@test.de');

        $this->actingAs($teacher)->get(route('students.create'))->assertForbidden();
        $this->actingAs($studentUser)->get(route('students.create'))->assertForbidden();
        $this->actingAs($teacher)->post(route('students.store'), [])->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Page « Ajouter un élève » indépendante (aucune sidebar pédagogique)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_create_page_has_no_pedagogical_sidebar(): void
    {
        // Contexte domaine actif : même en étant « dans » Santé, la page dédiée
        // ne doit rendre aucun élément du dashboard pédagogique.
        session(['active_domain' => 'sante']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('standalone-layout', false)
            ->assertSee('btn-back-domaines', false);

        // Note : « users.index » est exclu car son URI (/users) est un sous-ensemble
        // de celle de users.create, lien légitime de l'alerte « aucun compte élève ».
        foreach (['teachers.index', 'classes.index', 'payments.index', 'attendances.index', 'schedules.index', 'centers.index'] as $name) {
            $response->assertDontSee(route($name), false);
        }
        $response->assertDontSee('Pédagogie');
    }

    public function test_center_admin_create_page_has_no_pedagogical_sidebar(): void
    {
        session(['active_domain' => 'sante']);

        foreach ([$this->adminBerlin, $this->adminMunich] as $admin) {
            $response = $this->actingAs($admin)
                ->get(route('students.create'))
                ->assertOk();

            $response->assertSee('standalone-layout', false)
                ->assertSee('btn-back-domaines', false)
                ->assertDontSee(route('teachers.index'), false)
                ->assertDontSee(route('centers.index'), false)
                ->assertDontSee('Pédagogie');
        }
    }

    public function test_back_to_formations_button_targets_the_right_portal_per_role(): void
    {
        // Nouveau parcours : le bouton de retour cible désormais la page
        // des Formations générales (et plus les Domaines).
        $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee(route('formations-generales.index'), false)
            ->assertSee('Retour aux formations');

        $this->actingAs($this->adminBerlin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee(route('formations-generales.index'), false);
    }

    public function test_no_add_student_link_anywhere_in_dashboard_navigation(): void
    {
        // La liste des élèves (avec sa sidebar) ne doit plus contenir de lien
        // vers la création ; le point d'entrée unique reste la page Domaines.
        $this->actingAs($this->superAdmin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertDontSee(route('students.create'), false);

        $this->actingAs($this->adminBerlin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertDontSee(route('students.create'), false);
    }

    public function test_students_list_link_removed_from_sidebar_per_simplification_mission(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertDontSee('href="' . route('students.index') . '"', false);

        $this->actingAs($this->adminBerlin)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertDontSee('href="' . route('students.index') . '"', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Inscription administrative DIRECTE — aucun compte utilisateur requis
    // ──────────────────────────────────────────────────────────────────────────

    public function test_create_page_no_longer_asks_for_an_account(): void
    {
        foreach ([$this->superAdmin, $this->adminBerlin] as $admin) {
            $response = $this->actingAs($admin)
                ->get(route('students.create'))
                ->assertOk()
                ->assertSee('Enregistrer l\'élève');

            // Le sélecteur de compte a disparu, ainsi que l'alerte de préinscription.
            $response->assertDontSee('name="user_id"', false);
            $response->assertDontSee('Compte utilisateur élève');
            $response->assertDontSee('Aucun compte élève disponible');
            $response->assertDontSee('Créer un compte utilisateur');
        }
    }

    public function test_super_admin_can_create_student_without_any_user_account(): void
    {
        $usersBefore = User::count();

        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'center_id' => $this->centerMunich->id,
            'domain' => 'sante',
            'formation_id' => $this->formationSanteMunich->id,
            'first_name' => 'Nadia',
            'last_name' => 'SansCompte',
            'date_of_birth' => '2003-02-10',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $student = Student::where('first_name', 'Nadia')->first();
        $this->assertNotNull($student);
        $this->assertNull($student->user_id, 'Le dossier doit être créé sans compte lié.');
        $this->assertSame($this->centerMunich->id, $student->center_id);
        $this->assertStringStartsWith('MUN-', $student->student_number);

        // Inscription créée normalement.
        $enrollment = Enrollment::where('student_id', $student->id)->first();
        $this->assertNotNull($enrollment);
        $this->assertSame($this->formationSanteMunich->id, $enrollment->formation_id);
        $this->assertStringStartsWith('MUN-INS-', $enrollment->registration_number);

        // Aucun compte utilisateur fictif n'a été créé.
        $this->assertSame($usersBefore, User::count());
    }

    public function test_center_admin_berlin_can_create_student_without_account(): void
    {
        $usersBefore = User::count();

        // Aucun user_id, aucun center_id : tout est déduit côté serveur.
        $response = $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'domain' => 'sante',
            'formation_id' => $this->formationSanteBerlin->id,
            'first_name' => 'Lena',
            'last_name' => 'OhneKonto',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $student = Student::where('first_name', 'Lena')->first();
        $this->assertNotNull($student);
        $this->assertNull($student->user_id);
        $this->assertSame($this->centerBerlin->id, $student->center_id);
        $this->assertStringStartsWith('BER-', $student->student_number);
        $this->assertSame($usersBefore, User::count());
    }

    public function test_center_admin_munich_can_create_student_without_account(): void
    {
        $this->actingAs($this->adminMunich)->post(route('students.store'), [
            'domain' => 'sante',
            'formation_id' => $this->formationSanteMunich->id,
            'first_name' => 'Max',
            'last_name' => 'OhneKonto',
            'status' => 'active',
        ])->assertRedirect();

        $student = Student::where('first_name', 'Max')->first();
        $this->assertNotNull($student);
        $this->assertNull($student->user_id);
        $this->assertSame($this->centerMunich->id, $student->center_id);
    }

    public function test_center_admin_without_account_still_cannot_cross_centers(): void
    {
        // Admin Berlin tente d'utiliser une formation de Munich, sans compte.
        $response = $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'center_id' => $this->centerMunich->id, // injection ignorée
            'domain' => 'sante',
            'formation_id' => $this->formationSanteMunich->id,
            'first_name' => 'Intrus',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('formation_id');
        $this->assertDatabaseMissing('students', ['first_name' => 'Intrus']);
    }

    public function test_multiple_students_without_accounts_can_coexist(): void
    {
        // L'index UNIQUE sur user_id autorise plusieurs NULL (MariaDB/MySQL).
        $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'first_name' => 'Awa', 'last_name' => 'Un', 'status' => 'active',
        ])->assertRedirect();

        $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'first_name' => 'Awa', 'last_name' => 'Deux', 'status' => 'active',
        ])->assertRedirect();

        $nulls = Student::whereNull('user_id')->count();
        $this->assertGreaterThanOrEqual(2, $nulls);

        $n1 = Student::where('last_name', 'Un')->first();
        $n2 = Student::where('last_name', 'Deux')->first();
        $this->assertNotEquals($n1->student_number, $n2->student_number);
    }

    public function test_student_without_account_is_consultable_and_listed(): void
    {
        $student = Student::factory()->forCenter($this->centerBerlin)->withoutAccount()->create([
            'first_name' => 'Orphelin',
            'last_name' => 'Dossier',
        ]);

        $this->assertNull($student->user_id);

        // Liste (le module liste des élèves gère user_id null).
        $this->actingAs($this->adminBerlin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee($student->student_number);

        // Fiche individuelle (affiches null-safe du compte).
        $this->actingAs($this->adminBerlin)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee($student->student_number);
    }

    public function test_existing_account_linked_students_still_work(): void
    {
        // Récupération : un dossier lié à un compte conserve son comportement.
        $user = $this->newStudentUser($this->centerBerlin, 'Garde Lien', 'garde@test.de');

        $this->actingAs($this->adminBerlin)->post(route('students.store'), [
            'user_id' => $user->id,
            'first_name' => 'Garde',
            'last_name' => 'Lien',
            'status' => 'active',
        ])->assertRedirect();

        $student = Student::where('user_id', $user->id)->first();
        $this->assertNotNull($student);
        $this->assertSame($this->centerBerlin->id, $student->center_id);
    }
}
