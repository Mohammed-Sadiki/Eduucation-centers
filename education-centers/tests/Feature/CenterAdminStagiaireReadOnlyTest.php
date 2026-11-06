<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CenterAdminStagiaireReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;
    protected Center $centerMunich;
    protected User $superAdmin;
    protected User $centerAdminBerlin;
    protected User $centerAdminMunich;
    protected User $secretaryBerlin;
    protected Stagiaire $stagiaireBerlin;
    protected Stagiaire $stagiaireMunich;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerBerlin = Center::create([
            'name' => 'Centre Berlin',
            'code' => 'BER',
            'status' => 'active',
        ]);

        $this->centerMunich = Center::create([
            'name' => 'Centre Munich',
            'code' => 'MUN',
            'status' => 'active',
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'center_id' => null,
        ]);

        $this->centerAdminBerlin = User::factory()->create([
            'role' => User::ROLE_CENTER_ADMIN,
            'name' => 'Admin Berlin',
            'email' => 'admin.berlin@example.com',
            'center_id' => $this->centerBerlin->id,
        ]);

        $this->centerAdminMunich = User::factory()->create([
            'role' => User::ROLE_CENTER_ADMIN,
            'name' => 'Admin Munich',
            'email' => 'admin.munich@example.com',
            'center_id' => $this->centerMunich->id,
        ]);

        $this->secretaryBerlin = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'name' => 'Secrétaire Berlin',
            'email' => 'secretaire.berlin@example.com',
            'center_id' => $this->centerBerlin->id,
        ]);

        $this->stagiaireBerlin = Stagiaire::create([
            'center_id' => $this->centerBerlin->id,
            'first_name' => 'Youssef',
            'last_name' => 'Alami',
            'stage_type' => 'Stage Découverte',
            'stage_price' => 1500.00,
            'status' => 'en_cours',
        ]);

        $this->stagiaireMunich = Stagiaire::create([
            'center_id' => $this->centerMunich->id,
            'first_name' => 'Hans',
            'last_name' => 'Gruber',
            'stage_type' => 'Stage Technique',
            'stage_price' => 2000.00,
            'status' => 'en_cours',
        ]);
    }

    /**
     * 1. L'Admin Centre peut consulter la liste des stagiaires de son propre centre.
     */
    public function test_center_admin_can_view_stagiaires_list_of_own_center(): void
    {
        $response = $this->actingAs($this->centerAdminBerlin)->get(route('stagiaires.index'));

        $response->assertStatus(200);
        $response->assertSee('Youssef');
        $response->assertSee('Alami');
        // Ne voit pas les stagiaires de Munich
        $response->assertDontSee('Hans');
        $response->assertDontSee('Gruber');

        // Mode consultation : ne voit pas le bouton Ajouter un stagiaire
        $response->assertDontSee('Ajouter un stagiaire');
        // Ne voit pas les boutons Modifier ni Supprimer
        $response->assertDontSee('Modifier le stagiaire');
        $response->assertDontSee('Supprimer le stagiaire');
        // Voit uniquement l'action Consulter
        $response->assertSee('Consulter le stagiaire');
    }

    /**
     * 2. L'Admin Centre peut consulter les détails d'un stagiaire de son centre.
     */
    public function test_center_admin_can_view_stagiaire_details_of_own_center(): void
    {
        $response = $this->actingAs($this->centerAdminBerlin)->get(route('stagiaires.show', $this->stagiaireBerlin));

        $response->assertStatus(200);
        $response->assertSee('Youssef');
        $response->assertSee('Alami');
        $response->assertSee('Stage Découverte');

        // Pas d'actions de modification ou suppression sur la fiche
        $response->assertDontSee('Modifier le dossier');
        $response->assertDontSee('Supprimer');
    }

    /**
     * 3. L'Admin Centre ne peut pas consulter un stagiaire d'un autre centre (403).
     */
    public function test_center_admin_cannot_view_stagiaire_of_other_center(): void
    {
        $response = $this->actingAs($this->centerAdminBerlin)->get(route('stagiaires.show', $this->stagiaireMunich));
        $response->assertStatus(403);
    }

    /**
     * 4. L'Admin Centre ne peut pas accéder au formulaire de création (403).
     */
    public function test_center_admin_cannot_access_create_form(): void
    {
        $response = $this->actingAs($this->centerAdminBerlin)->get(route('stagiaires.create'));
        $response->assertStatus(403);
    }

    /**
     * 5. L'Admin Centre ne peut pas soumettre de création de stagiaire (403).
     */
    public function test_center_admin_cannot_store_stagiaire(): void
    {
        $response = $this->actingAs($this->centerAdminBerlin)->post(route('stagiaires.store'), [
            'first_name' => 'Tentative',
            'last_name' => 'Interdite',
            'stage_type' => 'Stage Test',
        ]);
        $response->assertStatus(403);
    }

    /**
     * 6. L'Admin Centre ne peut pas accéder au formulaire d'édition (403).
     */
    public function test_center_admin_cannot_access_edit_form(): void
    {
        $response = $this->actingAs($this->centerAdminBerlin)->get(route('stagiaires.edit', $this->stagiaireBerlin));
        $response->assertStatus(403);
    }

    /**
     * 7. L'Admin Centre ne peut pas soumettre de mise à jour de stagiaire (403).
     */
    public function test_center_admin_cannot_update_stagiaire(): void
    {
        $response = $this->actingAs($this->centerAdminBerlin)->put(route('stagiaires.update', $this->stagiaireBerlin), [
            'first_name' => 'NouveauNom',
            'last_name' => 'Alami',
        ]);
        $response->assertStatus(403);
    }

    /**
     * 8. L'Admin Centre ne peut pas supprimer un stagiaire (403).
     */
    public function test_center_admin_cannot_delete_stagiaire(): void
    {
        $response = $this->actingAs($this->centerAdminBerlin)->delete(route('stagiaires.destroy', $this->stagiaireBerlin));
        $response->assertStatus(403);
    }

    /**
     * 9. La Secrétaire conserve son accès à la création et modification.
     */
    public function test_secretary_still_has_manage_access(): void
    {
        // Accès création
        $response = $this->actingAs($this->secretaryBerlin)->get(route('stagiaires.create'));
        $response->assertStatus(200);

        // Accès édition
        $response = $this->actingAs($this->secretaryBerlin)->get(route('stagiaires.edit', $this->stagiaireBerlin));
        $response->assertStatus(200);
    }

    /**
     * 10. Le Super Admin conserve son plein accès.
     */
    public function test_super_admin_still_has_full_access(): void
    {
        // Liste
        $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->assertStatus(200);
        // Création
        $this->actingAs($this->superAdmin)->get(route('stagiaires.create'))->assertStatus(200);
        // Fiche
        $this->actingAs($this->superAdmin)->get(route('stagiaires.show', $this->stagiaireBerlin))->assertStatus(200);
        // Édition
        $this->actingAs($this->superAdmin)->get(route('stagiaires.edit', $this->stagiaireBerlin))->assertStatus(200);
    }
}

