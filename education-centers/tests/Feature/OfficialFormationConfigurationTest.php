<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Teacher;
use App\Models\User;
use App\Support\DomainRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission « Correction — Bouton Ajouter une formation ».
 *
 * Vérifie que, sur admin.home :
 *  - « Accéder aux formations » ouvre bien le catalogue des six cartes
 *    (formations-generales.index) ;
 *  - « Ajouter une formation » ouvre une page DISTINCTE de configuration
 *    (formations-generales.picker) qui expose les SIX emplacements officiels
 *    et permet d'éditer prix / durée / description / statut / professeur
 *    responsable ;
 *  - aucun septième emplacement n'est jamais proposé, aucun doublon n'est
 *    créé (un slot déjà configuré s'ouvre en édition) ;
 *  - les permissions Admin Centre / Super Admin sont respectées.
 */
class OfficialFormationConfigurationTest extends TestCase
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

        $this->centerBerlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->centerMunich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin  = User::factory()->superAdmin()->create(['email' => 'sa-cfg@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab-cfg@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['email' => 'am-cfg@test.de']);
    }

    /**
     * Les deux boutons de la carte Formations mènent à des destinations
     * strictement distinctes (aucune confusion possible).
     */
    public function test_two_buttons_on_admin_home_target_distinct_destinations(): void
    {
        $home = $this->actingAs($this->adminBerlin)->get(route('admin.home'));
        $home->assertOk();

        $home->assertSee(route('formations-generales.picker'), false);
        $home->assertSee(route('formations-generales.index'), false);

        // Vérifie la destination exacte de chaque bouton via le HTML.
        $html = $home->getContent();

        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="' . preg_quote(route('formations-generales.picker'), '/') . '"[^>]+id="btn-add-formation-ca-domaines"/',
            $html,
            'Le bouton « Ajouter une formation » doit cibler formations-generales.picker.'
        );

        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="' . preg_quote(route('formations-generales.index'), '/') . '"/',
            $html,
            'Le bouton « Accéder aux formations » doit cibler formations-generales.index.'
        );

        // Les deux URL sont différentes.
        $this->assertNotSame(
            route('formations-generales.picker'),
            route('formations-generales.index'),
        );
    }

    /**
     * Le picker liste exactement les six formations officielles, ni plus ni moins.
     */
    public function test_picker_shows_exactly_six_official_slots(): void
    {
        // Sème officieLlement les 6 pour Berlin (un slot déjà configuré).
        $existing = Formation::factory()->official('sante')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'sante',
            'name'      => 'Santé',
            'code'      => 'OF-BER-SANTE',
        ]);

        $response = $this->actingAs($this->adminBerlin)->get(route('formations-generales.picker'));
        $response->assertOk();

        // Les six codes canoniques apparaissent dans le HTML.
        foreach (DomainRegistry::slugs() as $slug) {
            $response->assertSee('id="picker-slot-' . $slug . '"', false);
        }

        // Slot déjà configuré → bouton Configurer (édition), pas de doublon.
        $response->assertSee('id="picker-edit-sante"', false);
        $response->assertSee(route('formations-generales.edit', $existing), false);

        // Slots vierges → bouton Activer et configurer.
        $response->assertSee('id="picker-activate-informatique"', false);

        // Jamais de septième slot.
        $html = $response->getContent();
        $count = preg_match_all('/id="picker-slot-[a-z\-]+"/', $html);
        $this->assertSame(6, $count, 'Le picker doit exposer exactement six emplacements.');
    }

    /**
     * Ouvrir l'édition d'une formation officielle existante (pas de création).
     */
    public function test_edit_page_shows_configurable_fields_for_existing_formation(): void
    {
        $teacher = Teacher::factory()->create([
            'center_id' => $this->centerBerlin->id,
            'first_name' => 'Alice',
            'last_name'  => 'Martin',
        ]);

        $formation = Formation::factory()->official('informatique')->create([
            'center_id'     => $this->centerBerlin->id,
            'domain'        => 'informatique',
            'name'          => 'Informatique',
            'code'          => 'OF-BER-INFORMATIQUE',
            'annual_price'  => 12000,
            'duration_weeks' => 24,
            'duration_hours' => 480,
            'status'        => 'active',
            'teacher_id'    => $teacher->id,
        ]);

        $response = $this->actingAs($this->adminBerlin)->get(route('formations-generales.edit', $formation));
        $response->assertOk();
        $response->assertSee('form-configure-formation', false);
        $response->assertSee('name="annual_price"', false);
        $response->assertSee('name="duration_weeks"', false);
        $response->assertSee('name="duration_hours"', false);
        $response->assertSee('name="status"', false);
        $response->assertSee('name="teacher_id"', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('12000');
        $response->assertSee('Alice Martin');
    }

    /**
     * L'enregistrement d'une configuration met à jour la formation officielle
     * existante SANS en créer une nouvelle ni toucher aux autres.
     */
    public function test_update_saves_configuration_without_creating_seventh_formation(): void
    {
        $formation = Formation::factory()->official('hotellerie')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'hotellerie',
            'name'      => 'Hôtellerie',
            'code'      => 'OF-BER-HOTELLERIE',
            'status'    => 'active',
        ]);

        $teacher = Teacher::factory()->create([
            'center_id' => $this->centerBerlin->id,
        ]);

        $before = Formation::count();

        $response = $this->actingAs($this->adminBerlin)->patch(route('formations-generales.update', $formation), [
            'description'    => 'Parcours officiel Hôtellerie du centre Berlin.',
            'annual_price'   => 9500,
            'currency'       => 'MAD',
            'duration_weeks' => 30,
            'duration_hours' => 600,
            'status'         => 'active',
            'teacher_id'     => $teacher->id,
        ]);

        $response->assertRedirect(route('formations-generales.show', $formation));
        $response->assertSessionHas('success');

        $formation->refresh();
        $this->assertSame('Parcours officiel Hôtellerie du centre Berlin.', $formation->description);
        $this->assertSame('9500.00', (string) $formation->annual_price);
        $this->assertSame(30, $formation->duration_weeks);
        $this->assertSame(600, $formation->duration_hours);
        $this->assertSame('active', $formation->status);
        $this->assertSame($teacher->id, (int) $formation->teacher_id);

        // Aucun doublon, aucune 7ᵉ formation.
        $this->assertSame($before, Formation::count());
    }

    /**
     * Activer un emplacement vierge crée la formation officielle manquante
     * (unique pour ce (center,official_code)) puis redirige vers son édition.
     */
    public function test_activate_creates_missing_official_formation_and_redirects_to_edit(): void
    {
        $before = Formation::count();

        $response = $this->actingAs($this->adminBerlin)->post(route('formations-generales.activate'), [
            'official_code' => 'design-interieur',
        ]);

        $response->assertRedirect();

        $formation = Formation::query()
            ->where('center_id', $this->centerBerlin->id)
            ->where('official_code', 'design-interieur')
            ->where('is_official', true)
            ->first();

        $this->assertNotNull($formation, 'La formation officielle doit être créée.');
        $this->assertTrue($formation->isOfficial());

        // Redirection vers l'édition de la formation fraiche.
        $this->assertSame(
            route('formations-generales.edit', $formation),
            $response->headers->get('Location'),
        );

        // Une — et une seule — nouvelle ligne.
        $this->assertSame($before + 1, Formation::count());
    }

    /**
     * Ré-execute activation sur un slot déjà configuré : aucun doublon.
     */
    public function test_activate_is_idempotent_and_does_not_duplicate_existing_slot(): void
    {
        $existing = Formation::factory()->official('maconnerie')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'maconnerie',
            'name'      => 'Maçonnerie',
            'code'      => 'OF-BER-MACONNERIE',
        ]);

        $before = Formation::count();

        $response = $this->actingAs($this->adminBerlin)->post(route('formations-generales.activate'), [
            'official_code' => 'maconnerie',
        ]);

        $response->assertRedirect(route('formations-generales.edit', $existing));
        $this->assertSame($before, Formation::count(), 'Aucun doublon ne doit être créé.');
    }

    /**
     * Un code officiel inconnu (tentative de 7ᵉ formation) est rejeté.
     */
    public function test_activate_rejects_unknown_official_code(): void
    {
        $before = Formation::count();

        $response = $this->actingAs($this->adminBerlin)->post(route('formations-generales.activate'), [
            'official_code' => 'septime-fantome',
        ]);

        // Le contrôleur redirige vers le picker avec un message d'erreur.
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString(route('formations-generales.picker'), $location);
        $response->assertSessionHas('error');

        $this->assertSame($before, Formation::count(), 'Aucune formation fantôme ne doit être créée.');
    }

    /**
     * Isolation serveur : un Admin Centre ne peut pas configurer la formation
     * officielle d'un autre centre (403).
     */
    public function test_center_admin_cannot_edit_official_formation_of_other_center(): void
    {
        $foreign = Formation::factory()->official('sante')->create([
            'center_id' => $this->centerMunich->id,
            'domain'    => 'sante',
            'name'      => 'Santé Munich',
            'code'      => 'OF-MUN-SANTE',
        ]);

        // Édition interdite.
        $this->actingAs($this->adminBerlin)
            ->get(route('formations-generales.edit', $foreign))
            ->assertForbidden();

        // Mise à jour interdite.
        $this->actingAs($this->adminBerlin)
            ->patch(route('formations-generales.update', $foreign), [
                'status' => 'inactive',
            ])
            ->assertForbidden();

        // Activation croisée : le center_id est forcé côté serveur, la
        // création atterrit à Berlin, jamais à Munich.
        $this->actingAs($this->adminBerlin)->post(route('formations-generales.activate'), [
            'official_code' => 'design-graphique',
            'center_id'     => $this->centerMunich->id, // tentative de spoofing
        ])->assertRedirect();

        $this->assertDatabaseHas('formations', [
            'center_id'     => $this->centerBerlin->id,
            'official_code' => 'design-graphique',
            'is_official'   => true,
        ]);
        $this->assertDatabaseMissing('formations', [
            'center_id'     => $this->centerMunich->id,
            'official_code' => 'design-graphique',
        ]);
    }

    /**
     * Un Professeur ne peut pas atteindre le picker ni le formulaire d'édition.
     */
    public function test_teacher_cannot_access_configuration_flow(): void
    {
        $teacher = User::factory()->teacher($this->centerBerlin->id)->create(['email' => 't-cfg@test.de']);

        $this->actingAs($teacher)->get(route('formations-generales.picker'))->assertForbidden();

        $formation = Formation::factory()->official('sante')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'sante',
            'code'      => 'OF-BER-SANTE-T',
            'name'      => 'Santé',
        ]);

        $this->actingAs($teacher)->get(route('formations-generales.edit', $formation))->assertForbidden();
    }

    /**
     * Un professeur d'un autre centre ne peut pas être désigné comme
     * responsable d'une formation officielle du centre courant.
     */
    public function test_update_rejects_teacher_from_other_center(): void
    {
        $formation = Formation::factory()->official('informatique')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'informatique',
            'code'      => 'OF-BER-INFORMATIQUE-X',
            'name'      => 'Informatique',
            'status'    => 'active',
        ]);

        $foreignTeacher = Teacher::factory()->create([
            'center_id' => $this->centerMunich->id,
        ]);

        $response = $this->actingAs($this->adminBerlin)->patch(route('formations-generales.update', $formation), [
            'status'     => 'active',
            'teacher_id' => $foreignTeacher->id,
        ]);

        $response->assertSessionHasErrors('teacher_id');
        $formation->refresh();
        $this->assertNotSame($foreignTeacher->id, (int) $formation->teacher_id);
    }
}
