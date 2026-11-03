<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * Cartes statistiques cliquables du tableau de bord de domaine.
 *
 * Les KPI du dashboard (vue partagée : super-admin.domain.dashboard et
 * center-admin.domain.dashboard) sont des liens <a> vers les listes existantes,
 * filtrées centre + domaine côté serveur avec exactement les mêmes critères de
 * comptage que la carte. Aucun module n'est dupliqué : seules des routes
 * existantes sont réutilisées. Le lien de retour ramène au dashboard du domaine
 * courant, et l'Admin Centre reste enfermé dans son centre, même si l'URL est
 * manipulée.
 *
 * Depuis l'épurage du dashboard pédagogique : l'Admin Centre ne voit plus que
 * quatre cartes (Étudiants, Certificats, Présences du jour, Revenus payés) —
 * les cartes Professeurs / Formations / Classes / Inscriptions ne sont plus
 * affichées pour ce rôle, leurs pages et routes restant accessibles.
 */
class DomainDashboardClickableCardsTest extends TestCase
{
    use RefreshDatabase;

    protected Center $tanger;
    protected Center $essaouira;
    protected User $adminTanger;
    protected User $superAdmin;
    protected Formation $formaSante;
    protected Formation $formaInfo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tanger = Center::factory()->create(['name' => 'Centre Tanger', 'code' => 'TGR']);
        $this->essaouira = Center::factory()->create(['name' => 'Centre Essaouira', 'code' => 'ESS']);

        $this->adminTanger = User::factory()->centerAdmin($this->tanger->id)->create(['email' => 'admin.tanger@test.ma']);
        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa.cards@test.ma']);

        $this->formaSante = Formation::factory()->forCenter($this->tanger)->forDomain('sante')->create();
        $this->formaInfo = Formation::factory()->forCenter($this->tanger)->forDomain('informatique')->create();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function student(Center $center, Formation $formation, array $attrs = []): Student
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

    /** Nombre de pages = total paginé (20/page) pour vérifier les totaux. */
    private function dashboardStats(User $user, string $domain = 'sante', array $query = []): array
    {
        $response = $this->actingAs($user)->get(route('center-admin.domain.dashboard', $domain), $query);
        $response->assertOk();

        return $response->viewData('stats');
    }

    /** Total réellement appliqué à la requête paginée d'une page de liste. */
    private function listTotal(string $viewVar, $paginated): int
    {
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginated, "La vue doit exposer \${$viewVar} paginé.");

        return $paginated->total();
    }

    /**
     * Le domaine actif est un contexte de session (alimenté par la navigation
     * réelle : portail → dashboard → cartes). Une requête directe vers une
     * liste doit donc être précédée d'une visite du dashboard du domaine.
     */
    private function inDomain(User $user, string $routeName, string $domain = 'sante', array $params = [])
    {
        $dashboardRoute = $user->isCenterAdmin()
            ? 'center-admin.domain.dashboard'
            : 'super-admin.domain.dashboard';

        $this->actingAs($user)->get(route($dashboardRoute, $domain))->assertOk();

        return $this->actingAs($user)->get(route($routeName, $params + ['domain' => $domain]));
    }

    /** Route absolue telle qu'elle est rendue dans le HTML (& échappé en &amp;). */
    private function renderedUrl(string $routeName, array $params = []): string
    {
        return str_replace('&', '&amp;', route($routeName, $params));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Les cartes rendues sont des liens <a> vers les pages existantes
    //    (Admin Centre : 4 KPI après épurage — Super Admin : les 8 d'origine)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_dashboard_renders_only_four_clickable_cards(): void
    {
        $html = $this->actingAs($this->adminTanger)
            ->get(route('center-admin.domain.dashboard', 'sante'))
            ->assertOk()
            ->getContent();

        // Les quatre cartes conservées, inchangées (mêmes URL, mêmes critères).
        $kept = [
            $this->renderedUrl('students.index', ['domain' => 'sante']),
            $this->renderedUrl('certificates.index', ['domain' => 'sante', 'status' => 'issued']),
            $this->renderedUrl('attendances.index', ['domain' => 'sante', 'date' => now()->toDateString()]),
            $this->renderedUrl('payments.index', ['domain' => 'sante', 'status' => 'paid']),
        ];
        foreach ($kept as $url) {
            $this->assertStringContainsString($url, $html, "La carte doit pointer vers {$url}.");
        }

        // Les quatre cartes retirées de l'affichage (pages et routes intactes).
        $removed = [
            $this->renderedUrl('teachers.index', ['domain' => 'sante']),
            $this->renderedUrl('formations.index', ['domain' => 'sante']),
            $this->renderedUrl('classes.index', ['domain' => 'sante']),
            $this->renderedUrl('enrollments.index', ['domain' => 'sante']),
        ];
        foreach ($removed as $url) {
            $this->assertStringNotContainsString($url, $html, "La carte « {$url} » ne doit plus être affichée.");
        }

        // Exactement quatre cartes KPI cliquables.
        $this->assertMatchesRegularExpression('/<a[^>]+class="ec-kpi[^"]*cursor-pointer/', $html);
        $this->assertSame(4, substr_count($html, 'class="ec-kpi cursor-pointer'), 'Quatre cartes KPI pour l’Admin Centre.');

        // Les pages visées restent accessibles (aucune suppression de route).
        foreach (['teachers.index', 'formations.index', 'classes.index', 'enrollments.index'] as $routeName) {
            $this->actingAs($this->adminTanger)->get(route($routeName, ['domain' => 'sante']))->assertOk();
        }
    }

    public function test_super_admin_dashboard_still_renders_all_eight_cards(): void
    {
        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'sante'))
            ->assertOk()
            ->getContent();

        $expected = [
            $this->renderedUrl('students.index', ['domain' => 'sante']),
            $this->renderedUrl('teachers.index', ['domain' => 'sante']),
            $this->renderedUrl('formations.index', ['domain' => 'sante']),
            $this->renderedUrl('classes.index', ['domain' => 'sante']),
            $this->renderedUrl('enrollments.index', ['domain' => 'sante']),
            $this->renderedUrl('certificates.index', ['domain' => 'sante', 'status' => 'issued']),
            $this->renderedUrl('attendances.index', ['domain' => 'sante', 'date' => now()->toDateString()]),
            $this->renderedUrl('payments.index', ['domain' => 'sante', 'status' => 'paid']),
        ];

        foreach ($expected as $url) {
            $this->assertStringContainsString($url, $html, "La carte doit pointer vers {$url}.");
        }

        // Rendues en véritables <a class="ec-kpi ..."> (toute la surface est
        // cliquable, navigation clavier possible), pas en <div>.
        $this->assertMatchesRegularExpression('/<a[^>]+class="ec-kpi[^"]*cursor-pointer/', $html);
        $this->assertSame(8, substr_count($html, 'class="ec-kpi cursor-pointer'), 'Les huit cartes KPI du Super Admin sont inchangées.');

        // Graphiques et tableau « Dernières inscriptions » conservés chez lui.
        $this->assertStringContainsString('enrollTrendChart', $html);
        $this->assertStringContainsString('studentsDistChart', $html);
        $this->assertStringContainsString('Dernières inscriptions', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Cohérence : chaque liste destination applique les mêmes critères
    //    centre + domaine que la carte (comptage identique)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_each_destination_list_matches_its_card_count(): void
    {
        $tanger = $this->tanger;
        $sante = $this->formaSante;

        // Périmètre Santé / Tanger : 2 étudiants, 1 professeur, 1 formation,
        // 1 classe, 2 inscriptions, 2 certificats délivrés, 2 présences du jour,
        // 2 paiements payés (1 brouillon + 1 annulé + 1 hors domaine exclus).
        $s1 = $this->student($tanger, $sante, ['last_name' => 'Alami']);
        $s2 = $this->student($tanger, $sante, ['last_name' => 'Bennani']);
        $teacher = Teacher::factory()->forCenter($tanger)->forDomain('sante')->create();
        $class = ClassRoom::factory()->forFormation($sante)->create(['name' => 'Groupe Santé T1']);
        $room = \App\Models\Room::create(['center_id' => $tanger->id, 'name' => 'Salle A', 'code' => 'A1', 'capacity' => 20]);
        $scheduleToday = \App\Models\Schedule::create([
            'center_id' => $tanger->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id,
            'room_id' => $room->id, 'date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '11:00', 'title' => 'Séance 1',
        ]);
        $scheduleToday2 = \App\Models\Schedule::create([
            'center_id' => $tanger->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id,
            'room_id' => $room->id, 'date' => now()->toDateString(),
            'start_time' => '14:00', 'end_time' => '16:00', 'title' => 'Séance 2',
        ]);
        $schedulePast = \App\Models\Schedule::create([
            'center_id' => $tanger->id, 'class_id' => $class->id, 'teacher_id' => $teacher->id,
            'room_id' => $room->id, 'date' => now()->subDay()->toDateString(),
            'start_time' => '09:00', 'end_time' => '11:00', 'title' => 'Séance veille',
        ]);
        Attendance::create([
            'center_id' => $tanger->id, 'student_id' => $s1->id, 'class_id' => $class->id,
            'schedule_id' => $scheduleToday->id, 'date' => now()->toDateString(), 'status' => 'present',
        ]);
        Attendance::create([
            'center_id' => $tanger->id, 'student_id' => $s2->id, 'class_id' => $class->id,
            'schedule_id' => $scheduleToday2->id, 'date' => now()->toDateString(), 'status' => 'absent',
        ]);
        Attendance::create([
            'center_id' => $tanger->id, 'student_id' => $s1->id, 'class_id' => $class->id,
            'schedule_id' => $schedulePast->id, 'date' => now()->subDay()->toDateString(), 'status' => 'present',
        ]);
        foreach (['issued' => 2, 'draft' => 1] as $status => $count) {
            foreach (range(1, $count) as $i) {
                Certificate::create([
                    'center_id' => $tanger->id,
                    'student_id' => $s1->id,
                    'formation_id' => $sante->id,
                    'certificate_number' => "CERT-TGR-{$status}-{$i}",
                    'type' => 'completion',
                    'title' => 'Certificat test',
                    'issued_date' => now()->toDateString(),
                    'status' => $status,
                ]);
            }
        }
        foreach ([['paid', 300], ['paid', 200], ['cancelled', 500]] as $i => [$status, $amount]) {
            Payment::create([
                'center_id' => $tanger->id,
                'student_id' => $s1->id,
                'formation_id' => $sante->id,
                'amount' => $amount,
                'currency' => 'MAD',
                'payment_date' => now()->toDateString(),
                'method' => 'cash',
                'reference' => 'PAY-TGR-TEST-' . $i,
                'status' => $status,
            ]);
        }
        // Bruit hors périmètre : Santé/Essaouira et Informatique/Tanger.
        $this->student($this->essaouira, Formation::factory()->forCenter($this->essaouira)->forDomain('sante')->create(), ['last_name' => 'HorsTanger']);
        $this->student($tanger, $this->formaInfo, ['last_name' => 'HorsDomaine']);
        Payment::create([
            'center_id' => $tanger->id, 'student_id' => $s2->id, 'formation_id' => $this->formaInfo->id,
            'amount' => 999, 'currency' => 'MAD', 'payment_date' => now()->toDateString(),
            'method' => 'cash', 'reference' => 'PAY-TGR-INFO-1', 'status' => 'paid',
        ]);

        // 1) Dashboard : valeurs des cartes — périmètre strictement Santé/Tanger.
        $stats = $this->dashboardStats($this->adminTanger);
        $this->assertSame(2, $stats['students']);
        $this->assertSame(1, $stats['teachers']);
        $this->assertSame(1, $stats['formations']);
        $this->assertSame(1, $stats['classes']);
        $this->assertSame(2, $stats['enrollments']);
        $this->assertSame(2, $stats['certificates_issued']);
        $this->assertSame(2, $stats['attendances_today']);
        $this->assertSame(500.0, $stats['payments_total']);

        // 2) Listes destinations : mêmes totaux exactement.
        $checks = [
            'students' => ['route' => 'students.index', 'view' => 'students', 'expected' => 2],
            'teachers' => ['route' => 'teachers.index', 'view' => 'teachers', 'expected' => 1],
            'formations' => ['route' => 'formations.index', 'view' => 'formations', 'expected' => 1],
            'classes' => ['route' => 'classes.index', 'view' => 'classes', 'expected' => 1],
            'enrollments' => ['route' => 'enrollments.index', 'view' => 'enrollments', 'expected' => 2],
            'certificates' => ['route' => 'certificates.index', 'view' => 'certificates', 'expected' => 2],
            'attendances' => ['route' => 'attendances.index', 'view' => 'attendances', 'expected' => 2],
            'payments' => ['route' => 'payments.index', 'view' => 'payments', 'expected' => 2],
        ];

        foreach ($checks as $card => $check) {
            $params = ['domain' => 'sante'];
            if ($card === 'certificates') {
                $params['status'] = 'issued';
            } elseif (in_array($card, ['attendances'], true)) {
                $params['date'] = now()->toDateString();
            } elseif ($card === 'payments') {
                $params['status'] = 'paid';
            }

            $response = $this->actingAs($this->adminTanger)
                ->get(route($check['route'], $params))
                ->assertOk();

            $this->assertSame(
                $check['expected'],
                $this->listTotal($check['view'], $response->viewData($check['view'])),
                "La liste « {$card} » doit compter comme sa carte."
            );
        }

        // 3) Le total financier affiché sur la page destination reflète les
        // paiements réellement encaissés du domaine (300 + 200, pas le 999
        // hors domaine ni le 500 annulé).
        $paymentsPage = $this->actingAs($this->adminTanger)
            ->get(route('payments.index', ['domain' => 'sante', 'status' => 'paid']))
            ->assertOk();
        $this->assertSame(500.0, (float) $paymentsPage->viewData('totalPaid'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Professeurs : consultation seule pour l'Admin Centre
    // ──────────────────────────────────────────────────────────────────────────

    public function test_teachers_destination_is_read_only_for_center_admin(): void
    {
        Teacher::factory()->forCenter($this->tanger)->forDomain('sante')->create();

        $html = $this->actingAs($this->adminTanger)
            ->get(route('teachers.index', ['domain' => 'sante']))
            ->assertOk()
            ->getContent();

        // Aucun bouton/lien de création de dossier professeur.
        $this->assertStringNotContainsString(route('teachers.create', absolute: false), $html);
        $this->assertStringNotContainsString('btn-create-teacher', $html);

        // Accès directs aux actions de création : refusés (policy inchangée).
        $this->actingAs($this->adminTanger)->get(route('teachers.create'))->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Étudiants : la liste reste accessible malgré le retrait du lien sidebar
    // ──────────────────────────────────────────────────────────────────────────

    public function test_students_list_accessible_from_card_though_sidebar_link_removed(): void
    {
        $this->student($this->tanger, $this->formaSante, ['last_name' => 'Karimi']);

        $this->actingAs($this->adminTanger)
            ->get(route('students.index', ['domain' => 'sante']))
            ->assertOk()
            ->assertSee('Karimi');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Manipulation d'URL : un Admin Centre reste dans son centre
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_cannot_escape_own_center_via_url(): void
    {
        $this->student($this->tanger, $this->formaSante, ['last_name' => 'TangerineOnly']);
        $foreign = $this->student($this->essaouira, Formation::factory()->forCenter($this->essaouira)->forDomain('sante')->create(), ['last_name' => 'EssaouiraForeign']);

        foreach (['students.index', 'formations.index'] as $routeName) {
            // ?center_id = Essaouira : ignoré côté serveur pour un Admin Centre.
            $this->actingAs($this->adminTanger)
                ->get(route($routeName, ['domain' => 'sante', 'center_id' => $this->essaouira->id]))
                ->assertOk()
                ->assertDontSee('EssaouiraForeign');
        }

        // Même tentative sur le dashboard du domaine : les stats restent au centre.
        $stats = $this->dashboardStats($this->adminTanger, 'sante', ['center_id' => $this->essaouira->id]);
        $this->assertSame(1, $stats['students']);

        // Tente de détailler un étudiant d'un autre centre.
        $this->actingAs($this->adminTanger)->get(route('students.show', $foreign))->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Retour au dashboard du domaine précédent (périmètre conservé)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_every_destination_offers_back_link_to_domain_dashboard(): void
    {
        // Entre dans le contexte domaine Santé comme via les cartes.
        $this->dashboardStats($this->adminTanger);

        $routes = [
            'students.index', 'teachers.index', 'formations.index', 'classes.index',
            'enrollments.index', 'certificates.index', 'attendances.index', 'payments.index',
        ];

        foreach ($routes as $routeName) {
            $html = $this->actingAs($this->adminTanger)
                ->get(route($routeName, ['domain' => 'sante']))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString(
                route('center-admin.domain.dashboard', 'sante'),
                $html,
                "« {$routeName} » doit proposer le retour au dashboard du domaine Santé."
            );
        }

        // Hors contexte domaine (portail Admin Centre) : aucun lien de retour.
        $this->actingAs($this->adminTanger)->get(route('center-admin.domaines'))->assertOk();
        $noDomain = $this->actingAs($this->adminTanger)->get(route('students.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('link-retour-dashboard-domaine', $noDomain);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. Super Admin : fonctionnement conservé (cartes cliquables, tous centres)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_cards_work_and_scope_follows_center_filter(): void
    {
        $this->student($this->tanger, $this->formaSante, ['last_name' => 'SanteTanger']);
        $this->student($this->essaouira, Formation::factory()->forCenter($this->essaouira)->forDomain('sante')->create(), ['last_name' => 'SanteEssaouira']);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'sante'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('students.index', ['domain' => 'sante']), $html);

        // Sans filtre centre : tous les centres (2 étudiants Santé).
        $all = $this->actingAs($this->superAdmin)->get(route('students.index', ['domain' => 'sante']))->assertOk();
        $this->assertSame(2, $this->listTotal('students', $all->viewData('students')));

        // Avec filtre centre ?center_id : restreint (comportement existant).
        $scoped = $this->actingAs($this->superAdmin)
            ->get(route('students.index', ['domain' => 'sante', 'center_id' => $this->tanger->id]))
            ->assertOk()
            ->assertSee('SanteTanger')
            ->assertDontSee('SanteEssaouira');
        $this->assertSame(1, $this->listTotal('students', $scoped->viewData('students')));

        // Le retour du Super Admin pointe vers SON dashboard de domaine.
        $this->assertStringContainsString(
            route('super-admin.domain.dashboard', 'sante'),
            $scoped->getContent()
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. FR / AR (RTL) : les destinations et retours restent corrects
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cards_and_back_link_render_in_arabic_rtl(): void
    {
        $this->actingAs($this->adminTanger)->get(route('locale.switch', 'ar'))->assertRedirect();

        $dash = $this->actingAs($this->adminTanger)
            ->get(route('center-admin.domain.dashboard', 'sante'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('dir="rtl"', $dash);
        // Les huit cartes restent des liens cliquables en RTL.
        $this->assertMatchesRegularExpression('/<a[^>]+class="ec-kpi/', $dash);

        $list = $this->actingAs($this->adminTanger)
            ->get(route('students.index', ['domain' => 'sante']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('dir="rtl"', $list);
        $this->assertStringContainsString('link-retour-dashboard-domaine', $list);

        $this->actingAs($this->adminTanger)->get(route('locale.switch', 'fr'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. Aucune régression : les cartes sans href (autres pages) restent <div>
    // ──────────────────────────────────────────────────────────────────────────

    public function test_stat_without_href_still_renders_as_div(): void
    {
        $html = $this->actingAs($this->adminTanger)
            ->get(route('payments.index'))
            ->assertOk()
            ->getContent();

        // Les cartes stat de la page Paiements (sans href) ne sont pas des <a>.
        $this->assertMatchesRegularExpression('/<div[^>]+class="[^"]*ec-kpi/', $html);
        $this->assertStringNotContainsString('<a href="" class="ec-kpi', $html);
    }
}
