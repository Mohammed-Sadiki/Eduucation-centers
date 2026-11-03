<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard pédagogique simplifié pour les Admin Centres.
 *
 * Le dashboard d'un domaine (« Espace Santé », « Espace Maçonnerie »…) est une
 * VUE PARTAGÉE (super-admin.domain.dashboard) rendue à la fois pour le Super
 * Admin et pour l'Admin Centre. La simplification demandée est donc purement
 * conditionnée au rôle : aucune donnée, aucun modèle, aucune route, aucun
 * calcul financier n'est retiré — seul l'affichage change.
 *
 * Contrat vérifié ici pour `center_admin` :
 *  - exactement quatre cartes KPI, dans l'ordre : Étudiants, Certificats,
 *    Présences (jour), Revenus (payés) ;
 *  - plus de cartes Professeurs / Formations / Inscriptions / Classes (leurs
 *    pages restent accessibles) ;
 *  - grille responsive 4 / 2 / 1 colonne, design des cartes inchangé ;
 *  - plus de graphiques « Évolution des inscriptions » ni « Répartition des
 *    étudiants » (la bibliothèque Chart.js reste chargée, elle sert ailleurs) ;
 *  - plus de tableau « Dernières inscriptions » ;
 *  - en-tête (espace, bienvenue, centre, contexte actif, changement de
 *    domaine, date) et section « Prochains cours » conservés ;
 *  - valeurs réelles, scopées centre + domaine ;
 *  - appliqué aux quatre centres marocains et à tous les domaines ;
 *  - Super Admin et Professeur strictement inchangés ; FR/AR/RTL OK.
 */
class CenterAdminDomainDashboardSimplificationTest extends TestCase
{
    use RefreshDatabase;

    /** Les quatre cartes conservées, dans l'ordre d'affichage. */
    private const KEPT_CARDS_FR = ['Étudiants', 'Certificats', 'Présences (jour)', 'Revenus (payés)'];

    private const REMOVED_CARDS_FR = ['Professeurs', 'Formations', 'Classes', 'Inscriptions'];

    protected Center $tanger;
    protected Center $essaouira;
    protected User $adminTanger;
    protected User $adminEssaouira;
    protected User $superAdmin;
    protected Formation $formaSante;
    protected Formation $formaInfo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tanger = Center::factory()->create(['name' => 'Centre Tanger', 'code' => 'TGR', 'city' => 'Tanger']);
        $this->essaouira = Center::factory()->create(['name' => 'Centre Essaouira', 'code' => 'ESS', 'city' => 'Essaouira']);

        $this->adminTanger = User::factory()->centerAdmin($this->tanger->id)->create([
            'email' => 'ca.dash@test.ma',
            'name' => 'Samira El Fassi',
        ]);
        $this->adminEssaouira = User::factory()->centerAdmin($this->essaouira->id)->create(['email' => 'ca.ess@test.ma']);
        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa.dash@test.ma']);

        $this->formaSante = Formation::factory()->forCenter($this->tanger)->forDomain('sante')->create();
        $this->formaInfo = Formation::factory()->forCenter($this->tanger)->forDomain('informatique')->create();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Un étudiant réellement inscrit dans la formation donnée. */
    private function enrolledStudent(Center $center, Formation $formation, array $attrs = []): Student
    {
        $student = Student::factory()->forCenter($center)->create($attrs);

        Enrollment::factory()->create([
            'center_id' => $center->id,
            'student_id' => $student->id,
            'formation_id' => $formation->id,
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);

        return $student;
    }

    /**
     * Libellés des cartes KPI rendues, dans l'ordre.
     *
     * @return list<string>
     */
    private function kpiLabels(string $html): array
    {
        preg_match_all('/<p class="ec-kpi__label truncate">([^<]*)<\/p>/', $html, $matches);

        return $matches[1];
    }

    /**
     * Valeurs affichées sur les cartes KPI, dans l'ordre.
     *
     * @return list<string>
     */
    private function kpiValues(string $html): array
    {
        preg_match_all('/<p class="ec-kpi__value mt-2">([^<]*)<\/p>/', $html, $matches);

        return $matches[1];
    }

    /** Nombre de cartes KPI cliquables (<a>) rendues. */
    private function clickableCardCount(string $html): int
    {
        return substr_count($html, 'class="ec-kpi cursor-pointer');
    }

    private function dashboard(User $user, string $domain = 'sante'): string
    {
        $route = $user->isSuperAdmin() ? 'super-admin.domain.dashboard' : 'center-admin.domain.dashboard';

        return $this->actingAs($user)->get(route($route, $domain))->assertOk()->getContent();
    }

    /** URL telle qu'elle est rendue dans le HTML (& échappé en &amp;). */
    private function renderedUrl(string $routeName, array $params = []): string
    {
        return str_replace('&', '&amp;', route($routeName, $params));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §3-§4 — quatre cartes KPI, et seulement elles
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_dashboard_shows_exactly_the_four_kept_cards(): void
    {
        $html = $this->dashboard($this->adminTanger);

        $this->assertSame(self::KEPT_CARDS_FR, $this->kpiLabels($html),
            'Le dashboard Admin Centre doit afficher uniquement Étudiants, Certificats, Présences (jour), Revenus (payés), dans cet ordre.');

        $this->assertSame(4, $this->clickableCardCount($html));
    }

    public function test_the_four_removed_cards_are_no_longer_displayed(): void
    {
        $html = $this->dashboard($this->adminTanger);

        foreach (self::REMOVED_CARDS_FR as $label) {
            $this->assertNotContains($label, $this->kpiLabels($html),
                "La carte « {$label} » ne doit plus être une carte KPI du dashboard Admin Centre.");
        }

        // Aucune de leurs URL de cartes n'est rendue.
        foreach ([
            $this->renderedUrl('teachers.index', ['domain' => 'sante']),
            $this->renderedUrl('formations.index', ['domain' => 'sante']),
            $this->renderedUrl('classes.index', ['domain' => 'sante']),
            $this->renderedUrl('enrollments.index', ['domain' => 'sante']),
        ] as $url) {
            $this->assertStringNotContainsString($url, $html, "La carte pointant vers {$url} doit avoir disparu de l'affichage.");
        }

        // Mais les pages correspondantes restent consultables (aucune suppression).
        foreach (['teachers.index', 'formations.index', 'classes.index', 'enrollments.index'] as $routeName) {
            $this->actingAs($this->adminTanger)->get(route($routeName))->assertOk();
        }
    }

    public function test_kept_cards_keep_their_existing_icons_tones_and_links(): void
    {
        $html = $this->dashboard($this->adminTanger);

        // Mêmes tons de puces qu'avant (primary, success, info, warning).
        $this->assertSame(
            ['bg-brand-50', 'bg-emerald-50', 'bg-blue-50', 'bg-amber-50'],
            $this->chipTones($html),
            'Le design des quatre cartes conservées doit rester identique.'
        );

        // Mêmes destinations qu'avant (listes filtrées domaine + critère).
        $this->assertStringContainsString($this->renderedUrl('students.index', ['domain' => 'sante']), $html);
        $this->assertStringContainsString($this->renderedUrl('certificates.index', ['domain' => 'sante', 'status' => 'issued']), $html);
        $this->assertStringContainsString($this->renderedUrl('attendances.index', ['domain' => 'sante', 'date' => now()->toDateString()]), $html);
        $this->assertStringContainsString($this->renderedUrl('payments.index', ['domain' => 'sante', 'status' => 'paid']), $html);
    }

    /** Classes de puce (icône) des cartes KPI, dans l'ordre. */
    private function chipTones(string $html): array
    {
        preg_match_all('/<span class="ec-kpi__chip (bg-[a-z0-9-]+)/', $html, $matches);

        return $matches[1];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §5 — disposition des cartes
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cards_are_laid_out_in_a_responsive_four_column_grid(): void
    {
        $html = $this->dashboard($this->adminTanger);

        // Mobile 1 / tablette 2 / ordinateur 4 cartes sur la même ligne.
        $this->assertStringContainsString('<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">', $html);

        // Les quatre cartes sont les seuls enfants de cette grille, la section
        // suivante démarrant immédiatement : pas de case vide dans la ligne.
        $this->assertMatchesRegularExpression(
            '/<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">(.{0,12000}?)<\/div>\s*<div class="grid gap-6"/s',
            $html,
            'Les quatre cartes doivent être suivies immédiatement de la section « Prochains cours ».'
        );
        preg_match('/<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">(.{0,12000}?)<\/div>\s*<div class="grid gap-6"/s', $html, $grid);
        $this->assertSame(4, substr_count($grid[1], 'class="ec-kpi cursor-pointer'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §6 — suppression des deux graphiques
    // ──────────────────────────────────────────────────────────────────────────

    public function test_both_chart_sections_are_removed_for_center_admin(): void
    {
        $html = $this->dashboard($this->adminTanger);

        foreach (['Évolution des inscriptions', 'Répartition des étudiants'] as $title) {
            $this->assertStringNotContainsString($title, $html, "Le graphique « {$title} » doit avoir disparu.");
        }

        // Conteneurs, légendes et initialisation JavaScript retirés.
        $this->assertStringNotContainsString('enrollTrendChart', $html);
        $this->assertStringNotContainsString('studentsDistChart', $html);
        $this->assertStringNotContainsString('new Chart(', $html);

        // La bibliothèque n'est pas supprimée : elle reste chargée pour les
        // autres pages de l'application (layout global).
        $this->assertStringContainsString('chart.js', $html);
    }

    public function test_charts_are_still_rendered_for_super_admin(): void
    {
        $html = $this->dashboard($this->superAdmin);

        $this->assertStringContainsString('Évolution des inscriptions', $html);
        $this->assertStringContainsString('Répartition des étudiants', $html);
        $this->assertStringContainsString('enrollTrendChart', $html);
        $this->assertStringContainsString('studentsDistChart', $html);
        $this->assertStringContainsString('new Chart(', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §7 — suppression du tableau « Dernières inscriptions »
    // ──────────────────────────────────────────────────────────────────────────

    public function test_recent_enrollments_table_is_removed_for_center_admin(): void
    {
        $student = Student::factory()->forCenter($this->tanger)->create(['last_name' => 'Bennani']);
        $enrollment = Enrollment::factory()->create([
            'center_id' => $this->tanger->id,
            'student_id' => $student->id,
            'formation_id' => $this->formaSante->id,
            'status' => Enrollment::STATUS_ACCEPTED,
        ]);

        $html = $this->dashboard($this->adminTanger);

        // Titre + icône, bouton « Voir tout », tableau et conteneur.
        $this->assertStringNotContainsString('Dernières inscriptions', $html);
        // (« Voir tout » existe aussi ailleurs : le dropdown de notifications.)
        $this->assertSame(
            0,
            preg_match('/<a href="' . preg_quote(route('enrollments.index'), '/') . '[^>]*>\s*Voir tout/', $html),
            'Le bouton « Voir tout » du tableau des inscriptions doit avoir disparu.'
        );
        $this->assertStringNotContainsString('ec-table', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('Aucune inscription récente.', $html);

        // La donnée reste en base et consultable depuis les pages existantes.
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => Enrollment::STATUS_ACCEPTED]);
        $this->actingAs($this->adminTanger)->get(route('enrollments.index'))->assertOk()->assertSee('Bennani');
    }

    public function test_panel_section_keeps_a_single_balanced_column_for_center_admin(): void
    {
        $html = $this->dashboard($this->adminTanger);

        // Plus de grille à trois colonnes ni de panneau 2/3 : « Prochains cours »
        // occupe toute la largeur (pas d'espace vide).
        $this->assertStringNotContainsString('class="grid gap-6 xl:grid-cols-3"', $html);
        $this->assertStringNotContainsString('class="ec-panel xl:col-span-2"', $html);
        $this->assertStringContainsString('class="grid gap-6"', $html);

        // Le Super Admin conserve sa grille 2/3 + 1/3.
        $superHtml = $this->dashboard($this->superAdmin);
        $this->assertStringContainsString('class="grid gap-6 xl:grid-cols-3"', $superHtml);
        $this->assertStringContainsString('class="ec-panel xl:col-span-2"', $superHtml);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §8 — éléments conservés
    // ──────────────────────────────────────────────────────────────────────────

    public function test_header_and_upcoming_lessons_are_preserved(): void
    {
        $class = ClassRoom::factory()->forFormation($this->formaSante)->create(['name' => 'Groupe Santé T1']);
        $teacher = Teacher::factory()->forCenter($this->tanger)->forDomain('sante')->create();
        $room = Room::create(['center_id' => $this->tanger->id, 'name' => 'Salle A', 'code' => 'A1', 'capacity' => 20]);
        Schedule::create([
            'center_id' => $this->tanger->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id,
            'room_id' => $room->id, 'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00', 'end_time' => '11:00', 'title' => 'Séanceavenir',
        ]);

        $html = $this->dashboard($this->adminTanger);

        $this->assertStringContainsString('Espace Santé', $html);
        $this->assertStringContainsString('Domaine actif', $html);
        $this->assertStringContainsString('Bonjour', $html);
        $this->assertStringContainsString($this->adminTanger->name, $html);
        $this->assertStringContainsString('Centre Tanger', $html);
        $this->assertStringContainsString(now()->translatedFormat('d F Y'), $html);

        // Bouton « Changer de domaine » : retour au portail du centre.
        $this->assertStringContainsString('id="btn-retour-domaines"', $html);
        $this->assertStringContainsString(route('center-admin.domaines'), $html);
        $this->actingAs($this->adminTanger)->get(route('center-admin.domaines'))->assertOk();

        // « Prochains cours » conservé, avec sa donnée réelle.
        $this->assertStringContainsString('Prochains cours', $html);
        $this->assertStringContainsString('Groupe Santé T1', $html);

        // Header global et sidebar toujours rendus.
        $this->assertStringContainsString('<aside', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §4 / §10 — valeurs réelles, scopées, calculs financiers intacts
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cards_display_real_values_scoped_to_center_and_domain(): void
    {
        $tanger = $this->tanger;

        $s1 = $this->enrolledStudent($tanger, $this->formaSante, ['last_name' => 'Alami']);
        $s2 = $this->enrolledStudent($tanger, $this->formaSante, ['last_name' => 'Bennani']);
        $s3 = $this->enrolledStudent($tanger, $this->formaSante, ['last_name' => 'Cherkaoui']);

        $class = ClassRoom::factory()->forFormation($this->formaSante)->create();
        $teacher = Teacher::factory()->forCenter($tanger)->forDomain('sante')->create();
        $room = Room::create(['center_id' => $tanger->id, 'name' => 'Salle B', 'code' => 'B1', 'capacity' => 25]);

        // Quatre séances aujourd'hui + une hier (contrainte unique
        // student_id + schedule_id : une présence par séance et par étudiant).
        $todaySchedules = collect(range(1, 4))->map(fn (int $i) => Schedule::create([
            'center_id' => $tanger->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id,
            'room_id' => $room->id, 'date' => now()->toDateString(),
            'start_time' => sprintf('%02d:00', 8 + $i), 'end_time' => sprintf('%02d:00', 10 + $i),
            'title' => "Séance du jour {$i}",
        ]));
        $yesterday = Schedule::create([
            'center_id' => $tanger->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id,
            'room_id' => $room->id, 'date' => now()->subDay()->toDateString(),
            'start_time' => '09:00', 'end_time' => '11:00', 'title' => 'Séance de la veille',
        ]);

        // Présences : 4 aujourd'hui, 1 hier (exclue).
        foreach ($todaySchedules as $schedule) {
            Attendance::create([
                'center_id' => $tanger->id, 'student_id' => $s1->id, 'class_id' => $class->id,
                'schedule_id' => $schedule->id, 'date' => now()->toDateString(), 'status' => 'present',
            ]);
        }
        Attendance::create([
            'center_id' => $tanger->id, 'student_id' => $s1->id, 'class_id' => $class->id,
            'schedule_id' => $yesterday->id, 'date' => now()->subDay()->toDateString(), 'status' => 'present',
        ]);

        // Certificats : 2 délivrés, 1 brouillon (exclu).
        foreach (['issued' => 2, 'draft' => 1] as $status => $count) {
            foreach (range(1, $count) as $i) {
                Certificate::create([
                    'center_id' => $tanger->id, 'student_id' => $s1->id, 'formation_id' => $this->formaSante->id,
                    'certificate_number' => "CERT-TGR-{$status}-{$i}", 'type' => 'completion',
                    'title' => 'Certificat', 'issued_date' => now()->toDateString(), 'status' => $status,
                ]);
            }
        }

        // Revenus : 250 + 250 payés en Santé ; 500 annulé et 999 payés hors domaine exclus.
        foreach ([['paid', 250], ['paid', 250], ['cancelled', 500]] as $i => [$status, $amount]) {
            Payment::create([
                'center_id' => $tanger->id, 'student_id' => $s1->id, 'formation_id' => $this->formaSante->id,
                'amount' => $amount, 'currency' => 'MAD', 'payment_date' => now()->toDateString(),
                'method' => 'cash', 'reference' => "PAY-TGR-SANTE-{$i}", 'status' => $status,
            ]);
        }
        Payment::create([
            'center_id' => $tanger->id, 'student_id' => $s2->id, 'formation_id' => $this->formaInfo->id,
            'amount' => 999, 'currency' => 'MAD', 'payment_date' => now()->toDateString(),
            'method' => 'cash', 'reference' => 'PAY-TGR-INFO-1', 'status' => 'paid',
        ]);

        // Bruit hors centre : Essaouira / Santé.
        $foreignFormation = Formation::factory()->forCenter($this->essaouira)->forDomain('sante')->create();
        $this->enrolledStudent($this->essaouira, $foreignFormation, ['last_name' => 'HorsTanger']);

        $html = $this->dashboard($this->adminTanger);

        $this->assertSame(
            ['3', '2', '4', '500,00 DH'],
            $this->kpiValues($html),
            'Les quatre cartes doivent afficher les valeurs réelles du centre et du domaine sélectionnés.'
        );

        // Le payload du contrôleur reste complet : rien n'est supprimé côté
        // données (les cartes retirées sont seulement masquées).
        $stats = $this->actingAs($this->adminTanger)
            ->get(route('center-admin.domain.dashboard', 'sante'))
            ->viewData('stats');

        $this->assertArrayHasKey('teachers', $stats);
        $this->assertArrayHasKey('formations', $stats);
        $this->assertArrayHasKey('classes', $stats);
        $this->assertArrayHasKey('enrollments', $stats);
        $this->assertSame(1, $stats['teachers']);
        $this->assertSame(1, $stats['formations']);
        $this->assertSame(1, $stats['classes']);
        $this->assertSame(3, $stats['enrollments']);
        $this->assertSame(500.0, $stats['payments_total']);

        // Isolation multi-centres conservée : l'Admin Centre Essaouira ne voit
        // que les données de SON centre sur le même domaine sélectionné.
        $this->assertSame(
            ['1', '0', '0', '0,00 DH'],
            $this->kpiValues($this->dashboard($this->adminEssaouira)),
            'Chaque Admin Centre reste enfermé dans son centre.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §2 — les quatre centres, tous domaines (composants partagés)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_simplification_applies_to_the_four_moroccan_centers_and_all_domains(): void
    {
        $agadir = Center::factory()->create(['name' => 'Centre Agadir', 'code' => 'AGD', 'city' => 'Agadir', 'country' => 'Morocco']);
        $tetouane = Center::factory()->create(['name' => 'Centre Tétouane', 'code' => 'TET', 'city' => 'Tétouane', 'country' => 'Morocco']);

        $adminAgadir = User::factory()->centerAdmin($agadir->id)->create(['email' => 'ca.agd@test.ma']);
        $adminTetouane = User::factory()->centerAdmin($tetouane->id)->create(['email' => 'ca.tet@test.ma']);

        // Les quatre centres concernés par la mission.
        $admins = [
            'Centre Tanger' => $this->adminTanger,
            'Centre Agadir' => $adminAgadir,
            'Centre Essaouira' => $this->adminEssaouira,
            'Centre Tétouane' => $adminTetouane,
        ];

        foreach ($admins as $centerName => $admin) {
            foreach (['sante', 'informatique', 'maconnerie'] as $domain) {
                $html = $this->dashboard($admin, $domain);

                $this->assertSame(self::KEPT_CARDS_FR, $this->kpiLabels($html),
                    "Le dashboard de {$centerName} (domaine {$domain}) doit afficher les quatre mêmes cartes.");
                $this->assertStringNotContainsString('enrollTrendChart', $html);
                $this->assertStringNotContainsString('studentsDistChart', $html);
                $this->assertStringNotContainsString('Dernières inscriptions', $html);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §9 — aucun autre rôle touché
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_dashboard_is_untouched(): void
    {
        $html = $this->dashboard($this->superAdmin);

        $this->assertSame(
            ['Étudiants', 'Professeurs', 'Formations', 'Classes', 'Inscriptions', 'Certificats', 'Présences (jour)', 'Revenus (payés)'],
            $this->kpiLabels($html),
            'Les huit cartes du Super Admin doivent rester inchangées.'
        );
        $this->assertSame(8, $this->clickableCardCount($html));

        $this->assertStringContainsString('Dernières inscriptions', $html);
        $this->assertSame(
            1,
            preg_match('/<a href="' . preg_quote(route('enrollments.index'), '/') . '[^>]*>\s*Voir tout/', $html),
            'Le lien « Voir tout » du tableau des inscriptions est conservé chez le Super Admin.'
        );
        $this->assertStringContainsString('ec-table', $html);

        // Son filtre centre secondaire est conservé.
        $this->assertStringContainsString('Tous les centres', $html);
        $this->assertStringContainsString('name="center_id"', $html);
    }

    public function test_teacher_interface_is_untouched(): void
    {
        $response = $this->actingAs(User::factory()->teacher($this->tanger->id)->create(['email' => 'prof.dash@test.ma']))
            ->get(route('dashboard'))
            ->assertOk();

        $response->assertViewIs('dashboard.teacher');

        $html = $response->getContent();
        $this->assertStringContainsString('Espace professeur', $html);

        // La vue du professeur n'est pas la vue de domaine simplifiée.
        $this->assertStringNotContainsString('Domaine actif', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // §10 — FR / AR (RTL)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_simplified_dashboard_renders_in_arabic_with_rtl(): void
    {
        $this->assertSame(4, $this->clickableCardCount($this->dashboard($this->adminTanger)));

        $this->actingAs($this->adminTanger)->get(route('locale.switch', 'ar'))->assertRedirect();

        $html = $this->dashboard($this->adminTanger);

        $this->assertStringContainsString('dir="rtl"', $html);

        // Les quatre libellés arabes existants (aucune clé nouvelle créée).
        foreach (['الطلاب', 'الشهادات', 'الحضور (اليوم)', 'الإيرادات (المحصلة)'] as $label) {
            $this->assertContains($label, $this->kpiLabels($html), "Le libellé arabe « {$label} » doit être affiché.");
        }

        // Les cartes masquées n'apparaissent pas non plus en arabe.
        foreach (['الأساتذة', 'الأقسام'] as $label) {
            $this->assertNotContains($label, $this->kpiLabels($html));
        }

        // Affichage de la devise en arabe.
        $this->assertStringContainsString('د.م.', $html);

        $this->actingAs($this->adminTanger)->get(route('locale.switch', 'fr'));
    }

    public function test_super_admin_labels_are_still_complete_in_arabic(): void
    {
        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $html = $this->dashboard($this->superAdmin);

        $this->assertSame(8, count($this->kpiLabels($html)), 'Le Super Admin conserve ses huit cartes en arabe.');

        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'fr'));
    }
}
