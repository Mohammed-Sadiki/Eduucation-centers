<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormationCenterAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $adminAgadir;
    private User $adminEssaouira;
    private Center $centerAgadir;
    private Center $centerEssaouira;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerAgadir = Center::factory()->create([
            'name' => 'Centre Agadir',
            'code' => 'AGA',
        ]);

        $this->centerEssaouira = Center::factory()->create([
            'name' => 'Centre Essaouira',
            'code' => 'ESS',
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'center_id' => null,
        ]);

        $this->adminAgadir = User::factory()->create([
            'role' => 'center_admin',
            'center_id' => $this->centerAgadir->id,
        ]);

        $this->adminEssaouira = User::factory()->create([
            'role' => 'center_admin',
            'center_id' => $this->centerEssaouira->id,
        ]);
    }

    public function test_create_formation_assigned_to_agadir_is_only_visible_in_agadir(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('formations-generales.store'), [
            'center_id' => $this->centerAgadir->id,
            'name' => 'Formation Spécifique Agadir',
            'annual_price' => 5000,
            'status' => 'active',
        ]);

        $response->assertRedirect();

        // Visible dans le centre Agadir
        $this->actingAs($this->adminAgadir)
            ->get(route('formations-generales.index'))
            ->assertOk()
            ->assertSee('Formation Spécifique Agadir');

        // Non visible dans le centre Essaouira
        $this->actingAs($this->adminEssaouira)
            ->get(route('formations-generales.index'))
            ->assertOk()
            ->assertDontSee('Formation Spécifique Agadir');
    }

    public function test_create_formation_assigned_to_essaouira_is_only_visible_in_essaouira(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('formations-generales.store'), [
            'center_id' => $this->centerEssaouira->id,
            'name' => 'Formation Spécifique Essaouira',
            'annual_price' => 6000,
            'status' => 'active',
        ]);

        $response->assertRedirect();

        // On vide la session (flash toast du store précédent) avant de tester l'isolation
        session()->flush();

        // Non visible dans Agadir
        $this->actingAs($this->adminAgadir)
            ->get(route('formations-generales.index'))
            ->assertOk()
            ->assertDontSee('Formation Spécifique Essaouira');

        // Visible dans Essaouira
        $this->actingAs($this->adminEssaouira)
            ->get(route('formations-generales.index'))
            ->assertOk()
            ->assertSee('Formation Spécifique Essaouira');
    }

    public function test_create_formation_assigned_to_all_centers_is_visible_in_all_centers(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('formations-generales.store'), [
            'center_id' => 'all',
            'name' => 'Formation Globale Tous Centres',
            'annual_price' => 7500,
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $formation = Formation::where('name', 'Formation Globale Tous Centres')->first();
        $this->assertNotNull($formation);
        $this->assertNull($formation->center_id);

        // Visible pour Agadir
        $this->actingAs($this->adminAgadir)
            ->get(route('formations-generales.index'))
            ->assertOk()
            ->assertSee('Formation Globale Tous Centres');

        // Visible pour Essaouira
        $this->actingAs($this->adminEssaouira)
            ->get(route('formations-generales.index'))
            ->assertOk()
            ->assertSee('Formation Globale Tous Centres');

        // Accessible pour le dashboard par les deux admins
        $this->actingAs($this->adminAgadir)
            ->get(route('formations-generales.show', $formation))
            ->assertOk();

        $this->actingAs($this->adminEssaouira)
            ->get(route('formations-generales.show', $formation))
            ->assertOk();
    }

    public function test_formation_image_upload_and_display(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('catalogue-test.png', 400, 300);

        $response = $this->actingAs($this->superAdmin)->post(route('formations-generales.store'), [
            'center_id' => $this->centerAgadir->id,
            'name' => 'Formation Avec Image',
            'annual_price' => 4500,
            'status' => 'active',
            'image' => $file,
        ]);

        $response->assertRedirect();

        $formation = Formation::where('name', 'Formation Avec Image')->first();
        $this->assertNotNull($formation);
        $this->assertNotNull($formation->image_path);
        Storage::disk('public')->assertExists($formation->image_path);

        // Vérification de la présence de l'URL de l'image dans la Card du catalogue
        $this->actingAs($this->adminAgadir)
            ->get(route('formations-generales.index'))
            ->assertOk()
            ->assertSee($formation->image_path);
    }

    public function test_formation_card_click_opens_formation_specific_dashboard(): void
    {
        $formation = Formation::create([
            'center_id' => $this->centerAgadir->id,
            'name' => 'Formation Dashboard Test',
            'code' => 'CF-AGA-DASH01',
            'annual_price' => 5000,
            'status' => 'active',
            'is_official' => true,
            'official_code' => null,
        ]);

        $response = $this->actingAs($this->adminAgadir)
            ->get(route('formations-generales.show', $formation));

        $response->assertOk();
        $response->assertSee('Formation Dashboard Test');
    }
}
