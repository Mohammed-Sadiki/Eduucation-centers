<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mission « Ajout dynamique des formations » (catalogue extensible).
 *
 * Vérifie que le bouton « + » du picker ouvre un réel formulaire de création
 * de formation générale PERSONNALISÉE (is_official = true, official_code =
 * NULL, sans domaine) et que le cycle complet fonctionne :
 *  - création dans le centre actif, affichage immédiat (picker + cartes) ;
 *  - interdiction des doublons de nom dans un même centre ;
 *  - professeur responsable facultatif mais obligatoirement du centre ;
 *  - modification / suppression réservées aux personnalisées (les six
 *    officielles sont 404 sur ces routes) ;
 *  - isolation des centres respectée (Admin Centre → 403 sur le centre tiers) ;
 *  - aucune donnée existante supprimée ou modifiée par l'ajout.
 */
class CustomFormationsTest extends TestCase
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

        $this->superAdmin  = User::factory()->superAdmin()->create(['email' => 'sa-custom@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab-custom@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['email' => 'am-custom@test.de']);
    }

    /**
     * Payload minimal valide pour store()/updateCustom().
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'         => 'Assistance médicale',
            'description'  => 'Formation générale personnalisée du centre Berlin.',
            'annual_price' => 7500,
            'status'       => 'active',
        ], $overrides);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  1. Accès au formulaire de création
    // ──────────────────────────────────────────────────────────────────────────

    public function test_create_page_accessible_to_center_admin(): void
    {
        $response = $this->actingAs($this->adminBerlin)->get(route('formations-generales.create'));

        $response->assertOk();
        $response->assertSee('form-custom-formation', false);
        $response->assertSee('name="annual_price"', false);
        $response->assertSee('name="teacher_id"', false);
        $response->assertSee('name="image"', false);
        // Admin Centre : le centre n'est pas choisissable (forcé côté serveur).
        $response->assertDontSee('name="center_id"', false);
    }

    public function test_create_page_accessible_to_super_admin_with_center_context(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('formations-generales.create', ['center_id' => $this->centerMunich->id]));

        $response->assertOk();
        // Super Admin : le centre cible est transmis au formulaire.
        $response->assertSee('name="center_id"', false);
        $response->assertSee($this->centerMunich->name);
    }

    public function test_create_page_forbidden_for_other_roles(): void
    {
        $teacherUser = User::factory()->create([
            'role'      => \App\Models\User::ROLE_TEACHER,
            'center_id' => $this->centerBerlin->id,
        ]);

        $this->actingAs($teacherUser)->get(route('formations-generales.create'))->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  2. Création + règles métier
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_creates_custom_formation_visible_immediately(): void
    {
        $response = $this->actingAs($this->adminBerlin)->post(route('formations-generales.store'), $this->payload());

        $response->assertRedirect(route('formations-generales.index', ['center_id' => $this->centerBerlin->id]));
        $response->assertSessionHas('success');

        $formation = Formation::query()->customGeneral()->sole();
        $this->assertSame($this->centerBerlin->id, (int) $formation->center_id);
        $this->assertNull($formation->official_code);
        $this->assertNull($formation->domain);
        $this->assertTrue($formation->is_official);
        $this->assertStringStartsWith('CF-BER-', $formation->code);

        // Affichage immédiat : section personnalisée du picker + carte index.
        $picker = $this->actingAs($this->adminBerlin)->get(route('formations-generales.picker'));
        $picker->assertOk()->assertSee('id="picker-custom-' . $formation->id . '"', false);

        $index = $this->actingAs($this->adminBerlin)->get(route('formations-generales.index'));
        $index->assertOk()->assertSee('id="card-domaine-perso-' . $formation->id . '"', false);
    }

    public function test_store_requires_name_and_price(): void
    {
        $response = $this->actingAs($this->adminBerlin)
            ->post(route('formations-generales.store'), ['status' => 'active']);

        $response->assertSessionHasErrors(['name', 'annual_price']);
        $this->assertSame(0, Formation::query()->customGeneral()->count());
    }

    public function test_store_rejects_duplicate_name_within_center(): void
    {
        Formation::factory()->official('sante')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'sante',
            'name'      => 'Santé',
            'code'      => 'OF-BER-SANTE',
        ]);

        // Doublon avec une officielle du même centre → rejeté.
        $this->actingAs($this->adminBerlin)
            ->post(route('formations-generales.store'), $this->payload(['name' => 'Santé']))
            ->assertSessionHasErrors('name');

        // Doublon entre deux personnalisées du même centre → rejeté.
        $this->actingAs($this->adminBerlin)
            ->post(route('formations-generales.store'), $this->payload(['name' => 'Secourisme']));
        $this->actingAs($this->adminBerlin)
            ->post(route('formations-generales.store'), $this->payload(['name' => 'Secourisme']))
            ->assertSessionHasErrors('name');

        // Même nom dans un AUTRE centre → autorisé (isolation des centres).
        $this->actingAs($this->adminMunich)
            ->post(route('formations-generales.store'), $this->payload([
                'name'        => 'Secourisme',
                'center_id'   => $this->centerMunich->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Formation::query()->customGeneral()->count());
    }

    public function test_store_rejects_teacher_from_another_center(): void
    {
        $foreignTeacher = Teacher::factory()->create(['center_id' => $this->centerMunich->id]);

        $response = $this->actingAs($this->adminBerlin)->post(route('formations-generales.store'), $this->payload([
            'teacher_id' => $foreignTeacher->id,
        ]));

        $response->assertSessionHasErrors('teacher_id');
        $this->assertSame(0, Formation::query()->customGeneral()->count());
    }

    public function test_store_accepts_optional_image_upload(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->adminBerlin)->post(route('formations-generales.store'), $this->payload([
            'image' => UploadedFile::fake()->image('banner.jpg', 800, 450),
        ]));

        $response->assertSessionHasNoErrors();

        $formation = Formation::query()->customGeneral()->sole();
        $this->assertNotNull($formation->image_path);
        Storage::disk('public')->assertExists($formation->image_path);
    }

    public function test_super_admin_must_provide_valid_center(): void
    {
        // Sans center_id → redirection avec erreur, aucune création.
        $this->actingAs($this->superAdmin)
            ->post(route('formations-generales.store'), $this->payload())
            ->assertRedirect(route('formations-generales.index'))
            ->assertSessionHas('error');

        // Center_id inexistant → même comportement.
        $this->actingAs($this->superAdmin)
            ->post(route('formations-generales.store'), $this->payload(['center_id' => 99_999]))
            ->assertSessionHas('error');

        $this->assertSame(0, Formation::query()->customGeneral()->count());
    }

    public function test_creation_never_touches_existing_formations(): void
    {
        $official = Formation::factory()->official('informatique')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'informatique',
            'name'      => 'Informatique',
            'code'      => 'OF-BER-INFORMATIQUE',
        ]);
        $legacy = Formation::factory()->create([
            'center_id' => $this->centerBerlin->id,
            'name'      => 'Ancienne sous-formation',
            'code'      => 'OLD-001',
        ]);

        $this->actingAs($this->adminBerlin)
            ->post(route('formations-generales.store'), $this->payload());

        $official->refresh();
        $legacy->refresh();
        $this->assertSame('Informatique', $official->name);
        $this->assertSame('OF-BER-INFORMATIQUE', $official->code);
        $this->assertSame('Ancienne sous-formation', $legacy->name);
        $this->assertFalse((bool) $legacy->is_official);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  3. Modification
    // ──────────────────────────────────────────────────────────────────────────

    public function test_custom_formation_name_is_editable_and_unique_per_center(): void
    {
        Formation::factory()->official('sante')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'sante',
            'name'      => 'Santé',
            'code'      => 'OF-BER-SANTE',
        ]);
        $custom = Formation::factory()->create([
            'center_id'     => $this->centerBerlin->id,
            'name'          => 'Assistance médicale',
            'code'          => 'CF-BER-AAA111',
            'is_official'   => true,
            'official_code' => null,
        ]);

        // Nom conservé → valide (ignore soi-même).
        $this->actingAs($this->adminBerlin)->put(route('formations-generales.update-custom', $custom), $this->payload([
            'name' => 'Assistance médicale',
        ]))->assertSessionHasNoErrors();

        // Conflit avec une officielle du même centre → rejeté.
        $this->actingAs($this->adminBerlin)->put(route('formations-generales.update-custom', $custom), $this->payload([
            'name' => 'Santé',
        ]))->assertSessionHasErrors('name');

        // Renommage valide.
        $this->actingAs($this->adminBerlin)->put(route('formations-generales.update-custom', $custom), $this->payload([
            'name'   => 'Auxiliaire de santé',
            'status' => 'inactive',
        ]))->assertSessionHasNoErrors();

        $custom->refresh();
        $this->assertSame('Auxiliaire de santé', $custom->name);
        $this->assertSame('inactive', $custom->status);
    }

    public function test_edit_custom_returns_404_for_official_formation(): void
    {
        $official = Formation::factory()->official('hotellerie')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'hotellerie',
            'name'      => 'Hôtellerie',
            'code'      => 'OF-BER-HOTELLERIE',
        ]);

        $this->actingAs($this->adminBerlin)->get(route('formations-generales.edit-custom', $official))->assertNotFound();
        $this->actingAs($this->adminBerlin)->put(route('formations-generales.update-custom', $official), $this->payload())->assertNotFound();
    }

    public function test_center_admin_cannot_edit_another_centers_custom_formation(): void
    {
        $custom = Formation::factory()->create([
            'center_id'     => $this->centerBerlin->id,
            'name'          => 'Secourisme',
            'code'          => 'CF-BER-BBB222',
            'is_official'   => true,
            'official_code' => null,
        ]);

        $this->actingAs($this->adminMunich)->get(route('formations-generales.edit-custom', $custom))->assertForbidden();
        $this->actingAs($this->adminMunich)->put(route('formations-generales.update-custom', $custom), $this->payload())->assertForbidden();

        $custom->refresh();
        $this->assertSame('Secourisme', $custom->name);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  4. Suppression
    // ──────────────────────────────────────────────────────────────────────────

    public function test_destroy_custom_removes_only_empty_custom_formations(): void
    {
        $custom = Formation::factory()->create([
            'center_id'     => $this->centerBerlin->id,
            'name'          => 'Couture industrielle',
            'code'          => 'CF-BER-CCC333',
            'is_official'   => true,
            'official_code' => null,
        ]);

        $response = $this->actingAs($this->adminBerlin)->delete(route('formations-generales.destroy-custom', $custom));

        $response->assertRedirect(route('formations-generales.index', ['center_id' => $this->centerBerlin->id]));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('formations', ['id' => $custom->id]);
    }

    public function test_destroy_blocked_when_custom_has_classes_or_enrollments(): void
    {
        $custom = Formation::factory()->create([
            'center_id'     => $this->centerBerlin->id,
            'name'          => 'Logistique',
            'code'          => 'CF-BER-DDD444',
            'is_official'   => true,
            'official_code' => null,
        ]);
        ClassRoom::factory()->forFormation($custom)->create();

        $this->actingAs($this->adminBerlin)
            ->delete(route('formations-generales.destroy-custom', $custom))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('formations', ['id' => $custom->id]);

        Enrollment::factory()->forFormation($custom)->accepted()->create();

        $this->actingAs($this->adminBerlin)
            ->delete(route('formations-generales.destroy-custom', $custom))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('formations', ['id' => $custom->id]);
    }

    public function test_destroy_returns_404_for_official_formations(): void
    {
        $official = Formation::factory()->official('maconnerie')->create([
            'center_id' => $this->centerBerlin->id,
            'domain'    => 'maconnerie',
            'name'      => 'Maçonnerie',
            'code'      => 'OF-BER-MACONNERIE',
        ]);

        // Les six officielles ne sont JAMAIS supprimables via ces routes.
        $this->actingAs($this->adminBerlin)->delete(route('formations-generales.destroy-custom', $official))->assertNotFound();
        $this->actingAs($this->superAdmin)->delete(route('formations-generales.destroy-custom', $official))->assertNotFound();
        $this->assertDatabaseHas('formations', ['id' => $official->id]);
    }

    public function test_center_admin_cannot_delete_another_centers_custom_formation(): void
    {
        $custom = Formation::factory()->create([
            'center_id'     => $this->centerBerlin->id,
            'name'          => 'Taille de pierre',
            'code'          => 'CF-BER-EEE555',
            'is_official'   => true,
            'official_code' => null,
        ]);

        $this->actingAs($this->adminMunich)->delete(route('formations-generales.destroy-custom', $custom))->assertForbidden();
        $this->assertDatabaseHas('formations', ['id' => $custom->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  5. Picker : le « + » est un vrai bouton et les cartes coexistent
    // ──────────────────────────────────────────────────────────────────────────

    public function test_picker_exposes_create_button_and_custom_cards_beside_six_slots(): void
    {
        $custom = Formation::factory()->create([
            'center_id'     => $this->centerBerlin->id,
            'name'          => 'Assistanat de direction',
            'code'          => 'CF-BER-FFF666',
            'is_official'   => true,
            'official_code' => null,
        ]);

        $response = $this->actingAs($this->adminBerlin)->get(route('formations-generales.picker'));
        $response->assertOk();

        // Bouton « + » cliquable et accessible.
        $response->assertSee('id="picker-open-create"', false);
        $response->assertSee(route('formations-generales.create'), false);
        $response->assertSee('aria-label', false);

        // Six slots officiels conservés + carte personnalisée à côté.
        $html = $response->getContent();
        $this->assertSame(6, preg_match_all('/id="picker-slot-[a-z\-]+"/', $html));
        $response->assertSee('id="picker-custom-' . $custom->id . '"', false);
        $response->assertSee('id="picker-edit-custom-' . $custom->id . '"', false);
    }
}
