<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\FormationLineage;
use App\Models\Teacher;
use App\Models\User;
use App\Services\OfficialFormationService;
use Database\Seeders\OfficialFormationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Contrats de la phase RÉVERSIBLE de la refonte des formations.
 *
 * Vérifie : seeder idempotent des 6 formations générales par centre, non-destruction
 * des données héritées, limites appliquées UNIQUEMENT aux officielles (≤2 classes,
 * 1 responsable, contrôle du centre), préservation de l'accès historique et de
 * l'isolation multi-centres.
 */
class OfficialFormationsTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICIAL_CODES = [
        'sante', 'informatique', 'hotellerie', 'maconnerie', 'design-interieur', 'design-graphique',
    ];

    private function officialFormation(Center $center, string $code = 'sante'): Formation
    {
        return Formation::factory()->create([
            'center_id'     => $center->id,
            'domain'        => $code,
            'official_code' => $code,
            'is_official'   => true,
            'status'        => 'active',
            'teacher_id'    => null,
            'code'          => 'OF-' . $center->code . '-' . strtoupper(str_replace('-', '_', $code)) . '-' . fake()->unique()->numberBetween(1, 99999),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Seeder : 6 officielles par centre, idempotent, non destructif
    // ──────────────────────────────────────────────────────────────────────

    public function test_seeder_creates_six_official_formations_per_center(): void
    {
        $tanger = Center::factory()->create(['code' => 'TANGER']);
        $agadir = Center::factory()->create(['code' => 'AGADIR']);

        $this->seed(OfficialFormationsSeeder::class);

        $this->assertSame(12, Formation::query()->official()->count());

        foreach ([$tanger, $agadir] as $center) {
            $codes = Formation::query()->official()->where('center_id', $center->id)->pluck('official_code')->sort()->values()->all();
            $expected = self::OFFICIAL_CODES;
            sort($expected);
            $this->assertEquals($expected, $codes, "Le centre {$center->code} doit disposer exactement des six formations officielles.");
        }
    }

    public function test_seeder_is_idempotent_on_replay(): void
    {
        Center::factory()->create(['code' => 'TANGER']);

        $this->seed(OfficialFormationsSeeder::class);
        $first = Formation::query()->official()->orderBy('id')->get(['id', 'center_id', 'official_code', 'name'])->all();

        $this->seed(OfficialFormationsSeeder::class);
        $second = Formation::query()->official()->orderBy('id')->get(['id', 'center_id', 'official_code', 'name'])->all();

        $this->assertCount(6, $second);
        $this->assertEquals($first, $second, 'La réexécution ne doit rien créer ni modifier.');
    }

    public function test_seeder_never_touches_legacy_formations_or_business_data(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);

        // Une formation héritée (non officielle) avec des données métier.
        $legacy = Formation::factory()->create([
            'center_id' => $center->id,
            'domain' => 'sante',
            'is_official' => false,
            'official_code' => null,
        ]);
        $class = ClassRoom::factory()->forFormation($legacy)->create();
        Enrollment::factory()->create(['center_id' => $center->id, 'formation_id' => $legacy->id, 'class_id' => $class->id]);

        $legacyId = $legacy->id;
        $this->seed(OfficialFormationsSeeder::class);

        // Legacy intact : toujours non officielle, jamais fusionnée, sans lineage.
        $legacy->refresh();
        $this->assertFalse((bool) $legacy->is_official);
        $this->assertNull($legacy->merged_into_id);
        $this->assertNotSame($legacyId, Formation::query()->official()->where('center_id', $center->id)->where('official_code', 'sante')->value('id'));

        // Classes/inscriptions historiques non reparentées.
        $class->refresh();
        $this->assertSame($legacy->id, (int) $class->formation_id);
        $this->assertSame(1, Enrollment::query()->count());

        // Aucune donnée de fusion inventée.
        $this->assertSame(0, FormationLineage::query()->count());
        $this->assertSame(0, Formation::query()->official()->whereNotNull('teacher_id')->count());
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Limite de 2 classes — uniquement sur les formations officielles
    // ──────────────────────────────────────────────────────────────────────

    public function test_official_formation_is_capped_at_two_classes_at_the_model_level(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $formation = $this->officialFormation($center);

        ClassRoom::factory()->forFormation($formation)->create();
        ClassRoom::factory()->forFormation($formation)->create();

        $this->expectException(ValidationException::class);
        ClassRoom::factory()->forFormation($formation)->create();
    }

    public function test_legacy_formation_is_not_bounded_by_the_class_limit(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $legacy = Formation::factory()->create([
            'center_id' => $center->id,
            'is_official' => false,
            'official_code' => null,
        ]);

        for ($i = 0; $i < 4; $i++) {
            ClassRoom::factory()->forFormation($legacy)->create();
        }

        $this->assertSame(4, $legacy->classes()->count());
    }

    public function test_class_limit_is_enforced_through_the_http_interface(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $admin = User::factory()->centerAdmin($center->id)->create();
        $formation = $this->officialFormation($center);

        $this->actingAs($admin)->post(route('formations-generales.classes.store', $formation), [
            'name' => 'Classe A', 'code' => 'GA-A1',
        ])->assertRedirect(route('formations-generales.show', $formation));

        $this->actingAs($admin)->post(route('formations-generales.classes.store', $formation), [
            'name' => 'Classe B', 'code' => 'GA-A2',
        ])->assertRedirect(route('formations-generales.show', $formation));

        $this->actingAs($admin)->post(route('formations-generales.classes.store', $formation), [
            'name' => 'Classe C', 'code' => 'GA-A3',
        ])->assertSessionHas('error');

        $this->assertSame(2, $formation->classes()->count());
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Un seul professeur responsable, même centre, jamais automatique
    // ──────────────────────────────────────────────────────────────────────

    public function test_responsible_teacher_assignment_is_manual_single_and_center_scoped(): void
    {
        $service = app(OfficialFormationService::class);
        $center = Center::factory()->create(['code' => 'TANGER']);
        $other = Center::factory()->create(['code' => 'AGADIR']);

        $formation = $this->officialFormation($center);
        $this->assertNull($formation->teacher_id, 'Aucun responsable automatique.');

        $t1 = Teacher::factory()->forCenter($center)->create();
        $t2 = Teacher::factory()->forCenter($center)->create();
        $foreign = Teacher::factory()->forCenter($other)->create();

        $service->assignResponsible($formation, $t1);
        $formation->refresh();
        $this->assertSame($t1->id, (int) $formation->teacher_id);

        // Remplacement : un seul responsable à la fois.
        $service->assignResponsible($formation, $t2);
        $formation->refresh();
        $this->assertSame($t2->id, (int) $formation->teacher_id);

        $this->expectException(ValidationException::class);
        $service->assignResponsible($formation, $foreign);
    }

    public function test_assigning_responsible_through_http_rejects_foreign_center_teacher(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $other = Center::factory()->create(['code' => 'AGADIR']);
        $admin = User::factory()->centerAdmin($center->id)->create();
        $formation = $this->officialFormation($center);
        $foreign = Teacher::factory()->forCenter($other)->create();

        $this->actingAs($admin)->post(route('formations-generales.teacher.assign', $formation), [
            'teacher_id' => $foreign->id,
        ])->assertSessionHas('error');

        $formation->refresh();
        $this->assertNull($formation->teacher_id);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Isolation des centres + accès historique préservé
    // ──────────────────────────────────────────────────────────────────────

    public function test_center_admin_cannot_open_another_center_official_formation(): void
    {
        $tanger = Center::factory()->create(['code' => 'TANGER']);
        $agadir = Center::factory()->create(['code' => 'AGADIR']);
        $adminTanger = User::factory()->centerAdmin($tanger->id)->create();
        $formationAgadir = $this->officialFormation($agadir);

        $this->actingAs($adminTanger)->get(route('formations-generales.show', $formationAgadir))->assertStatus(403);
    }

    public function test_general_space_rejects_non_official_formation(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $admin = User::factory()->centerAdmin($center->id)->create();
        $legacy = Formation::factory()->create(['center_id' => $center->id, 'is_official' => false]);

        $this->actingAs($admin)->get(route('formations-generales.show', $legacy))->assertStatus(404);
    }

    public function test_index_displays_the_six_cards_preserving_design(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $admin = User::factory()->centerAdmin($center->id)->create();
        $this->seed(OfficialFormationsSeeder::class);

        $response = $this->actingAs($admin)->get(route('formations-generales.index'));
        $response->assertStatus(200);

        foreach (self::OFFICIAL_CODES as $code) {
            $response->assertSee('card-domaine-' . $code, false);
        }
    }

    public function test_index_cards_link_directly_to_domain_dashboards(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $admin = User::factory()->centerAdmin($center->id)->create();
        $this->seed(OfficialFormationsSeeder::class);

        $response = $this->actingAs($admin)->get(route('formations-generales.index'));
        $response->assertStatus(200);

        // Un clic sur une carte formation ouvre directement le tableau de bord
        // pédagogique existant du domaine (et non la page de configuration).
        foreach (self::OFFICIAL_CODES as $code) {
            $response->assertSee('href="' . route('center-admin.domain.dashboard', $code) . '"', false);
        }
        $response->assertDontSee('/formations-generales/', false);
    }

    public function test_index_cards_link_to_domain_dashboards_for_super_admin(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $superAdmin = User::factory()->superAdmin()->create();
        $this->seed(OfficialFormationsSeeder::class);

        $response = $this->actingAs($superAdmin)->get(route('formations-generales.index', ['center_id' => $center->id]));
        $response->assertStatus(200);

        // Super Admin : le dashboard domaine cible le centre sélectionné.
        $response->assertSee('href="' . route('super-admin.domain.dashboard', ['domain' => 'informatique', 'center_id' => $center->id]) . '"', false);
        $response->assertDontSee('/formations-generales/' . $center->id, false);
    }

    public function test_domain_dashboard_opens_in_directly_from_card_target(): void
    {
        $center = Center::factory()->create(['code' => 'TANGER']);
        $admin = User::factory()->centerAdmin($center->id)->create();
        $this->seed(OfficialFormationsSeeder::class);

        // La cible de la carte est accessible en un seul clic et pose le
        // contexte domaine en session (aucune page intermédiaire).
        $this->actingAs($admin)
            ->get(route('center-admin.domain.dashboard', 'informatique'))
            ->assertStatus(200);

        $this->assertSame('informatique', session('active_domain'));
    }

    public function test_historical_domain_access_is_preserved(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get(route('super-admin.domaines'))->assertStatus(200);

        // La route du dashboard domaine (accès historique) reste résolue.
        $this->assertTrue($this->routeHas('super-admin.domain.dashboard'));
        $this->assertTrue(Schema::hasTable('formation_lineage'));
    }

    private function routeHas(string $name): bool
    {
        return app('router')->has($name);
    }
}
