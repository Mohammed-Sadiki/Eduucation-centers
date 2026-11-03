<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correction globale « Supprimer le choix Domaine de toute l'interface ».
 *
 * Vérifie que les formulaires de création / modification exposent une
 * sélection directe « Centre → Formation générale → Classe », sans champ
 * Domaine, et que le backend ne demande plus ce champ.
 *
 * Règles couvertes (mission §7) :
 *  - Formulaire élève : un seul champ Formation (aucun champ Domaine).
 *  - Formulaire professeur (users.create/edit) : sélection directe Formation.
 *  - Aucune sous-formation legacy dans les listes proposées.
 *  - Aucune exigence serveur du domaine (champ technique optionnel).
 *  - Filtrage strict par centre + is_official = true.
 *  - Le domaine n'est jamais requis lors de la création.
 */
class DomainFreeFormationSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Center $centerTanger;
    private Center $centerAgadir;
    private User $superAdmin;
    private User $adminTanger;

    /** @var list<string> */
    private const OFFICIAL_CODES = [
        'sante',
        'informatique',
        'hotellerie',
        'maconnerie',
        'design-interieur',
        'design-graphique',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerTanger = Center::factory()->create(['name' => 'Tanger', 'code' => 'TNG']);
        $this->centerAgadir = Center::factory()->create(['name' => 'Agadir', 'code' => 'AGD']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@nodomain.test']);
        $this->adminTanger = User::factory()->centerAdmin($this->centerTanger->id)->create(['email' => 'tng@nodomain.test']);

        // Catalogue officiel : 6 formations par centre.
        foreach ($this->officialSet($this->centerTanger) as $f) {
            $f; // noop (créé par factory)
        }
        $this->officialSet($this->centerAgadir);
    }

    /**
     * Crée les six formations générales officielles pour un centre.
     * @return \Illuminate\Support\Collection<int, Formation>
     */
    private function officialSet(Center $center)
    {
        return collect(self::OFFICIAL_CODES)->map(fn (string $code) => Formation::factory()
            ->forCenter($center)
            ->forDomain($code)
            ->official($code)
            ->create([
                'name' => 'Formation ' . ucfirst($code) . ' ' . $center->code,
                'code' => 'OF-' . $center->code . '-' . strtoupper($code),
            ]));
    }

    // ─── 1. Formulaire Élève ─────────────────────────────────────────────────

    public function test_student_create_form_has_no_domain_field(): void
    {
        $html = $this->actingAs($this->superAdmin)
            ->get(route('students.create'))
            ->assertOk()
            ->getContent();

        // Le nom de champ `domain` n'apparaît plus dans le DOM du form.
        $this->assertStringNotContainsString('name="domain"', $html);
        $this->assertStringNotContainsString('Domaine de formation', $html);
        $this->assertStringNotContainsString('— Sélectionner le domaine —', $html);
        // Le champ Formation existe toujours.
        $this->assertStringContainsString('name="formation_id"', $html);
    }

    public function test_student_create_form_only_lists_official_formations(): void
    {
        // Une sous-formation legacy (non officielle) est présente dans le centre
        // Tanger ; elle ne doit PAS apparaître dans le select.
        $legacy = Formation::factory()->forCenter($this->centerTanger)->forDomain('informatique')->create([
            'name' => 'Développement Web (legacy)',
            'code' => 'LEGACY-DEV-TNG',
        ]);
        $this->assertFalse($legacy->isOfficial());

        $html = $this->actingAs($this->adminTanger)
            ->get(route('students.create'))
            ->assertOk()
            ->getContent();

        // Identifiants des 6 officielles présentes.
        foreach (self::OFFICIAL_CODES as $code) {
            $official = Formation::officialFor($this->centerTanger->id, $code);
            $this->assertNotNull($official, "Formation officielle $code attendue.");
            $this->assertStringContainsString('value="' . $official->id . '"', $html);
        }

        // Identifiant legacy absent de la liste.
        $this->assertStringNotContainsString('value="' . $legacy->id . '"', $html);
        $this->assertStringNotContainsString('Développement Web (legacy)', $html);

        // Aucune formation d'un autre centre n'apparaît pour Admin Centre Tanger.
        $other = Formation::officialFor($this->centerAgadir->id, 'sante');
        $this->assertStringNotContainsString('value="' . $other->id . '"', $html);
    }

    public function test_student_create_form_preselects_formation_from_card(): void
    {
        $target = Formation::officialFor($this->centerTanger->id, 'informatique');

        $html = $this->actingAs($this->adminTanger)
            ->get(route('students.create', ['formation_id' => $target->id]))
            ->assertOk()
            ->getContent();

        // L'option cible porte `selected` (préselection depuis la carte).
        $this->assertMatchesRegularExpression(
            '/<option value="' . preg_quote((string) $target->id, '/') . '"[^>]*selected/i',
            $html,
            'La formation préselectionnée depuis la carte doit être marquée selected.'
        );
    }

    // ─── 2. Formulaire Professeur (users.create / edit) ──────────────────────

    public function test_teacher_create_form_has_no_domain_field(): void
    {
        $html = $this->actingAs($this->superAdmin)
            ->get(route('users.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="domain"', $html);
        $this->assertStringNotContainsString("Domaine d'enseignement", $html);
        $this->assertStringNotContainsString('— Sélectionner le domaine —', $html);
        $this->assertStringContainsString('name="formation_id"', $html);
    }

    public function test_teacher_edit_form_has_no_domain_field(): void
    {
        $formation = Formation::officialFor($this->centerTanger->id, 'sante');
        $teacher = User::factory()->teacher($this->centerTanger->id)->create([
            'name' => 'Prof Test',
            'email' => 'prof@nodomain.test',
            'domain' => 'sante',
            'formation_id' => $formation->id,
        ]);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('users.edit', $teacher))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="domain"', $html);
        $this->assertStringNotContainsString('id="edit_domain"', $html);
        $this->assertStringContainsString('id="edit_formation_id"', $html);
    }

    // ─── 3. Backend : domaine non requis + validation centre stricte ─────────

    public function test_store_user_request_accepts_submission_without_domain(): void
    {
        $formation = Formation::officialFor($this->centerTanger->id, 'informatique');

        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Prof Sans Domaine',
            'email' => 'sans.domaine@nodomain.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => User::ROLE_TEACHER,
            'center_id' => $this->centerTanger->id,
            'status' => 'active',
            'formation_id' => $formation->id,
            // Pas de champ "domain" transmis.
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'sans.domaine@nodomain.test')->first();
        $this->assertNotNull($user);
        $this->assertSame($formation->id, $user->formation_id);
        // Métadonnée interne : la dérivation depuis la formation est un
        // confort technique (scope historique), pas une obligation métier.
        // On vérifie uniquement que le champ n'est plus exigé à la soumission.
        $this->assertTrue(
            $user->domain === 'informatique' || $user->domain === null,
            'La métadonnée domain doit être dérivée depuis la formation (ou absente).'
        );
    }

    public function test_store_student_request_does_not_require_domain_but_validates_center(): void
    {
        $formationTanger = Formation::officialFor($this->centerTanger->id, 'sante');
        $formationAgadir = Formation::officialFor($this->centerAgadir->id, 'sante');

        // Soumission croisée (Admin Tanger tente d'inscrire à une formation
        // d'Agadir) : refus côté serveur.
        $response = $this->actingAs($this->adminTanger)->post(route('students.store'), [
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'center_id' => $this->centerAgadir->id, // ignoré (admin Tanger forcé)
            'formation_id' => $formationAgadir->id,
        ]);

        $response->assertSessionHasErrors('formation_id');
        $this->assertDatabaseMissing('students', ['first_name' => 'Karim', 'last_name' => 'Benali']);

        // Soumission correcte : pas de champ `domain`, formation officielle du
        // centre → acceptée.
        $ok = $this->actingAs($this->adminTanger)->post(route('students.store'), [
            'first_name' => 'Salma',
            'last_name' => 'Idrissi',
            'formation_id' => $formationTanger->id,
        ]);
        $ok->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['first_name' => 'Salma', 'last_name' => 'Idrissi']);
    }

    // ─── 4. Catalogue : formations officielles seulement, filtrées par centre ─

    public function test_selection_helper_returns_only_official_active_by_center(): void
    {
        // Rend la hotellerie officielle Tanger inactive (plutôt que d'essayer
        // d'en créer une deuxième, ce qui violerait l'index UNIQUE par centre).
        $inactive = Formation::officialFor($this->centerTanger->id, 'hotellerie');
        $inactive->update(['status' => 'inactive']);

        // Une legacy ne doit jamais être proposée.
        $legacy = Formation::factory()
            ->forCenter($this->centerTanger)
            ->forDomain('sante')
            ->create(['code' => 'LEGACY-TNG']);

        $list = Formation::selectionForCenters([(int) $this->centerTanger->id]);
        $ids = array_column($list, 'id');

        $this->assertCount(5, $list, 'Six officielles créées, -1 inactive = 5.');
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($legacy->id, $ids);
        foreach ($list as $row) {
            $this->assertArrayHasKey('classes', $row);
            $this->assertLessThanOrEqual(Formation::MAX_CLASSES, count($row['classes']));
        }
    }

    // ─── 5. Design / compatibilité FR + AR ───────────────────────────────────

    public function test_student_create_form_renders_in_arabic_rtl_without_domain(): void
    {
        $adminAr = User::factory()->centerAdmin($this->centerTanger->id)
            ->create(['email' => 'ar@nodomain.test', 'locale' => 'ar']);

        $html = $this->actingAs($adminAr)
            ->get(route('students.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        // Aucun résidu français « Domaine ».
        $this->assertStringNotContainsString('Domaine de formation', $html);
        $this->assertStringNotContainsString('name="domain"', $html);
    }
}
