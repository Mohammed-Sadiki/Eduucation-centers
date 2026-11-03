<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Domain;
use App\Models\Formation;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherFormationSelectionMissionTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;
    protected Center $centerMunich;
    protected User $superAdmin;
    protected User $adminBerlin;
    protected User $adminMunich;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BERLIN']);
        $this->centerMunich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUNICH']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@ec.test']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab@ec.test']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['email' => 'am@ec.test']);

        // Créer les 6 domaines officiels
        $domains = [
            ['slug' => 'sante', 'name' => 'Santé'],
            ['slug' => 'informatique', 'name' => 'Informatique'],
            ['slug' => 'hotellerie', 'name' => 'Hôtellerie'],
            ['slug' => 'maconnerie', 'name' => 'Maçonnerie'],
            ['slug' => 'design-interieur', 'name' => 'Design intérieur'],
            ['slug' => 'design-graphique', 'name' => 'Design graphique'],
        ];

        foreach ($domains as $d) {
            Domain::firstOrCreate(['slug' => $d['slug']], [
                'name' => $d['name'],
                'icon' => 'folder',
                'color' => 'blue',
                'sort_order' => 1,
                'is_active' => true,
            ]);
        }
    }

    /**
     * 1. Le nouveau formulaire ne propose PLUS le champ Domaine : la sélection
     * de la Formation est directe (parcours « Centre → Formation générale »).
     */
    public function test_create_form_uses_direct_formation_selection(): void
    {
        // Créer les six formations générales officielles du centre Berlin pour
        // alimenter le catalogue du formulaire.
        foreach (['sante', 'informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'] as $code) {
            Formation::factory()->forCenter($this->centerBerlin)->forDomain($code)->official($code)->create([
                'name' => 'Formation ' . ucfirst($code),
                'code' => strtoupper($code) . '-BER',
            ]);
        }

        $response = $this->actingAs($this->superAdmin)->get(route('users.create'));
        $response->assertStatus(200);

        // Ne doit PLUS contenir le champ Domaine (supprimé de l'interface).
        $response->assertDontSee('name="domain"', false);
        $response->assertDontSee("Domaine d'enseignement", false);

        // Doit contenir la sélection directe de Formation.
        $response->assertSee('name="formation_id"', false);

        // Les six formations officielles de Berlin sont proposées.
        $response->assertSee('Formation Sante', false);
        $response->assertSee('Formation Informatique', false);
    }

    /**
     * 2. Création réussie d'un professeur avec domaine et formation assignés.
     */
    public function test_teacher_creation_stores_domain_and_formation_id(): void
    {
        $formationBerlinInfo = Formation::create([
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Développement Web Fullstack',
            'code' => 'DEV-WEB-BER',
            'duration_hours' => 140,
            'price' => 600,
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'Professeur Informatique',
            'email' => 'prof.info@ec.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => User::ROLE_TEACHER,
            'center_id' => $this->centerBerlin->id,
            'status' => 'active',
            'domain' => 'informatique',
            'formation_id' => $formationBerlinInfo->id,
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), $payload);

        $response->assertRedirect(route('users.index'));

        // Vérification dans users
        $this->assertDatabaseHas('users', [
            'email' => 'prof.info@ec.test',
            'role' => User::ROLE_TEACHER,
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'formation_id' => $formationBerlinInfo->id,
        ]);

        $user = User::where('email', 'prof.info@ec.test')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->formation);
        $this->assertEquals('Développement Web Fullstack', $user->formation->name);
        $this->assertContains('informatique', $user->domainSlugs()->toArray());
    }

    /**
     * 3. Isolation multi-centres : l'Admin Centre Berlin ne peut pas assigner une formation de Munich.
     */
    public function test_center_admin_cannot_assign_formation_from_another_center(): void
    {
        $formationMunich = Formation::create([
            'center_id' => $this->centerMunich->id,
            'domain' => 'informatique',
            'name' => 'Data Science Munich',
            'code' => 'DS-MUC',
            'duration_hours' => 100,
            'price' => 500,
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'Prof Hacker',
            'email' => 'hacker@ec.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => User::ROLE_TEACHER,
            'status' => 'active',
            'domain' => 'informatique',
            'formation_id' => $formationMunich->id, // Formation d'un autre centre !
        ];

        // L'admin de Berlin tente de rattacher la formation de Munich -> Refus 403 (création interdite aux admin centres)
        $response = $this->actingAs($this->adminBerlin)->post(route('users.store'), $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'hacker@ec.test']);
    }

    /**
     * 4. La validation ne dépend plus du domaine : seule l'appartenance au
     * centre est contrôlée (parcours « Centre → Formation générale »).
     */
    public function test_formation_belongs_to_the_authorized_center(): void
    {
        $formationSante = Formation::factory()->forCenter($this->centerBerlin)->forDomain('sante')->official('sante')->create([
            'name' => 'Soins Infirmiers',
            'code' => 'SOIN-BER',
        ]);

        // Super Admin rattache le prof à un centre différent de celui de la
        // formation -> refus (la règle « centre autorisé » s'applique).
        $payload = [
            'name' => 'Prof Mismatch',
            'email' => 'mismatch@ec.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => User::ROLE_TEACHER,
            'center_id' => $this->centerMunich->id,
            'status' => 'active',
            'formation_id' => $formationSante->id,
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), $payload);
        $response->assertSessionHasErrors('formation_id');
        $this->assertDatabaseMissing('users', ['email' => 'mismatch@ec.test']);
    }

    /**
     * 5. users.edit n'affiche plus le champ Domaine, uniquement la Formation.
     */
    public function test_edit_user_updates_formation_only(): void
    {
        $formation1 = Formation::factory()->forCenter($this->centerBerlin)->forDomain('informatique')->official('informatique')->create([
            'name' => 'Formation Info',
            'code' => 'INFO-1',
        ]);

        $formation2 = Formation::factory()->forCenter($this->centerBerlin)->forDomain('sante')->official('sante')->create([
            'name' => 'Formation Sante',
            'code' => 'SANTE-1',
        ]);

        $teacherUser = User::factory()->teacher($this->centerBerlin->id)->create([
            'name' => 'Prof Initial',
            'email' => 'teacher.update@ec.test',
            'domain' => 'informatique',
            'formation_id' => $formation1->id,
        ]);

        // Vérifier l'accès à la page d'édition : plus de champ Domaine.
        $responseGet = $this->actingAs($this->superAdmin)->get(route('users.edit', $teacherUser));
        $responseGet->assertStatus(200);
        $responseGet->assertDontSee('name="domain"', false);
        $responseGet->assertSee('name="formation_id"', false);

        // Mettre à jour la formation (sans transmettre de domaine).
        $responsePut = $this->actingAs($this->superAdmin)->put(route('users.update', $teacherUser), [
            'name' => 'Prof Santé Modifié',
            'email' => 'teacher.update@ec.test',
            'role' => User::ROLE_TEACHER,
            'status' => 'active',
            'formation_id' => $formation2->id,
        ]);

        $responsePut->assertRedirect(route('users.show', $teacherUser));

        $teacherUser->refresh();
        $this->assertEquals($formation2->id, $teacherUser->formation_id);
    }

    /**
     * 6. Compatibilité Planning de classe : classes() fonctionne indépendamment sur le profil Teacher sans conflit.
     */
    public function test_teacher_class_planning_compatibility(): void
    {
        $formation = Formation::create([
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Dev Web',
            'code' => 'DWB',
            'duration_hours' => 60,
            'price' => 400,
            'status' => 'active',
        ]);

        $teacherUser = User::factory()->teacher($this->centerBerlin->id)->create([
            'name' => 'Prof Planning',
            'domain' => 'informatique',
            'formation_id' => $formation->id,
        ]);

        $teacherProfile = Teacher::create([
            'user_id' => $teacherUser->id,
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'formation_id' => $formation->id,
            'teacher_number' => 'T-TEST-001',
            'first_name' => 'Prof',
            'last_name' => 'Planning',
            'gender' => 'male',
            'status' => 'active',
        ]);

        // Créer une classe pour cette formation
        $classA = \App\Models\ClassRoom::create([
            'center_id' => $this->centerBerlin->id,
            'formation_id' => $formation->id,
            'name' => 'Classe DW-1',
            'code' => 'DW1',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-01',
            'status' => 'active',
        ]);

        // La classe n'est pas encore assignée au professeur via class_teacher
        $this->assertCount(0, $teacherProfile->classes);

        // Assigner la classe manuellement
        $teacherProfile->classes()->attach($classA->id);
        $teacherProfile->load('classes');

        $this->assertCount(1, $teacherProfile->classes);
        $this->assertEquals('Classe DW-1', $teacherProfile->classes->first()->name);
    }
}
