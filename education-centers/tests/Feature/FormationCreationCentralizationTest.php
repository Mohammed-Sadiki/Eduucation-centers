<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Centralisation de la création des FORMATIONS dans la page « Domaines de formation ».
 *
 * Vérifie : bouton « + Ajouter une formation » placé à côté de « + Ajouter un élève »,
 * page indépendante (aucune sidebar pédagogique), champ domaine obligatoire,
 * centre forcé côté serveur selon le rôle (non contournable par requête modifiée),
 * association centre + domaine correcte, disparition des anciens boutons internes,
 * et listes / modifications existantes toujours fonctionnelles.
 */
class FormationCreationCentralizationTest extends TestCase
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

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['email' => 'ab@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['email' => 'am@test.de']);

        // Formation existante (sans domaine renseigné) : simule un dossier antérieur
        // à l'obligation « domaine », pour vérifier qu'il reste intact et consultable.
        Formation::create([
            'center_id' => $this->centerBerlin->id,
            'name' => 'Ancienne Formation Berlin',
            'code' => 'OLD-001',
            'status' => 'active',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  1-2. Bouton présent à côté de « Ajouter un élève » + les deux fonctionnent
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_domaines_shows_formation_button_beside_student_button(): void
    {
        // Les deux actions rapides sont désormais hébergées par la carte
        // Formations de la page d'accueil admin (admin.home), plus par la
        // page Domaines.
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.home'))
            ->assertOk()
            ->assertSee('btn-add-student-domaines', false)
            ->assertSee('btn-add-formation-domaines', false)
            ->assertSee('Ajouter un élève')
            // Nouveau parcours : le bouton formation mène directement au
            // formulaire existant « Nouvelle formation générale » (aucune page
            // intermédiaire) ; la liste des six cartes reste dans la cellule
            // « Accéder aux formations ».
            ->assertSee('Ajouter une formation')
            ->assertSee(route('students.create'), false)
            ->assertSee(route('formations-generales.create'), false);

        $html = $response->getContent();
        // Le bouton formation suit immédiatement le bouton élève (ordre visuel « côte à côte »).
        $this->assertLessThan(
            strpos($html, 'btn-add-formation-domaines'),
            strpos($html, 'btn-add-student-domaines'),
            'Le bouton « Ajouter une formation » doit apparaître après « Ajouter un élève ».'
        );
    }

    public function test_center_admin_domaines_shows_formation_button_beside_student_button(): void
    {
        foreach ([$this->adminBerlin, $this->adminMunich] as $admin) {
            $response = $this->actingAs($admin)
                ->get(route('admin.home'))
                ->assertOk()
                ->assertSee('btn-add-student-ca-domaines', false)
                ->assertSee('btn-add-formation-ca-domaines', false)
                ->assertSee('Ajouter un élève')
                ->assertSee('Ajouter une formation')
                ->assertSee(route('students.create'), false)
                ->assertSee(route('formations-generales.create'), false);

            $html = $response->getContent();
            $this->assertLessThan(
                strpos($html, 'btn-add-formation-ca-domaines'),
                strpos($html, 'btn-add-student-ca-domaines')
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  3. Accès au formulaire + 14. page indépendante (aucune sidebar)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_create_page_is_standalone_without_pedagogical_sidebar(): void
    {
        foreach ([$this->superAdmin, $this->adminBerlin] as $admin) {
            $response = $this->actingAs($admin)
                ->get(route('formations.create'))
                ->assertOk()
                ->assertSee('standalone-layout', false)
                ->assertSee('btn-back-domaines', false)
                // Contrôles de thème présents dans la topbar indépendante.
                ->assertSee('toggleTheme', false);

            // Aucune navigation du dashboard pédagogique n'est rendue.
            foreach (['teachers.index', 'classes.index', 'attendances.index', 'schedules.index', 'payments.index'] as $name) {
                $response->assertDontSee(route($name), false);
            }
            $response->assertDontSee('Pédagogie');
        }
    }

    public function test_create_page_shows_title_and_back_to_domaines(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('formations.create'))
            ->assertOk()
            ->assertSee('Ajouter une formation')
            ->assertSee('Retour aux formations générales')
            ->assertSee(route('super-admin.domaines'), false);

        $this->actingAs($this->adminBerlin)
            ->get(route('formations.create'))
            ->assertOk()
            ->assertSee(route('center-admin.domaines'), false)
            ->assertDontSee(route('super-admin.domaines'), false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  8. Admin Centre : aucun sélecteur de centre (auto)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_form_has_no_center_selector_but_super_admin_does(): void
    {
        // Super Admin : sélecteur de centre présent.
        $this->actingAs($this->superAdmin)
            ->get(route('formations.create'))
            ->assertOk()
            ->assertSee('name="center_id"', false)
            ->assertSee('name="domain"', false)
            ->assertSee('Centre Berlin')
            ->assertSee('Centre Munich');

        // Admin Centre : pas de sélecteur, bandeau « Automatique ».
        foreach ([[$this->adminBerlin, 'Centre Berlin'], [$this->adminMunich, 'Centre Munich']] as [$admin, $centerName]) {
            $this->actingAs($admin)
                ->get(route('formations.create'))
                ->assertOk()
                ->assertDontSee('name="center_id"', false)
                ->assertSee('name="domain"', false)
                ->assertSee('Automatique', false)
                ->assertSee($centerName);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  4-5. Super Admin crée à Berlin puis Munich (association centre + domaine)
    //  10. La formation est associée au bon domaine
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_formation_in_berlin_with_domain(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('formations.store'), [
            'center_id' => $this->centerBerlin->id,
            'domain' => 'sante',
            'name' => 'Soins Infirmiers Basics',
            'code' => 'SIB-01',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('formations', [
            'center_id' => $this->centerBerlin->id,
            'domain' => 'sante',
            'code' => 'SIB-01',
            'name' => 'Soins Infirmiers Basics',
        ]);
    }

    public function test_super_admin_can_create_formation_in_munich_with_domain(): void
    {
        $this->actingAs($this->superAdmin)->post(route('formations.store'), [
            'center_id' => $this->centerMunich->id,
            'domain' => 'design-graphique',
            'name' => 'Graphisme Initiation',
            'code' => 'GRA-01',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('formations', [
            'center_id' => $this->centerMunich->id,
            'domain' => 'design-graphique',
            'code' => 'GRA-01',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  6-7. Admin Berlin / Munich créent dans leur centre (aucun center_id transmis)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_berlin_creates_in_own_center_without_center_id(): void
    {
        $response = $this->actingAs($this->adminBerlin)->post(route('formations.store'), [
            'domain' => 'informatique',
            'name' => 'Développement Web Berlin',
            'code' => 'WEB-BER',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('formations', [
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'code' => 'WEB-BER',
        ]);
    }

    public function test_center_admin_munich_creates_in_own_center_without_center_id(): void
    {
        $this->actingAs($this->adminMunich)->post(route('formations.store'), [
            'domain' => 'hotellerie',
            'name' => 'Réception Hôtelière Munich',
            'code' => 'HOT-MUN',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('formations', [
            'center_id' => $this->centerMunich->id,
            'domain' => 'hotellerie',
            'code' => 'HOT-MUN',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  9. Requête HTTP modifiée : un Admin Berlin ne peut jamais créer à Munich
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_cannot_cross_centers_via_spoofed_request(): void
    {
        $response = $this->actingAs($this->adminBerlin)->post(route('formations.store'), [
            'center_id' => $this->centerMunich->id, // injection : doit être ignorée
            'domain' => 'informatique',
            'name' => 'Tentative Munich',
            'code' => 'SPOOF-1',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        // Forcé à Berlin, jamais à Munich.
        $this->assertDatabaseHas('formations', [
            'center_id' => $this->centerBerlin->id,
            'code' => 'SPOOF-1',
        ]);
        $this->assertDatabaseMissing('formations', [
            'center_id' => $this->centerMunich->id,
            'code' => 'SPOOF-1',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  10 (bis). Domaine inconnu refusé
    // ──────────────────────────────────────────────────────────────────────────

    public function test_unknown_domain_slug_is_rejected(): void
    {
        $response = $this->actingAs($this->adminBerlin)->post(route('formations.store'), [
            'domain' => 'domaine-inexistant',
            'name' => 'Hors Domaine',
            'code' => 'BAD-1',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('domain');
        $this->assertDatabaseMissing('formations', ['code' => 'BAD-1']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  16. Erreurs de validation affichées (domaine désormais obligatoire)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_validation_errors_are_shown_for_missing_required_fields(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('formations.store'), [
            'center_id' => $this->centerBerlin->id,
            // domain, name, code, status manquants
        ]);

        $response->assertSessionHasErrors(['domain', 'name', 'code', 'status']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  11. La formation apparaît dans les listes appropriées (filtrage centre + domaine)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_created_formation_appears_in_center_list_and_domain_scope(): void
    {
        $this->actingAs($this->adminBerlin)->post(route('formations.store'), [
            'domain' => 'maconnerie',
            'name' => 'Maçonnerie Traditionnelle',
            'code' => 'MAC-TRAD',
            'status' => 'active',
        ])->assertRedirect();

        // Liste du centre (Admin Berlin).
        $this->actingAs($this->adminBerlin)
            ->get(route('formations.index'))
            ->assertOk()
            ->assertSee('Maçonnerie Traditionnelle');

        // La formation n'apparaît PAS dans la liste de Munich (isolation centre).
        $this->actingAs($this->adminMunich)
            ->get(route('formations.index'))
            ->assertOk()
            ->assertDontSee('Maçonnerie Traditionnelle');

        // Filtrage métier : la bonne association centre + domaine est en base.
        $this->assertDatabaseHas('formations', [
            'name' => 'Maçonnerie Traditionnelle',
            'center_id' => $this->centerBerlin->id,
            'domain' => 'maconnerie',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  12. Les anciens boutons internes de création ont disparu
    // ──────────────────────────────────────────────────────────────────────────

    public function test_formation_list_no_longer_shows_internal_create_button(): void
    {
        foreach ([$this->superAdmin, $this->adminBerlin] as $admin) {
            $this->actingAs($admin)
                ->get(route('formations.index'))
                ->assertOk()
                ->assertDontSee('btn-create-formation', false)
                ->assertDontSee('Nouvelle formation')
                ->assertDontSee(route('formations.create'), false);
        }
    }

    public function test_domain_dashboard_no_longer_links_to_formation_creation(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'))
            ->assertOk()
            ->assertDontSee(route('formations.create'), false)
            ->assertDontSee('Créer une formation');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  13 & 17. Listes / modifications et formations existantes intactes
    // ──────────────────────────────────────────────────────────────────────────

    public function test_existing_formations_remain_consultable_and_editable(): void
    {
        $existing = Formation::where('code', 'OLD-001')->firstOrFail();

        // Consultation.
        $this->actingAs($this->adminBerlin)
            ->get(route('formations.show', $existing))
            ->assertOk()
            ->assertSee('Ancienne Formation Berlin');

        // Édition toujours possible (le domaine n'est pas exigé en mise à jour).
        $this->actingAs($this->adminBerlin)
            ->put(route('formations.update', $existing), [
                'name' => 'Ancienne Formation Berlin',
                'code' => 'OLD-001',
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('formations', ['code' => 'OLD-001', 'status' => 'inactive']);

        // La création d'une nouvelle formation ne touche pas l'existante.
        $this->actingAs($this->adminBerlin)->post(route('formations.store'), [
            'domain' => 'informatique',
            'name' => 'Nouvelle Formation',
            'code' => 'NEW-001',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('formations', ['code' => 'OLD-001', 'name' => 'Ancienne Formation Berlin']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  18. Aucun autre module impacté (aucun compte utilisateur créé, élèves OK)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_formation_creation_does_not_affect_other_modules(): void
    {
        $usersBefore = User::count();
        $studentsBefore = Student::count();

        $this->actingAs($this->adminBerlin)->post(route('formations.store'), [
            'domain' => 'informatique',
            'name' => 'Module Isolation Test',
            'code' => 'ISO-01',
            'status' => 'active',
        ])->assertRedirect();

        // Créer une formation ne crée aucun compte utilisateur ni dossier élève.
        $this->assertSame($usersBefore, User::count());
        $this->assertSame($studentsBefore, Student::count());

        // La page « Ajouter un élève » reste fonctionnelle (non régressée).
        $this->actingAs($this->adminBerlin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee('standalone-layout', false);
    }

    public function test_teacher_and_student_cannot_reach_formation_creation(): void
    {
        $teacher = User::factory()->teacher($this->centerBerlin->id)->create(['email' => 't@test.de']);

        $this->actingAs($teacher)->get(route('formations.create'))->assertForbidden();
        $this->actingAs($teacher)->post(route('formations.store'), [])->assertForbidden();
    }
}
