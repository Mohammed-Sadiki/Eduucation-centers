<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\User;
use App\Support\DomainRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeFormationCreationMissionTest extends TestCase
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

        $this->centerBerlin = Center::factory()->create([
            'name' => 'Centre Berlin',
            'code' => 'BER',
            'city' => 'Berlin',
        ]);

        $this->centerMunich = Center::factory()->create([
            'name' => 'Centre Munich',
            'code' => 'MUN',
            'city' => 'Munich',
        ]);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create();
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create();
    }

    /**
     * Test 1 — Création d'une formation avec saisie libre :
     * Créer « Développement Mobile » dans le domaine Informatique du centre Berlin.
     * Vérifier son enregistrement en base de données.
     */
    public function test_1_create_free_formation_developpement_mobile(): void
    {
        $payload = [
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Développement Mobile',
            'code' => 'DEV-MOB-01',
            'description' => 'Formation complète en développement mobile Flutter et React Native.',
            'duration_hours' => 120,
            'duration_weeks' => 10,
            'price' => 7500.00,
            'currency' => 'MAD',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('formations.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('formations', [
            'name' => 'Développement Mobile',
            'code' => 'DEV-MOB-01',
            'domain' => 'informatique',
            'center_id' => $this->centerBerlin->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test 2 — Catalogue :
     * Vérifier que « Développement Mobile » apparaît dans les formations du domaine Informatique à Berlin.
     */
    public function test_2_catalog_displays_newly_created_formation(): void
    {
        $formation = Formation::factory()->create([
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Développement Mobile',
            'code' => 'DEV-MOB-01',
            'status' => 'active',
        ]);

        // 1. Liste des formations du centre
        $response = $this->actingAs($this->superAdmin)
            ->get(route('formations.index', ['center_id' => $this->centerBerlin->id]));
        $response->assertOk();
        $response->assertSee('Développement Mobile');
        $response->assertSee('DEV-MOB-01');

        // 2. Dashboard du domaine Informatique
        $dashboardResponse = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', ['domain' => 'informatique', 'center_id' => $this->centerBerlin->id]));
        $dashboardResponse->assertOk();
    }

    /**
     * Test 3 — Ajouter un élève (nouveau parcours) :
     * Seules les six formations générales officielles sont proposées ; une
     * sous-formation legacy (ex. « Développement Mobile ») ne doit JAMAIS
     * apparaître dans le select, et l'attribut data-domain a été supprimé.
     */
    public function test_3_student_create_form_exposes_only_official_formations(): void
    {
        // Une formation legacy (non officielle) créée pour Berlin dans le domaine
        // informatique : doit être ignorée par le sélecteur du formulaire élève.
        $legacy = Formation::factory()->create([
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Développement Mobile',
            'code' => 'DEV-MOB-01',
            'price' => 7500,
            'annual_price' => 7500,
            'status' => 'active',
            'is_official' => false,
        ]);

        // La formation officielle Informatique de Berlin : doit être proposée.
        $officialInfo = Formation::factory()->official('informatique')->create([
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Informatique',
            'code' => 'OFF-BER-INF',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('students.create'));
        $response->assertOk();

        $content = $response->getContent();

        // La formation officielle est listée dans le select formation : on
        // reconnaît son option au couple (value = id formation) + data-center-id.
        $this->assertMatchesRegularExpression(
            '/<option\s+value="' . $officialInfo->id . '"\s+data-center-id="' . $this->centerBerlin->id . '"/',
            $content,
            'La formation officielle Informatique de Berlin doit être proposée.'
        );

        // La sous-formation legacy n'apparaît jamais : aucune option avec son id
        // ne porte l'attribut data-center-id (propre au select Formation), et son
        // libellé unique n'est pas rendu.
        $this->assertDoesNotMatchRegularExpression(
            '/<option\s+value="' . $legacy->id . '"\s+data-center-id/',
            $content,
            'Une sous-formation legacy ne doit jamais être proposée dans le parcours.'
        );
        $this->assertStringNotContainsString('Développement Mobile', $content);

        // Aucun data-domain n'est rendu dans le nouveau parcours.
        $this->assertStringNotContainsString('data-domain=', $content);
    }

    /**
     * Test 4 — Isolation des centres :
     * Vérifier qu'une formation créée uniquement à Berlin n'apparaît pas pour l'Admin Centre de Munich.
     */
    public function test_4_center_isolation_munich_does_not_see_berlin_formation(): void
    {
        $formationBerlin = Formation::factory()->create([
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Développement Mobile Berlin',
            'code' => 'DEV-MOB-BER',
            'status' => 'active',
        ]);

        // Pour l'Admin Centre de Munich dans "Ajouter un élève"
        $responseMunich = $this->actingAs($this->adminMunich)->get(route('students.create'));
        $responseMunich->assertOk();
        $responseMunich->assertDontSee('value="' . $formationBerlin->id . '"', false);
        $responseMunich->assertDontSee('Développement Mobile Berlin');

        // Pour l'Admin Centre de Munich dans la liste des formations
        $listMunich = $this->actingAs($this->adminMunich)->get(route('formations.index'));
        $listMunich->assertOk();
        $listMunich->assertDontSee('Développement Mobile Berlin');

        // Tentative par Admin Munich de créer dans Berlin -> forcé à Munich
        $munichCreate = $this->actingAs($this->adminMunich)->post(route('formations.store'), [
            'center_id' => $this->centerBerlin->id, // tentative de spoofing
            'domain' => 'informatique',
            'name' => 'Formation Munich Forcée',
            'code' => 'MUN-SEC-01',
            'status' => 'active',
        ]);
        $munichCreate->assertRedirect();
        $this->assertDatabaseHas('formations', [
            'code' => 'MUN-SEC-01',
            'center_id' => $this->centerMunich->id, // Strictement Munich !
        ]);
    }

    /**
     * Test 5 — Création libre dans chacun des six domaines existants.
     */
    public function test_5_free_creation_in_all_six_existing_domains(): void
    {
        $domains = ['sante', 'informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique'];

        foreach ($domains as $index => $domainSlug) {
            $code = 'TEST-DOM-' . $index;
            $name = 'Formation Spéciale ' . ucfirst($domainSlug);

            $response = $this->actingAs($this->superAdmin)->post(route('formations.store'), [
                'center_id' => $this->centerBerlin->id,
                'domain' => $domainSlug,
                'name' => $name,
                'code' => $code,
                'status' => 'active',
            ]);

            $response->assertRedirect();

            $this->assertDatabaseHas('formations', [
                'center_id' => $this->centerBerlin->id,
                'domain' => $domainSlug,
                'code' => $code,
                'name' => $name,
            ]);
        }
    }

    /**
     * Test 6 — Non-régression :
     * Les formulaires « Ajouter un élève » et « Ajouter une formation » restent indépendants.
     */
    public function test_6_non_regression_forms_independence(): void
    {
        // 1. Bouton "Ajouter une formation" mène bien à formations.create (sans champs élèves)
        $formCreate = $this->actingAs($this->superAdmin)->get(route('formations.create'));
        $formCreate->assertOk();
        $formCreate->assertSee('Intitulé de la formation');
        $formCreate->assertSee('name="name"', false);
        $formCreate->assertDontSee('Photos CIN');
        $formCreate->assertDontSee('Numéro WhatsApp');
        $formCreate->assertDontSee('name="first_name"', false);

        // 2. Bouton "Ajouter un élève" mène bien à students.create avec paiement
        $studentCreate = $this->actingAs($this->superAdmin)->get(route('students.create'));
        $studentCreate->assertOk();
        $studentCreate->assertSee('Ajouter un élève');
        $studentCreate->assertSee('name="last_name"', false);
        $studentCreate->assertSee('name="formation_id"', false);
        $studentCreate->assertSee('Paiement');
    }
}
