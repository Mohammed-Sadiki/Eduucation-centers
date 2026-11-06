<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\StagiaireDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasStagiaireFormData;
use Tests\TestCase;

/**
 * Simplification finale de la page « Tous les stagiaires ».
 *
 * La page ne contient plus que : un en-tête « Tous les stagiaires », une carte
 * statistique unique « Total stagiaires » (calculée sur la base, dans le
 * périmètre autorisé), la barre de recherche/filtres conservée, puis le
 * tableau complet. Les trois onglets de dossiers, leurs compteurs, le bouton
 * « Documents » et le bouton « Nouveau stagiaire » de l'en-tête ont disparu.
 * Aucune donnée ni fonctionnalité métier n'est supprimée : documents, tarifs,
 * périodes de stage, actions et accès restent intacts.
 *
 * La colonne « N° CIN » a ensuite été retirée du tableau (Super Admin et Admin
 * Centre) : sept colonnes restent, la largeur se répartit automatiquement, et le
 * CIN demeure en base, modifiable au formulaire, visible sur la fiche et pris en
 * charge par la recherche.
 */
class StagiaireListSimplificationTest extends TestCase
{
    use HasStagiaireFormData;
    use RefreshDatabase;

    protected Center $berlin;
    protected Center $munich;
    protected User $superAdmin;
    protected User $adminBerlin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->berlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->munich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa-list@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->berlin->id)->create(['email' => 'ab-list@test.de']);
    }

    private function makeStagiaire(array $attrs = []): Stagiaire
    {
        return Stagiaire::create(array_merge([
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'cin_number' => 'C1000000',
            'stage_type' => 'Stage pratique',
            'stage_start_date' => '2026-11-01',
            'stage_end_date' => '2027-02-28',
            'stage_price' => 1200.00,
            'currency' => 'EUR',
            'status' => 'en_cours',
            'center_id' => $this->berlin->id,
        ], $attrs));
    }

    /**
     * Contenu de la carte « Total stagiaires » (extrait du HTML de la page).
     */
    private function totalCardHtml(string $html): string
    {
        $start = strpos($html, 'id="card-total-stagiaires"');
        $this->assertNotFalse($start, 'La carte « Total stagiaires » doit être présente.');

        // Borné sur la légende de la carte (FR ou AR selon la locale active), pour ne
        // pas déborder sur le panneau de filtres qui partage les mêmes classes.
        $end = strpos($html, 'Stagiaires enregistrés', $start);
        if ($end === false) {
            $end = strpos($html, 'متدربون مسجلون', $start);
        }
        $length = $end === false ? 3000 : $end - $start + 40;

        return substr($html, $start, $length);
    }

    /**
     * Valeur numérique affichée par la carte « Total stagiaires ».
     */
    private function renderedTotal(string $html): string
    {
        $card = $this->totalCardHtml($html);

        $this->assertSame(1, preg_match('/text-3xl[^>]*>\s*(\d+)\s*</', $card, $m), 'Le nombre doit être affiché sur la carte.');

        return $m[1];
    }

    /**
     * HTML du tableau de la liste (unique sur la page).
     */
    private function tableHtml(string $page): string
    {
        $this->assertSame(1, preg_match('#<table class="ec-table">.*?</table>#s', $page, $m), 'Le tableau de la liste doit être rendu.');

        return $m[0];
    }

    /**
     * Libellés des colonnes (<th>), dans l'ordre de rendu.
     *
     * @return list<string>
     */
    private function tableHeaders(string $page): array
    {
        $this->assertSame(1, preg_match('#<thead>.*?</thead>#s', $this->tableHtml($page), $m));
        preg_match_all('#<th[^>]*>(.*?)</th>#s', $m[0], $cells);

        return array_map(static fn (string $h): string => trim(strip_tags($h)), $cells[1]);
    }

    /**
     * Nombre de cellules (<td>) de la première ligne du corps du tableau.
     */
    private function firstTableRowCells(string $page): int
    {
        $this->assertSame(1, preg_match('#<tbody>.*?<tr>(.*?)</tr>#s', $this->tableHtml($page), $m), 'Une ligne doit être rendue.');

        return substr_count($m[1], '<td');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1 & 2. Titre renommé, ancien titre supprimé
    // ──────────────────────────────────────────────────────────────────────────

    public function test_page_is_renamed_to_tous_les_stagiaires(): void
    {
        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();

        // Titre + sous-titre demandés.
        $this->assertStringContainsString('<h1', $page);
        $this->assertStringContainsString('Tous les stagiaires', $page);
        $this->assertStringContainsString('Consultez et gérez', $page);

        // Plus aucune référence visible au concept « dossiers » non plus.
        $this->assertStringNotContainsString('Dossiers Stagiaires', $page);
        $this->assertStringNotContainsString('pièces d\'identité, conventions, diplômes et prix convenu', $page);
        $this->assertStringNotContainsString('Consulter le dossier', $page);

        // Le <title> suit la page.
        $this->assertStringContainsString('Tous les stagiaires', $page);
    }

    public function test_super_admin_gets_all_centers_subtitle(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertSee('Consultez et gérez tous les stagiaires enregistrés dans les centres de formation.', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3 & 4. Onglets et compteurs de dossiers supprimés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dossier_tabs_and_counters_are_gone(): void
    {
        $this->makeStagiaire(['last_name' => 'Complet', 'cin_recto_path' => 'stagiaires/cin/recto.pdf']);
        $this->makeStagiaire(['last_name' => 'Incomplet', 'cin_number' => null, 'stage_start_date' => null, 'stage_end_date' => null]);

        $response = $this->actingAs($this->superAdmin)->get(route('stagiaires.index'));
        $page = $response->assertOk()->getContent();

        // Les trois onglets et leurs badges/compteurs ont disparu.
        $this->assertStringNotContainsString('Tous les dossiers', $page);
        $this->assertStringNotContainsString('Dossiers complets', $page);
        $this->assertStringNotContainsString('Dossiers incomplets', $page);
        $this->assertStringNotContainsString("['filter' => 'complets']", $page);
        $this->assertStringNotContainsString('name="filter"', $page);

        // Le contrôleur ne prépare plus ces compteurs.
        $response->assertViewHas('totalStagiaires');
        $response->assertViewMissing('completsCount');
        $response->assertViewMissing('incompletsCount');
        $response->assertViewMissing('totalCount');
        $response->assertViewMissing('filter');

        // La liste n'est limitée par aucun état de dossier : les deux sont affichés.
        $response->assertSee('Complet')->assertSee('Incomplet');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5 & 6. Boutons « Documents » et « Nouveau stagiaire » retirés de l'en-tête
    // ──────────────────────────────────────────────────────────────────────────

    public function test_header_has_no_documents_or_create_buttons(): void
    {
        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();

        // Bouton « Documents » retiré de la page liste.
        $this->assertStringNotContainsString('stagiaires/documents', $page);

        // Bouton « Nouveau stagiaire » retiré (la création reste dans la sidebar).
        $this->assertStringNotContainsString('btn-nouveau-stagiaire', $page);
        $this->assertStringNotContainsString('Nouveau stagiaire', $page);

        // Aucun en-tête de page ne contient de bouton : l'en-tête est un simple bloc.
        $header = substr($page, strpos($page, 'space-y-6'), 700);
        $this->assertStringNotContainsString('<a href', substr($header, 0, strpos($header, 'card-total-stagiaires') ?: 700));
    }

    public function test_documents_page_still_works_through_its_own_route(): void
    {
        $stagiaire = $this->makeStagiaire(['last_name' => 'Archives']);
        StagiaireDocument::create([
            'stagiaire_id' => $stagiaire->id,
            'title' => 'Convention de stage',
            'type' => 'convention',
            'file_path' => 'stagiaires/docs/convention.pdf',
            'file_name' => 'convention.pdf',
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.documents'))
            ->assertOk()
            ->assertSee('Convention de stage')
            ->assertSee('Archives');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. Une seule carte statistique, placée entre l'en-tête et les filtres
    // ──────────────────────────────────────────────────────────────────────────

    public function test_exactly_one_statistic_card_is_displayed(): void
    {
        $this->makeStagiaire();
        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();

        // Une carte, une seule fois.
        $this->assertSame(1, substr_count($page, 'card-total-stagiaires'));
        $this->assertSame(1, substr_count($page, 'Total stagiaires'));
        $this->assertSame(1, substr_count($page, 'Stagiaires enregistrés'));

        // Position : après le titre, avant la barre de filtres, avant le tableau.
        // (Le sélecteur de statut balise la barre de filtres : le topbar contient lui
        // aussi un champ name="search", trop générique pour servir de repère.)
        $titlePos  = strpos($page, '<h1');
        $cardPos   = strpos($page, 'card-total-stagiaires');
        $filterPos = strpos($page, 'name="status"');
        $tablePos  = strpos($page, 'ec-table');

        $this->assertLessThan($cardPos, $titlePos);
        $this->assertLessThan($filterPos, $cardPos);
        $this->assertLessThan($tablePos, $filterPos);

        // Aucune autre carte statistique / graphique / compteur secondaire.
        $this->assertStringNotContainsString('Nouveaux', $page);
        $this->assertStringNotContainsString('En stage', $page);
        $this->assertStringNotContainsString('Terminés', $page);
        $this->assertStringNotContainsString('Incomplets', $page);
        // Pas de graphique instancié sur cette page (le CDN chart.js est global au layout).
        $this->assertStringNotContainsString('canvas', $page);
        $this->assertStringNotContainsString('new Chart(', $page);
    }

    public function test_card_respects_the_visual_charter(): void
    {
        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();

        $card = $this->totalCardHtml($page);

        // Carte blanche, coins arrondis, bordure légère, accent violet, icône.
        $this->assertStringContainsString('bg-white', $card);
        $this->assertStringContainsString('rounded-2xl', $card);
        $this->assertStringContainsString('border-slate-200', $card);
        $this->assertStringContainsString('bg-purple-600', $card);
        $this->assertStringContainsString('text-3xl', $card);

        // Dark Mode + responsive.
        $this->assertStringContainsString('dark:bg-slate-900', $card);
        $this->assertStringContainsString('dark:border-slate-800', $card);
        $this->assertStringContainsString('sm:max-w-xs', $card);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8 à 11. Total calculé sur la base, dans le périmètre autorisé
    // ──────────────────────────────────────────────────────────────────────────

    public function test_total_comes_from_the_database_and_is_not_hardcoded(): void
    {
        // 0 stagiaire → 0 affiché (pas de valeur codée en dur).
        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();
        $this->assertSame('0', $this->renderedTotal($page));

        // 5 stagiaires → 5 affiché.
        foreach (range(1, 5) as $i) {
            $this->makeStagiaire(['last_name' => 'Stagiaire' . $i, 'cin_number' => 'C500000' . $i]);
        }

        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->getContent();
        $this->assertSame('5', $this->renderedTotal($page));
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertViewHas('totalStagiaires', 5);

        // Pas de double comptage : le total correspond exactement aux lignes en base.
        $this->assertSame(5, Stagiaire::count());
    }

    public function test_total_is_updated_after_creation_and_deletion(): void
    {
        $stagiaire = $this->makeStagiaire(['last_name' => 'Durable']);

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertViewHas('totalStagiaires', 1);

        // Création via le formulaire du module.
        $this->actingAs($this->superAdmin)->post(route('stagiaires.store'), $this->stagiairePayloadWithCin([
            'first_name' => 'Nadia',
            'last_name' => 'Ajoutee',
            'gender' => 'femme',
            'nationality' => 'Allemande',
            'email' => 'nadia.ajoutee@example.de',
            'stage_price' => 700.00,
            'currency' => 'EUR',
            'status' => 'nouveau',
            'center_id' => $this->berlin->id,
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertViewHas('totalStagiaires', 2);

        // Suppression.
        $this->actingAs($this->superAdmin)->delete(route('stagiaires.destroy', $stagiaire));

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertViewHas('totalStagiaires', 1);
    }

    public function test_total_covers_all_centers_for_super_admin(): void
    {
        $this->makeStagiaire(['last_name' => 'Berlinais1']);
        $this->makeStagiaire(['last_name' => 'Berlinais2']);
        $this->makeStagiaire(['last_name' => 'Munichois', 'center_id' => $this->munich->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertViewHas('totalStagiaires', 3);

        // Et le nombre affiché sur la carte est bien 3.
        $page = $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->getContent();
        $this->assertSame('3', $this->renderedTotal($page));
    }

    public function test_total_is_per_center_for_admin_berlin_and_admin_munich(): void
    {
        $this->makeStagiaire(['last_name' => 'Berlin1']);
        $this->makeStagiaire(['last_name' => 'Berlin2']);
        $this->makeStagiaire(['last_name' => 'Berlin3']);
        $this->makeStagiaire(['last_name' => 'Munich1', 'center_id' => $this->munich->id]);
        $this->makeStagiaire(['last_name' => 'Munich2', 'center_id' => $this->munich->id]);

        // Le périmètre de calcul est bien par centre : 3 à Berlin, 2 à Munich, 5 au total.
        $this->assertSame(3, Stagiaire::where('center_id', $this->berlin->id)->count());
        $this->assertSame(2, Stagiaire::where('center_id', $this->munich->id)->count());

        // Accès au module : l'Admin Centre y accède, strictement limité à son centre.
        $pageBerlin = $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertSee('Berlin1')
            ->assertSee('Berlin3')
            ->assertDontSee('Munich1')
            ->getContent();
        $this->assertSame('3', $this->renderedTotal($pageBerlin));

        $adminMunich = User::factory()->centerAdmin($this->munich->id)->create(['email' => 'am-list@test.de']);
        $pageMunich = $this->actingAs($adminMunich)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertSee('Munich1')
            ->assertDontSee('Berlin1')
            ->getContent();
        $this->assertSame('2', $this->renderedTotal($pageMunich));

        // La requête de la carte applique la même restriction de centre : un Admin
        // Centre ne compte que les stagiaires de son centre.
        $scopedBerlin = Stagiaire::query()->where('center_id', $this->adminBerlin->center_id)->count();
        $scopedMunich = Stagiaire::query()->where('center_id', $adminMunich->center_id)->count();

        $this->assertSame(3, $scopedBerlin);
        $this->assertSame(2, $scopedMunich);
    }

    public function test_total_stays_stable_while_the_list_is_filtered(): void
    {
        $this->makeStagiaire(['last_name' => 'Muller', 'status' => 'en_cours']);
        $this->makeStagiaire(['last_name' => 'Schmidt', 'status' => 'termine', 'center_id' => $this->munich->id]);
        $this->makeStagiaire(['last_name' => 'Weber', 'status' => 'nouveau']);

        foreach ([
            ['search' => 'Muller'],
            ['status' => 'nouveau'],
            ['center_id' => $this->munich->id],
            ['search' => 'nope'],
        ] as $query) {
            $this->actingAs($this->superAdmin)
                ->get(route('stagiaires.index', $query))
                ->assertOk()
                ->assertViewHas('totalStagiaires', 3);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12 & 13. Recherche et filtres conservés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_search_still_works(): void
    {
        $this->makeStagiaire(['first_name' => 'Alice', 'last_name' => 'Muller', 'cin_number' => 'AA111']);
        $this->makeStagiaire(['first_name' => 'Bruno', 'last_name' => 'Schmidt', 'cin_number' => 'BB222']);

        // Par nom.
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['search' => 'Muller']))
            ->assertOk()
            ->assertSee('Alice')
            ->assertDontSee('Schmidt');

        // Par CIN.
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['search' => 'BB222']))
            ->assertOk()
            ->assertSee('Bruno')
            ->assertDontSee('Alice');
    }

    public function test_status_and_center_filters_still_work(): void
    {
        $this->makeStagiaire(['last_name' => 'EnCours', 'status' => 'en_cours']);
        $this->makeStagiaire(['last_name' => 'Termine', 'status' => 'termine']);
        $this->makeStagiaire(['last_name' => 'Munich', 'status' => 'nouveau', 'center_id' => $this->munich->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['status' => 'termine']))
            ->assertOk()
            ->assertSee('Termine')
            ->assertDontSee('EnCours');

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['center_id' => $this->munich->id]))
            ->assertOk()
            ->assertSee('Munich')
            ->assertDontSee('EnCours');

        // La barre de filtres est bien présente (recherche, statut, centre, Filtrer).
        $page = $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->getContent();
        $this->assertStringContainsString('name="search"', $page);
        $this->assertStringContainsString('name="status"', $page);
        $this->assertStringContainsString('name="center_id"', $page);
        $this->assertStringContainsString('Filtrer', $page);
    }

    public function test_center_filter_is_hidden_for_non_super_admins(): void
    {
        // Le sélecteur de centre n'est réservé qu'au Super Admin.
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertSee('name="center_id"', false);

        // L'Admin Centre voit la liste de son centre, sans selecteur de centre.
        $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertDontSee('name="center_id"', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14 & 15. Tableau et actions conservés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_table_displays_all_expected_columns_and_data(): void
    {
        $this->makeStagiaire([
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'email' => 'karim.benali@test.de',
            'cin_number' => 'C987654',
            'stage_type' => 'Stage de perfectionnement',
            'stage_price' => 1234.50,
        ]);

        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertSee('Karim')
            ->assertSee('Benali')
            ->assertSee('karim.benali@test.de')
            ->assertSee('Stage de perfectionnement')
            ->assertSee('Centre Berlin')
            ->getContent();

        // Exactement les sept colonnes demandées, dans l'ordre. La colonne
        // « N° CIN » n'est plus rendue (mission « suppression du N° CIN »).
        $this->assertSame(
            ['Stagiaire', 'Centre', 'Type de stage', 'Période du stage', 'Prix convenu', 'Statut', 'Actions'],
            $this->tableHeaders($page)
        );

        // Montant et périodes du stage affichés.
        $this->assertStringContainsString('1 234,50', $page);
        $this->assertStringContainsString('01/11/2026', $page);
        $this->assertStringContainsString('28/02/2027', $page);
        // Statut conservé (avancement du stage, pas la complétude d'un dossier).
        $this->assertStringContainsString('Stage en cours', $page);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14 bis. Colonne « N° CIN » retirée du tableau (affichage uniquement)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cin_column_is_removed_from_the_table_for_both_roles(): void
    {
        $stagiaire = $this->makeStagiaire(['last_name' => 'SansColonne', 'cin_number' => 'C987654']);

        $roles = [
            'super admin'  => $this->superAdmin,
            'admin centre' => $this->adminBerlin,
        ];

        foreach ($roles as $role => $user) {
            $page = $this->actingAs($user)
                ->get(route('stagiaires.index'))
                ->assertOk()
                ->assertSee('SansColonne')
                ->getContent();

            // Sept colonnes, sept cellules par ligne : rien ne déborde.
            $this->assertCount(7, $this->tableHeaders($page), $role);
            $this->assertSame(7, $this->firstTableRowCells($page), $role);

            // En-tête et valeur du CIN ont disparu du tableau.
            $this->assertStringNotContainsString('N° CIN', $page, $role);
            $this->assertStringNotContainsString('C987654', $page, $role . ' : le numéro ne doit plus être affiché dans la liste.');
            // L'ancien état « non renseigné » de cette colonne n'est plus rendu non plus.
            $this->assertStringNotContainsString('Non renseigné', $page, $role);

            // Les actions selon les permissions du rôle.
            $this->assertStringContainsString('Consulter le stagiaire', $page, $role);
            if ($role === 'super admin') {
                $this->assertStringContainsString('Modifier le stagiaire', $page, $role);
                $this->assertStringContainsString('Supprimer le stagiaire', $page, $role);
            } elseif ($role === 'admin centre') {
                // Admin Centre est en consultation uniquement : ni modifier ni supprimer
                $this->assertStringNotContainsString('Modifier le stagiaire', $page, $role);
                $this->assertStringNotContainsString('Supprimer le stagiaire', $page, $role);
            }
        }

        // « Ne pas supprimer la colonne CIN de la base » : la donnée reste intacte.
        $this->assertDatabaseHas('stagiaires', ['id' => $stagiaire->id, 'cin_number' => 'C987654']);

        // La fiche du stagiaire continue d'afficher le CIN (rien d'autre modifié).
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.show', $stagiaire))
            ->assertOk()
            ->assertSee('C987654');

        // La recherche par CIN reste fonctionnelle (backend inchangé).
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index', ['search' => 'C987654']))
            ->assertOk()
            ->assertSee('SansColonne');
    }

    public function test_cin_column_is_absent_in_the_four_moroccan_centers(): void
    {
        $centers = [
            ['name' => 'Centre Tanger',     'code' => 'TGR'],
            ['name' => 'Centre Agadir',     'code' => 'AGD'],
            ['name' => 'Centre Essaouira',  'code' => 'ESS'],
            ['name' => 'Centre Tétouane',   'code' => 'TET'],
        ];

        foreach ($centers as $center) {
            $created = Center::factory()->create($center);
            $admin = User::factory()->centerAdmin($created->id)->create(['email' => strtolower($center['code']) . '-cin@test.ma']);
            $this->makeStagiaire([
                'last_name'  => 'Stagiaire' . $center['code'],
                'cin_number' => 'AA' . $center['code'],
                'center_id'  => $created->id,
            ]);

            $page = $this->actingAs($admin)
                ->get(route('stagiaires.index'))
                ->assertOk()
                ->assertSee('Stagiaire' . $center['code'])
                ->getContent();

            $this->assertSame(
                ['Stagiaire', 'Centre', 'Type de stage', 'Période du stage', 'Prix convenu', 'Statut', 'Actions'],
                $this->tableHeaders($page),
                $center['name']
            );
            $this->assertStringNotContainsString('N° CIN', $page, $center['name']);
            $this->assertStringNotContainsString('AA' . $center['code'], $page, $center['name'] . ' : le CIN ne doit plus être affiché.');
        }
    }

    public function test_cin_column_is_absent_in_arabic_and_empty_state_matches_columns(): void
    {
        // Ligne vide : l'unique <td> doit couvrir les sept colonnes restantes.
        $empty = $this->actingAs($this->adminBerlin)->get(route('stagiaires.index'))->assertOk()->getContent();
        $this->assertStringContainsString('colspan="7"', $empty);
        $this->assertStringNotContainsString('colspan="8"', $empty);

        $this->makeStagiaire(['last_name' => 'Muller', 'cin_number' => 'C987654']);

        // Libellés attendus tels que les produirait la traduction arabe (calculés
        // hors requête, puis rendus via le sélecteur de langue).
        $columns = ['Stagiaire', 'Centre', 'Type de stage', 'Période du stage', 'Prix convenu', 'Statut', 'Actions'];
        $current = App::getLocale();
        App::setLocale('ar');
        $expected = array_map(static fn (string $key): string => (string) __($key), $columns);
        App::setLocale($current);

        $this->actingAs($this->adminBerlin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $ar = $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $ar);

        // En-têtes traduits, sans la colonne CIN (« رقم CIN » en arabe).
        $this->assertSame($expected, $this->tableHeaders($ar));
        $this->assertStringNotContainsString('رقم CIN', $ar);
        $this->assertStringNotContainsString('C987654', $ar);
        $this->assertSame(7, $this->firstTableRowCells($ar));

        $this->actingAs($this->adminBerlin)->get(route('locale.switch', 'fr'))->assertRedirect();
    }

    public function test_row_actions_stay_functional(): void
    {
        $stagiaire = $this->makeStagiaire(['last_name' => 'Agir']);

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.show', $stagiaire))
            ->assertOk()
            ->assertSee('Agir');

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.edit', $stagiaire))
            ->assertOk()
            ->assertSee('Agir');

        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayloadWithCin([
                'first_name' => 'Agir',
                'last_name' => 'Modifie',
                'gender' => 'homme',
                'nationality' => 'Allemande',
                'email' => 'agir.modifie@example.de',
                'stage_price' => 1500.00,
                'currency' => 'EUR',
                'status' => 'termine',
                'center_id' => $this->berlin->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stagiaires', ['last_name' => 'Modifie', 'stage_price' => 1500.00]);

        // Suppression (avec confirmation côté client dans le tableau).
        $page = $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->getContent();
        $this->assertStringContainsString('Confirmez-vous la suppression de ce stagiaire', $page);

        $this->actingAs($this->superAdmin)
            ->delete(route('stagiaires.destroy', $stagiaire))
            ->assertRedirect(route('stagiaires.index'));

        $this->assertDatabaseMissing('stagiaires', ['id' => $stagiaire->id]);
    }

    public function test_pagination_is_preserved(): void
    {
        foreach (range(1, 14) as $i) {
            $this->makeStagiaire(['last_name' => 'Page' . $i, 'cin_number' => 'P' . str_pad((string) $i, 6, '0', STR_PAD_LEFT)]);
        }

        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertViewHas('stagiaires', fn ($paginated) => $paginated->count() === 12 && $paginated->total() === 14);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 16. Création toujours accessible depuis la sidebar
    // ──────────────────────────────────────────────────────────────────────────

    public function test_create_remains_available_from_the_sidebar(): void
    {
        $page = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();

        $sidebar = substr($page, strpos($page, '<aside'), strpos($page, '</aside>') - strpos($page, '<aside'));
        $this->assertStringContainsString('Ajouter un stagiaire', $sidebar);
        $this->assertStringContainsString(route('stagiaires.create'), $sidebar);
        $this->assertStringContainsString('Tous les stagiaires', $sidebar);

        // Le formulaire est intact.
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.create'))
            ->assertOk()
            ->assertSee('Entreprise où le stagiaire effectuera son stage');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 16bis. Sidebar Admin Centre : rubrique « Gestion des stagiaires » retirée
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_sidebar_has_no_stagiaires_section(): void
    {
        // Page classique de l'Admin Centre (layout avec sidebar) : le groupe
        // « Gestion des stagiaires » doit avoir disparu du menu latéral.
        $page = $this->actingAs($this->adminBerlin)
            ->get(route('formations.index'))
            ->assertOk()
            ->getContent();

        $start = strpos($page, '<aside');
        $sidebar = substr($page, $start, strpos($page, '</aside>', $start) - $start);

        // Rubrique entièrement absente : titre, flèche accordéon et deux liens.
        $this->assertStringNotContainsString('Gestion des stagiaires', $sidebar);
        $this->assertStringNotContainsString('Tous les stagiaires', $sidebar);
        $this->assertStringNotContainsString('Ajouter un stagiaire', $sidebar);
        $this->assertStringNotContainsString(route('stagiaires.index'), $sidebar);
        $this->assertStringNotContainsString(route('stagiaires.create'), $sidebar);

        // Mission « sidebar plate Admin Centre » : plus aucun titre de
        // catégorie n'est rendu pour l'Admin Centre (navigation plate).
        $this->assertStringNotContainsString('Pédagogie', $sidebar);
        $this->assertStringNotContainsString('Communication', $sidebar);
        // La navigation plate reste fonctionnelle : liens cibles en place.
        // Le lien « Domaines » a été retiré du menu (la page Domaines est
        // conservée et reste atteignable depuis Accueil / les dashboards).
        $this->assertStringNotContainsString('href="' . route('center-admin.domaines') . '"', $sidebar);
        $this->assertStringContainsString(route('notifications.index'), $sidebar);

        // La carte « Gestion des stagiaires » reste visible et fonctionnelle sur Accueil.
        $home = $this->actingAs($this->adminBerlin)
            ->get(route('admin.home'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('card-home-stagiaires', $home);
        $this->assertStringContainsString(route('stagiaires.index'), $home);

        // Le module reste accessible depuis Accueil avec isolation centre,
        // et la sidebar du contexte Stagiaires (Super Admin) est inchangée.
        $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.index'))
            ->assertOk();

        $saSidebar = (function ($html) {
            $s = strpos($html, '<aside');
            return substr($html, $s, strpos($html, '</aside>', $s) - $s);
        })($this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->getContent());
        $this->assertStringContainsString('Gestion des stagiaires', $saSidebar);
        $this->assertStringContainsString('Tous les stagiaires', $saSidebar);
        $this->assertStringContainsString('Ajouter un stagiaire', $saSidebar);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 17 & 18. Données documents / financières intactes
    // ──────────────────────────────────────────────────────────────────────────

    public function test_documents_and_stored_files_are_untouched(): void
    {
        $stagiaire = $this->makeStagiaire([
            'last_name' => 'Documente',
            'cin_recto_path' => 'stagiaires/cin/recto-99.pdf',
            'cin_verso_path' => 'stagiaires/cin/verso-99.pdf',
        ]);

        StagiaireDocument::create([
            'stagiaire_id' => $stagiaire->id,
            'title' => 'Diplôme original',
            'type' => 'diploma',
            'file_path' => 'stagiaires/99/diplome.pdf',
            'file_name' => 'diplome.pdf',
        ]);

        // Visiter la page simplifiée ne supprime rien.
        $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->assertOk();

        $this->assertDatabaseHas('stagiaires', [
            'id' => $stagiaire->id,
            'cin_recto_path' => 'stagiaires/cin/recto-99.pdf',
            'cin_verso_path' => 'stagiaires/cin/verso-99.pdf',
        ]);
        $this->assertDatabaseCount('stagiaire_documents', 1);
        $this->assertDatabaseHas('stagiaire_documents', [
            'stagiaire_id' => $stagiaire->id,
            'file_path' => 'stagiaires/99/diplome.pdf',
        ]);
    }

    public function test_financial_and_stage_data_remain_intact(): void
    {
        $stagiaire = $this->makeStagiaire([
            'last_name' => 'Compta',
            'stage_price' => 2400.75,
            'currency' => 'EUR',
            'stage_start_date' => '2026-12-01',
            'stage_end_date' => '2027-05-30',
        ]);

        $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('stagiaires.documents'))->assertOk();

        $fresh = $stagiaire->fresh();
        $this->assertSame(2400.75, (float) $fresh->stage_price);
        $this->assertSame('EUR', $fresh->currency);
        $this->assertSame('2026-12-01', $fresh->stage_start_date->format('Y-m-d'));
        $this->assertSame('2027-05-30', $fresh->stage_end_date->format('Y-m-d'));
        $this->assertSame('en_cours', $fresh->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 19. FR/AR, RTL, Light/Dark Mode
    // ──────────────────────────────────────────────────────────────────────────

    public function test_page_renders_in_arabic_with_rtl_and_keeps_one_card(): void
    {
        $this->makeStagiaire(['last_name' => 'Muller']);

        // Francisation d'abord : pas de RTL.
        $fr = $this->actingAs($this->superAdmin)->get(route('stagiaires.index'))->getContent();
        $this->assertStringContainsString('dir="ltr"', $fr);

        // Passage à l'arabe via le sélecteur de langue.
        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $ar = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $ar);
        $this->assertStringContainsString('جميع المتدربين', $ar);   // Tous les stagiaires
        $this->assertStringContainsString('إجمالي المتدربين', $ar);   // Total stagiaires

        // Une seule carte statistique, aucun reliquat de dossiers.
        $this->assertSame(1, substr_count($ar, 'card-total-stagiaires'));
        $this->assertStringNotContainsString('stagiaires/documents', $ar);

        // Dark Mode : les classes sombres de la carte sont rendues.
        $this->assertStringContainsString('dark:bg-slate-900', $this->totalCardHtml($ar));
    }

    public function test_locale_is_restored_to_french_for_other_requests(): void
    {
        App::setLocale('fr');
        $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.index'))
            ->assertOk()
            ->assertSee('Total stagiaires', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 20. Aucun impact sur les autres modules
    // ──────────────────────────────────────────────────────────────────────────

    public function test_other_modules_are_not_impacted(): void
    {
        // Élèves, Communication, Finance, domaines : inchangés.
        $this->actingAs($this->superAdmin)->get(route('students.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('messages.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.finance.dashboard'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.domain.dashboard', 'informatique'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('admin.home'))->assertOk();

        // La sidebar pédagogique des domaines n'a pas gagné d'éléments stagiaires.
        $domain = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'informatique'))
            ->getContent();
        $sidebar = substr($domain, strpos($domain, '<aside'), strpos($domain, '</aside>') - strpos($domain, '<aside'));

        $this->assertStringContainsString('Pédagogie', $sidebar);
        $this->assertStringNotContainsString('Total stagiaires', $sidebar);
        $this->assertStringNotContainsString('Tous les stagiaires', $sidebar);

        // Espace enseignant toujours fonctionnel.
        $teacher = User::factory()->teacher($this->berlin->id)->create(['email' => 'tc-list@test.de']);
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
    }
}
