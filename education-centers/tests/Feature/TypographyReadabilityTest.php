<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lisibilité globale — typographie agrandie sans refonte du design.
 *
 * L'amélioration est centralisée dans `resources/css/app.css` : Tailwind v4
 * compile `text-sm` en `font-size: var(--text-sm)`, donc redéfinir les tokens
 * sur la racine des coquilles internes (`.ec-admin` pour les dashboards,
 * `.ec-type` pour Communication / formulaires autonome / dossier élève)
 * agrandit toute l'interface des trois rôles sans toucher à la vitrine
 * publique ni à la page de connexion.
 *
 * Contrat vérifié ici :
 *  - une seule échelle, scopée, hiérarchisée (aucune taille uniformisée) ;
 *  - plus aucune micro-taille arbitraire dans les vues internes ;
 *  - les coquilles hors périmètre gardent leurs sizes d'origine ;
 *  - pages réelles des trois rôles et des quatre espaces (Formations,
 *    Stagiaires, Communication, Gestion financière) rendues sans texte minuscule ;
 *  - niveaux ciblés : tableaux, en-têtes, KPI, titres de cartes, sidebar,
 *    champs, labels, boutons ;
 *  - structure des menus, classes de design (couleurs, ombres, arrondis),
 *    responsive, FR/AR-RTL et clair/obscur inchangés.
 */
class TypographyReadabilityTest extends TestCase
{
    use RefreshDatabase;

    /** Tailles arbitraires bannies des interfaces internes (px et rem). */
    private const FORBIDDEN_IN_INTERNAL_VIEWS = [
        'text-[9px]',
        'text-[10px]',
        'text-[11px]',
        'text-[13px]',
        'text-[0.85rem]',
        'text-[0.95rem]',
        'text-[1rem]',
        'text-[1.0625rem]',
        'text-[1.15rem]',
        'text-[1.9rem]',
        'text-[2.15rem]',
        'text-[1.75rem]',
        'text-[2rem]',
    ];

    /** Vues hors périmètre : vitrine, connexion, PDF et page d'accueil Laravel. */
    private const OUT_OF_SCOPE = [
        'auth/',
        'public/',
        'pdf/',
        'welcome.blade.php',
        'layouts/public.blade.php',
        'layouts/guest.blade.php',
    ];

    private string $css;

    protected Center $tanger;

    protected Center $agadir;

    protected User $superAdmin;

    protected User $adminTanger;

    protected User $adminAgadir;

    protected User $teacher;

    protected Formation $santeTanger;

    protected Formation $infoAgadir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->css = file_get_contents(base_path('resources/css/app.css'));

        $this->tanger = Center::factory()->create(['name' => 'Centre Tanger', 'code' => 'TGR', 'city' => 'Tanger']);
        $this->agadir = Center::factory()->create(['name' => 'Centre Agadir', 'code' => 'AGD', 'city' => 'Agadir']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa-typo@test.ma']);
        $this->adminTanger = User::factory()->centerAdmin($this->tanger->id)->create(['email' => 'ca-typo@test.ma']);
        $this->adminAgadir = User::factory()->centerAdmin($this->agadir->id)->create(['email' => 'ca-agadir-typo@test.ma']);

        $this->santeTanger = Formation::factory()->forCenter($this->tanger)->forDomain('sante')->official('sante')
            ->create(['name' => 'Santé Tanger', 'annual_price' => 5000, 'price' => 500]);
        $this->infoAgadir = Formation::factory()->forCenter($this->agadir)->forDomain('informatique')->official('informatique')
            ->create(['name' => 'Informatique Agadir', 'annual_price' => 3000, 'price' => 300]);

        $this->teacher = User::factory()->teacher($this->tanger->id)->create(['email' => 'teacher-typo@test.ma']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Toutes les vues Blade internes (chemins relatifs à resources/views).
     *
     * @return list<string>
     */
    private function internalViewFiles(): array
    {
        $root = base_path('resources/views');
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            foreach (self::OUT_OF_SCOPE as $excluded) {
                if (str_starts_with($relative, $excluded) || str_contains('/'.$relative, '/'.$excluded)) {
                    continue 2;
                }
            }

            $found[] = $relative;
        }

        sort($found);

        return $found;
    }

    /** Micro-tailles encore présentes dans un HTML rendu. */
    private function smallSizesIn(string $html): array
    {
        return array_values(array_filter(
            self::FORBIDDEN_IN_INTERNAL_VIEWS,
            fn (string $class): bool => str_contains($html, $class)
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Une échelle centrale, scopée et hiérarchisée
    // ──────────────────────────────────────────────────────────────────────────

    public function test_the_scale_is_defined_once_and_scoped_to_the_internal_shells(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.ec-admin,\s*\.ec-type\s*\{[^}]*--text-sm:\s*0\.9375rem/',
            $this->css,
            'L\'échelle doit être définie une seule fois, scopée sur les coquilles internes.'
        );

        // Chaque niveau de la hiérarchie est présent, avec un écart conservé :
        // rien n'est uniformisé (14 px secondaires, 15 px tableaux/boutons,
        // 17 px texte et champs, 19–26 px titres, 32 px titres de pages).
        foreach (['--text-2xs: 0.8125rem', '--text-xs: 0.875rem', '--text-sm: 0.9375rem',
            '--text-base: 1.0625rem', '--text-lg: 1.1875rem', '--text-xl: 1.375rem',
            '--text-2xl: 1.625rem', '--text-3xl: 2rem'] as $token) {
            $this->assertStringContainsString($token, $this->css, "Niveau manquant dans l'échelle : {$token}.");
        }

        // Une seule source de vérité : les tokens ne sont redéfinis nulle part ailleurs.
        $this->assertSame(1, substr_count($this->css, '--text-sm:'), '--text-sm ne doit être déclaré qu’une fois.');
        $this->assertSame(1, substr_count($this->css, '--text-base:'), '--text-base ne doit être déclaré qu’une fois.');
    }

    public function test_the_scale_keeps_the_absolute_line_boxes_stable(): void
    {
        // Les hauteurs de ligne sont recalculées pour absorber l'agrandissement
        // sans faire déborder ni décaler les blocs (garde-fou anti-débordement).
        $this->assertMatchesRegularExpression('/--text-xs--line-height:\s*1\.43/', $this->css);
        $this->assertMatchesRegularExpression('/--text-sm--line-height:\s*1\.34/', $this->css);
        $this->assertMatchesRegularExpression('/--text-3xl--line-height:\s*1\.22/', $this->css);
    }

    public function test_every_internal_shell_carries_the_typography_scope(): void
    {
        $scoped = [
            'layouts/app.blade.php' => 'ec-admin',
            'layouts/portal.blade.php' => 'ec-admin',
            'layouts/communication.blade.php' => 'ec-type',
            'layouts/standalone.blade.php' => 'ec-type',
            'layouts/student-dossier.blade.php' => 'ec-type',
        ];

        foreach ($scoped as $view => $class) {
            $html = file_get_contents(base_path('resources/views/'.$view));

            $this->assertMatchesRegularExpression(
                '/<body[^>]+class="[^"]*\b'.$class.'\b/',
                $html,
                "La coquille interne {$view} doit porter l'échelle ({$class})."
            );
        }
    }

    public function test_public_pages_and_the_login_page_keep_their_original_sizes(): void
    {
        foreach (['layouts/public.blade.php', 'layouts/guest.blade.php'] as $view) {
            $html = file_get_contents(base_path('resources/views/'.$view));

            $this->assertStringNotContainsString('ec-admin', $html, "{$view} ne doit pas entrer dans le périmètre.");
            $this->assertStringNotContainsString('ec-type', $html, "{$view} ne doit pas entrer dans le périmètre.");
        }

        // Les micro-tailles hors périmètre sont restées telles quelles : la
        // vitrine et la connexion ne sont pas une refonte.
        $this->assertStringContainsString('text-[10px]', file_get_contents(base_path('resources/views/auth/login.blade.php')));
        $this->assertStringContainsString('text-[11px]', file_get_contents(base_path('resources/views/public/home.blade.php')));
    }

    public function test_no_micro_font_size_survives_in_the_internal_views(): void
    {
        $views = $this->internalViewFiles();

        $this->assertGreaterThan(100, count($views), 'Le balayage doit couvrir tout le back-office.');

        $offenders = [];

        foreach ($views as $relative) {
            $html = file_get_contents(base_path('resources/views/'.$relative));

            foreach ($this->smallSizesIn($html) as $found) {
                $offenders[] = $relative.' → '.$found;
            }
        }

        $this->assertSame([], $offenders, 'Des tailles arbitraires court-circuitent encore l\'échelle centrale.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Niveaux ciblés par l'objectif
    // ──────────────────────────────────────────────────────────────────────────

    public function test_page_titles_share_one_enlarged_heading(): void
    {
        // Le composant partagé et les dashboards utilisent le même titre de page
        // (28 px sur mobile, 32,8 px sur desktop : la fourchette 30–34 px).
        $this->assertStringContainsString('.ec-page-title', $this->css);
        $this->assertStringContainsString('font-size: 2.05rem', $this->css);

        $pageHeader = file_get_contents(base_path('resources/views/components/ui/page-header.blade.php'));
        $this->assertStringContainsString('ec-page-title', $pageHeader);

        foreach (['dashboard/teacher', 'dashboard/center_admin', 'dashboard/student', 'super-admin/domain/dashboard'] as $view) {
            $this->assertStringContainsString(
                'ec-page-title',
                file_get_contents(base_path("resources/views/{$view}.blade.php")),
                "Le titre de page du dashboard {$view} doit passer par l'échelle partagée."
            );
        }
    }

    public function test_tables_get_readable_rows_and_secondary_headers(): void
    {
        // Corps de tableaux 16 px, en-têtes 14 px, lignes légèrement plus hautes.
        $this->assertMatchesRegularExpression(
            '/\.ec-admin \.ec-table td[^{]*\{\s*font-size:\s*1rem/',
            $this->css
        );
        $this->assertMatchesRegularExpression(
            '/\.ec-admin \.ec-table th[^{]*\{\s*font-size:\s*0\.875rem/',
            $this->css
        );
        $this->assertStringContainsString('padding-top: 0.9rem', $this->css);
    }

    public function test_kpi_cards_and_panels_are_enlarged_without_changing_their_design(): void
    {
        $this->assertMatchesRegularExpression('/\.ec-kpi__value\s*\{\s*font-size:\s*2\.1rem/', $this->css);
        $this->assertMatchesRegularExpression('/\.ec-kpi__label\s*\{\s*font-size:\s*1rem/', $this->css);
        $this->assertMatchesRegularExpression('/\.ec-panel__title\s*\{\s*[^}]*font-size:\s*1\.25rem/', $this->css);

        // Couleurs, bordures, arrondis et ombres des cartes KPI : inchangés.
        $this->assertStringContainsString('border-radius: 1.15rem', $this->css);
        $this->assertStringContainsString('box-shadow: 0 1px 2px rgb(15 23 42 / 0.04)', $this->css);
    }

    public function test_sidebar_links_grow_without_touching_the_menu_structure(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.ec-admin \.ec-nav-link[^{]*\{\s*font-size:\s*1\.0625rem/',
            $this->css,
            'Les liens de navigation doivent atteindre 17 px.'
        );

        // Colonne élargie pour éviter retours à la ligne et libellés tronqués.
        $this->assertMatchesRegularExpression('/\.ec-sidebar\s*\{\s*width:\s*17\.5rem/', $this->css);

        $sidebar = file_get_contents(base_path('resources/views/layouts/partials/sidebar.blade.php'));
        $this->assertStringNotContainsString('text-[10px]', $sidebar);
        $this->assertStringNotContainsString('text-[11px]', $sidebar);
        $this->assertStringContainsString('ec-nav-link', $sidebar);
        $this->assertStringContainsString('$group[\'items\']', $sidebar);
    }

    public function test_card_and_panel_titles_reach_the_card_level_as_a_floor(): void
    {
        // Plancher à 19 px pour les titres restés en text-sm/text-base, déclaré
        // dans @layer components : un titre déjà dimensionné garde sa taille.
        $this->assertMatchesRegularExpression(
            '/\.ec-admin :is\(h2, h3\),\s*\.ec-type :is\(h2, h3\)\s*\{\s*font-size: var\(--text-lg\)/',
            $this->css
        );

        // Les grandes cartes de section conservent leurs propres classes.
        $this->assertStringContainsString(
            'text-2xl',
            file_get_contents(base_path('resources/views/components/students/form/card.blade.php'))
        );
    }

    public function test_form_fields_and_their_labels_are_enlarged_centralally(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.ec-admin \.ec-input,[^}]*\.ec-type \.ec-textarea\s*\{\s*@apply px-3\.5 text-base;/',
            $this->css,
            'Les champs internes doivent viser 17 px via l\'échelle.'
        );

        // Aucune règle de validation ni aucun champ n'est touché : les classes
        // partagées restent celles du formulaire de référence.
        $create = file_get_contents(base_path('resources/views/students/create.blade.php'));
        $edit = file_get_contents(base_path('resources/views/students/edit.blade.php'));
        $this->assertStringContainsString('students.form.shell', $create);
        $this->assertStringContainsString('students.form.shell', $edit);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Rendu réel : les pages représentatives des trois rôles
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rolePagesProvider(): array
    {
        return [
            'dashboard super admin (accueil)' => ['superAdmin', 'admin.home'],
            'dashboard d\'une formation (super admin)' => ['superAdmin', 'super-admin.domain.dashboard'],
            'dashboard d\'une formation (admin centre)' => ['adminTanger', 'center-admin.domain.dashboard'],
            'accueil admin centre' => ['adminTanger', 'admin.home'],
            'gestion des stagiaires' => ['superAdmin', 'stagiaires.index'],
            'gestion financière' => ['superAdmin', 'super-admin.finance.dashboard'],
            'tableau des étudiants' => ['superAdmin', 'students.index'],
            'formations (catalogue)' => ['superAdmin', 'formations-generales.index'],
            'communication (messagerie)' => ['superAdmin', 'messages.index'],
            'profil professeur (dashboard)' => ['teacher', 'dashboard'],
        ];
    }

    #[DataProvider('rolePagesProvider')]
    public function test_a_representative_page_renders_enlarged_and_without_small_text(
        string $actor,
        string $routeName,
    ): void {
        $html = $this->renderPage($actor, $routeName);

        // Toute page interne vit dans une coquille portant l'échelle : .ec-admin
        // (dashboards) ou .ec-type (Communication, formulaires autonomes, dossier
        // élève). Jamais une page hors périmètre.
        $this->assertMatchesRegularExpression(
            '/<body[^>]+class="[^"]*\bec-(admin|type)\b/',
            $html,
            "{$routeName} doit être rendu dans une coquille scalée."
        );
        $this->assertSame([], $this->smallSizesIn($html), "{$routeName} affiche encore des tailles hors échelle.");
        $this->assertStringContainsString('Inter', file_get_contents(base_path('resources/css/app.css')), 'La police Inter est conservée.');
    }

    /**
     * Pages « document » (titre de page) : la messagerie est une coquille
     * applicative plein écran sans en-tête de document, elle est testée à part.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function documentPagesProvider(): array
    {
        return array_diff_key(self::rolePagesProvider(), ['communication (messagerie)' => null]);
    }

    #[DataProvider('documentPagesProvider')]
    public function test_a_representative_page_keeps_its_headings_hierarchy(string $actor, string $routeName): void
    {
        $html = $this->renderPage($actor, $routeName);

        // Le titre de la page reste le plus grand élément de texte du corps.
        $this->assertMatchesRegularExpression(
            '/<(h1|h2)[^>]+class="[^"]*(ec-page-title|text-2xl|text-3xl|text-xl|text-lg)/',
            $html,
            "{$routeName} doit conserver un titre hiérarchisé (pas un texte plat)."
        );
    }

    private function renderPage(string $actor, string $routeName): string
    {
        $user = match ($actor) {
            'superAdmin' => $this->superAdmin,
            'adminTanger' => $this->adminTanger,
            'teacher' => $this->teacher,
        };

        $params = str_contains($routeName, 'domain.dashboard') ? ['domain' => 'sante'] : [];

        $response = $this->actingAs($user)->get(route($routeName, $params));

        $response->assertOk();

        return $response->getContent();
    }

    public function test_add_and_edit_student_forms_render_on_the_same_scale(): void
    {
        $class = ClassRoom::factory()->create(['center_id' => $this->tanger->id, 'formation_id' => $this->santeTanger->id]);
        $student = Student::factory()->create(['center_id' => $this->tanger->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'formation_id' => $this->santeTanger->id,
            'class_id' => $class->id,
            'center_id' => $this->tanger->id,
        ]);

        $create = $this->actingAs($this->adminTanger)->get(route('students.create'))->assertOk()->getContent();
        $edit = $this->actingAs($this->adminTanger)->get(route('students.edit', $student))->assertOk()->getContent();

        foreach (['Ajouter' => $create, 'Modifier' => $edit] as $label => $html) {
            $this->assertSame([], $this->smallSizesIn($html), "Le formulaire « {$label} » garde des tailles arbitraires.");
            $this->assertStringContainsString('ec-label', $html);
            $this->assertStringContainsString('ec-input', $html);
            $this->assertStringContainsString('ec-type', $html, "Le formulaire doit être dans une coquille scalée.");
        }

        // Même structure, donc mêmes classes typographiques partagées.
        $this->assertSame(
            substr_count($create, 'ec-input'),
            substr_count($edit, 'ec-input'),
            'Ajouter et Modifier doivent rester le même formulaire.'
        );
    }

    public function test_the_arabic_rtl_and_dark_modes_are_still_served_by_the_same_scale(): void
    {
        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'sante'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('ec-admin', $html);
        $this->assertSame([], $this->smallSizesIn($html), 'La version arabe doit suivre la même échelle.');
        $this->assertStringContainsString('dark:', $html, 'Le mode sombre reste câblé.');

        // L'échelle ne change aucune règle RTL : la couche de retournement est
        // intacte et l'arabe garde sa police dédiée.
        $this->assertMatchesRegularExpression('/html\[dir=\'rtl\'\]\s*\{\s*font-family:\s*\'Cairo\'/', $this->css);
    }

    public function test_responsive_behaviour_is_untouched(): void
    {
        // Aucun point de rupture n'est ajouté à l'échelle : seule la taille de
        // police change, la mise en page reste celle d'origine.
        $scaleBlock = preg_match('/\.ec-admin,\s*\.ec-type\s*\{([^}]*)\}/', $this->css, $m) ? $m[1] : '';

        $this->assertStringNotContainsString('grid', $scaleBlock);
        $this->assertStringNotContainsString('width', $scaleBlock);
        $this->assertStringNotContainsString('padding', $scaleBlock);

        // Les grilles responsives des formulaires sont toujours de la partie.
        $formGrid = file_get_contents(base_path('resources/views/components/ui/form-grid.blade.php'));
        $this->assertMatchesRegularExpression('/grid-cols-1.*lg:grid-cols-2/', $formGrid);
    }

    public function test_the_business_logic_and_routes_are_not_touched(): void
    {
        // Un agrandissement typographique ne doit déplacer aucune route : les
        // mêmes URL restent servies aux mêmes rôles.
        $this->actingAs($this->adminAgadir)->get(route('admin.home'))->assertOk();
        $this->actingAs($this->adminAgadir)->get(route('students.create'))->assertOk();
        $this->actingAs($this->adminAgadir)->get(route('super-admin.finance.dashboard'))->assertOk();

        // Un admin d'un autre centre reste refusé (permissions inchangées). Le
        // dossier d'un autre centre est hors du scope de requête : 403 ou 404,
        // jamais 200 — l'agrandissement n'a ouvert accès à rien.
        $studentTanger = Student::factory()->create(['center_id' => $this->tanger->id]);

        $response = $this->actingAs($this->adminAgadir)->get(route('students.edit', $studentTanger));

        $this->assertContains($response->status(), [403, 404]);
    }
}
