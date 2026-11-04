@php
    $user = auth()->user();
    $collapsed = false;
    $isStagiaires = request()->routeIs('stagiaires.*');
    $isFinance = request()->routeIs('super-admin.finance.*');

    // ─── Détection du contexte domaine de formation ───────────────────────────
    // On utilise la session active_domain pour identifier le contexte courant.
    // Si aucun domaine n'est en session mais que la route correspond à un domaine,
    // on le récupère depuis l'URL (robustesse en cas de navigation directe).
    $activeDomain = session('active_domain');
    if (!$activeDomain && request()->routeIs('super-admin.domain.dashboard')) {
        $activeDomain = request()->route('domain');
    }
    if (!$activeDomain && request()->routeIs('super-admin.sante.dashboard')) {
        $activeDomain = 'sante';
    }
    $isFormationDomain = $user->isSuperAdmin() && !$isStagiaires && !$isFinance && !empty($activeDomain);

    // Configuration visuelle par domaine
    $domainMeta = [
        'sante'            => ['label' => __('Santé'),            'color' => 'emerald', 'icon' => 'heart'],
        'informatique'     => ['label' => __('Informatique'),     'color' => 'blue',    'icon' => 'monitor'],
        'hotellerie'       => ['label' => __('Hôtellerie'),       'color' => 'amber',   'icon' => 'building'],
        'maconnerie'       => ['label' => __('Maçonnerie'),       'color' => 'orange',  'icon' => 'wrench'],
        'design-interieur' => ['label' => __('Design intérieur'), 'color' => 'violet',  'icon' => 'home'],
        'design-graphique' => ['label' => __('Design graphique'), 'color' => 'pink',    'icon' => 'brush'],
    ];
    $currentDomainMeta = $domainMeta[$activeDomain] ?? $domainMeta['sante'];
    $domainLabel       = $currentDomainMeta['label'];
    $domainColor       = $currentDomainMeta['color'];
    $domainIcon        = $currentDomainMeta['icon'];

    // Palettes de couleur par domaine
    $colorPalette = [
        'emerald' => [
            'banner_bg'    => 'bg-emerald-50/80 dark:bg-emerald-950/30',
            'icon_bg'      => 'bg-emerald-600',
            'label_text'   => 'text-emerald-800 dark:text-emerald-300',
            'btn_border'   => 'border-emerald-300/80 dark:border-emerald-700/80',
            'btn_text'     => 'text-emerald-700 dark:text-emerald-300',
            'btn_hover'    => 'hover:bg-emerald-100 dark:hover:bg-emerald-900/40',
            'icon_hover'   => 'hover:bg-emerald-700',
        ],
        'blue' => [
            'banner_bg'    => 'bg-blue-50/80 dark:bg-blue-950/30',
            'icon_bg'      => 'bg-blue-600',
            'label_text'   => 'text-blue-800 dark:text-blue-300',
            'btn_border'   => 'border-blue-300/80 dark:border-blue-700/80',
            'btn_text'     => 'text-blue-700 dark:text-blue-300',
            'btn_hover'    => 'hover:bg-blue-100 dark:hover:bg-blue-900/40',
            'icon_hover'   => 'hover:bg-blue-700',
        ],
        'amber' => [
            'banner_bg'    => 'bg-amber-50/80 dark:bg-amber-950/30',
            'icon_bg'      => 'bg-amber-600',
            'label_text'   => 'text-amber-800 dark:text-amber-300',
            'btn_border'   => 'border-amber-300/80 dark:border-amber-700/80',
            'btn_text'     => 'text-amber-700 dark:text-amber-300',
            'btn_hover'    => 'hover:bg-amber-100 dark:hover:bg-amber-900/40',
            'icon_hover'   => 'hover:bg-amber-700',
        ],
        'orange' => [
            'banner_bg'    => 'bg-orange-50/80 dark:bg-orange-950/30',
            'icon_bg'      => 'bg-orange-600',
            'label_text'   => 'text-orange-800 dark:text-orange-300',
            'btn_border'   => 'border-orange-300/80 dark:border-orange-700/80',
            'btn_text'     => 'text-orange-700 dark:text-orange-300',
            'btn_hover'    => 'hover:bg-orange-100 dark:hover:bg-orange-900/40',
            'icon_hover'   => 'hover:bg-orange-700',
        ],
        'violet' => [
            'banner_bg'    => 'bg-violet-50/80 dark:bg-violet-950/30',
            'icon_bg'      => 'bg-violet-600',
            'label_text'   => 'text-violet-800 dark:text-violet-300',
            'btn_border'   => 'border-violet-300/80 dark:border-violet-700/80',
            'btn_text'     => 'text-violet-700 dark:text-violet-300',
            'btn_hover'    => 'hover:bg-violet-100 dark:hover:bg-violet-900/40',
            'icon_hover'   => 'hover:bg-violet-700',
        ],
        'pink' => [
            'banner_bg'    => 'bg-pink-50/80 dark:bg-pink-950/30',
            'icon_bg'      => 'bg-pink-600',
            'label_text'   => 'text-pink-800 dark:text-pink-300',
            'btn_border'   => 'border-pink-300/80 dark:border-pink-700/80',
            'btn_text'     => 'text-pink-700 dark:text-pink-300',
            'btn_hover'    => 'hover:bg-pink-100 dark:hover:bg-pink-900/40',
            'icon_hover'   => 'hover:bg-pink-700',
        ],
    ];
    $pal = $colorPalette[$domainColor] ?? $colorPalette['emerald'];

    // ─── Construction des liens de navigation ─────────────────────────────────
    $link = function (string $label, string $routeName, string $icon, string|array $pattern, ?string $fallbackUrl = null, array $params = []) {
        $url = \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName, $params) : $fallbackUrl;
        if (!$url) return null;
        $active = request()->routeIs($pattern);
        return compact('label', 'url', 'icon', 'active');
    };

    $groups = [];

    if (($user->isSuperAdmin() || $user->isCenterAdmin()) && $isFinance) {
        // ────────────────────────────────────────────────────────────
        // CONTEXTE DÉDIÉ : GESTION FINANCIÈRE (CAD SYNDIC)
        // Super Admin : vue globale (tous les centres).
        // Admin Centre : même interface, périmètre forcé sur SON centre
        // (imposé côté serveur, pas seulement côté affichage).
        // ────────────────────────────────────────────────────────────
        // Navigation simplifiée : Trésorerie & Comptabilité (Compte bancaire,
        // Comptabilité), Rapports financiers et la rubrique autonome « Salaires
        // des professeurs » ont été retirés. Les salaires sont désormais gérés
        // comme une catégorie de charge directement depuis « Dépenses ».
        $groups[] = [
            'title' => __('Navigation'),
            'items' => array_filter([
                $link(__('Tableau de bord'), 'super-admin.finance.dashboard', 'chart', 'super-admin.finance.dashboard'),
                $link(__('Statistiques'), 'super-admin.finance.statistics', 'arrow-trending-up', 'super-admin.finance.statistics'),
            ])
        ];

        $groups[] = [
            'title' => __('Gestion financière'),
            'items' => array_filter([
                $link(__('Paiements'), 'super-admin.finance.students', 'academic', 'super-admin.finance.students'),
                $link(__('Dépenses'), 'super-admin.finance.expenses', 'banknotes', ['super-admin.finance.expenses', 'super-admin.finance.teachers']),
                $link(__('Recouvrement'), 'super-admin.finance.recovery', 'clock', 'super-admin.finance.recovery'),
            ])
        ];

        $groups[] = [
            'title' => __('Configuration'),
            'items' => array_filter([
                $link(__('Configuration des prix'), 'super-admin.finance.configuration', 'wrench', 'super-admin.finance.configuration'),
            ])
        ];

    } elseif (($user->isSuperAdmin() || $user->isCenterAdmin()) && $isStagiaires) {
        // ────────────────────────────────────────────────────────────
        // CONTEXTE DÉDIÉ : GESTION ADMINISTRATIVE DES STAGIAIRES
        // (Super Admin : tous centres autorisés — Admin Centre : son centre)
        // ────────────────────────────────────────────────────────────
        // Espace simplifié : la liste « Tous les stagiaires » est la page principale
        // du module ; le tableau de bord statistique et le groupe Dossiers ont été retirés.
        $groups[] = [
            'title' => __('Gestion des stagiaires'),
            'items' => array_filter([
                $link(__('Tous les stagiaires'), 'stagiaires.index', 'users', 'stagiaires.index'),
                $link(__('Ajouter un stagiaire'), 'stagiaires.create', 'plus', 'stagiaires.create'),
            ])
        ];

    } elseif ($user->isSuperAdmin()) {
        // ────────────────────────────────────────────────────────────
        // CONTEXTE : DOMAINE DE FORMATION (Santé, Informatique, etc.)
        // Le dashboard du domaine actif est l'entrée principale.
        // ────────────────────────────────────────────────────────────

        // Lien Dashboard dynamique selon le domaine actif
        $dashboardRoute = ($activeDomain === 'sante' || !$activeDomain)
            ? route('super-admin.sante.dashboard')
            : route('super-admin.domain.dashboard', $activeDomain ?? 'sante');

        $groups[] = ['title' => null, 'items' => array_filter([
            $link(__('Accueil'), 'admin.home', 'home', 'admin.home'),
            ['label' => __('Dashboard ') . $domainLabel, 'url' => $dashboardRoute, 'icon' => $domainIcon,
             'active' => request()->routeIs(['super-admin.sante.dashboard', 'super-admin.domain.dashboard'])],
        ])];

        $adminItems = array_filter([
            $link(__('Centres'), 'centers.index', 'building', 'centers.*'),
            $link(__('Formations'), 'formations.index', 'book', 'formations.*'),
            $link(__('Classes'), 'classes.index', 'classes', 'classes.*'),
            $link(__('Salles'), 'rooms.index', 'door', 'rooms.*'),
            $link(__('Emploi du temps'), 'schedules.index', 'calendar', 'schedules.*'),
            $link(__('Inscriptions'), 'enrollments.index', 'clipboard', 'enrollments.*'),
        ]);
        $groups[] = ['title' => __('Pédagogie'), 'items' => $adminItems];

        $ops = array_filter([
            $link(__('Présences'), 'attendances.index', 'clock', 'attendances.*'),
            $link(__('Évaluations'), 'evaluations.index', 'chart', 'evaluations.*'),
            $link(__('Documents'), 'documents.index', 'document', 'documents.*'),
            $link(__('Certificats'), 'certificates.index', 'certificate', 'certificates.*'),
        ]);
        $groups[] = ['title' => __('Gestion'), 'items' => $ops];

        $com = array_filter([
            $link(__('Notifications'), 'notifications.index', 'bell', 'notifications.*'),
            $link(__('Annonces'), 'announcements.index', 'megaphone', 'announcements.*'),
        ]);
        $groups[] = ['title' => __('Communication'), 'items' => $com];

    } elseif ($user->isCenterAdmin()) {
        // ────────────────────────────────────────────────────────────
        // SIDEBAR ADMIN CENTRE : navigation PLATE (pas de sections,
        // pas de titres de catégories ni de chevrons). Exactement six
        // liens, dans l'ordre. Les pages/routes/contrôleurs retirés de la
        // navigation restent entièrement accessibles par ailleurs (cartes
        // de l'Accueil, dashboards domaine, bouton « Toutes les formations »
        // de la bannière — la page Domaines est conservée).
        //
        // Le lien « Dashboard » ouvre le tableau de bord PÉDAGOGIQUE de la
        // formation active (exactement le domaine affiché par la bannière),
        // et surtout pas la page d'accueil générale admin.home : c'est le
        // petit bouton « Accueil » de la bannière qui ramène à /admin/accueil.
        // Route existante des dashboards de domaine — le centre est forcé côté
        // serveur par CenterAdminDomainController, jamais pris dans l'URL.
        // Sans formation active en session, on retombe sur le domaine par
        // défaut comme le fait déjà le Super Admin (jamais sur admin.home).
        // ────────────────────────────────────────────────────────────
        $groups[] = ['title' => null, 'items' => array_filter([
            $link(__('Dashboard'), 'center-admin.domain.dashboard', 'home', 'center-admin.domain.dashboard', null, [$activeDomain ?: 'sante']),
            $link(__('Emploi du temps'), 'schedules.index', 'calendar', 'schedules.*'),
            $link(__('Présences'), 'attendances.index', 'clock', 'attendances.*'),
            $link(__('Évaluations'), 'evaluations.index', 'chart', 'evaluations.*'),
            $link(__('Certificats'), 'certificates.index', 'certificate', 'certificates.*'),
            $link(__('Notifications'), 'notifications.index', 'bell', 'notifications.*'),
        ])];
        // NB : le contexte dédié « Gestion financière » (bloc $isFinance
        // ci-dessus) et le banner « formation active » en haut de sidebar
        // sont inchangés — seuls les liens de navigation simplifiés ici.

    } elseif ($user->isTeacher()) {
        $groups[] = ['title' => __('Mon espace'), 'items' => array_filter([
            $link(__('Tableau de bord'), 'dashboard', 'home', 'dashboard'),
            $link(__('Messagerie'), 'teacher.messages', 'message', 'teacher.messages*'),
            $link(__('Mes classes'), 'teacher.classes', 'classes', ['teacher.classes', 'teacher.classes.show']),
            $link(__('Mon emploi du temps'), 'teacher.schedule', 'calendar', 'teacher.schedule'),
            $link(__('Mes salaires'), 'teacher.salaries', 'banknotes', 'teacher.salaries'),
            $link(__('Présences'), 'attendances.index', 'clock', 'attendances.*'),
            $link(__('Évaluations & notes'), 'evaluations.index', 'chart', 'evaluations.*'),
            $link(__('Notifications'), 'notifications.index', 'bell', 'notifications.*'),
        ])];
    } elseif ($user->isStudent()) {
        $groups[] = ['title' => __('Mon espace'), 'items' => array_filter([
            $link(__('Tableau de bord'), 'dashboard', 'home', 'dashboard'),
            $link(__('Mes formations'), 'student.formations', 'book', 'student.formations'),
            $link(__('Mon emploi du temps'), 'student.schedule', 'calendar', 'student.schedule'),
            $link(__('Mes présences'), 'student.attendances', 'clock', 'student.attendances'),
            $link(__('Mes notes'), 'student.evaluations', 'chart', 'student.evaluations'),
            $link(__('Mes documents'), 'student.documents', 'document', 'student.documents*'),
            $link(__('Mes certificats'), 'student.certificates', 'certificate', 'student.certificates*'),
            $link(__('Mes paiements'), 'student.payments', 'banknotes', 'student.payments'),
            $link(__('Notifications'), 'notifications.index', 'bell', 'notifications.*'),
        ])];
    }
@endphp

<aside
    class="ec-sidebar-panel fixed inset-y-0 start-0 z-40 flex flex-col border-e border-slate-200 bg-white transition-all duration-200 dark:border-slate-800 dark:bg-slate-900
        lg:static"
    :class="{
        'ec-sidebar-panel-open': $store.ui.sidebarOpen,
        'ec-sidebar': !$store.ui.sidebarCollapsed,
        'ec-sidebar-collapsed': $store.ui.sidebarCollapsed
    }"
    aria-label="{{ __('Navigation principale') }}"
>
    <div class="flex h-16 items-center border-b border-slate-200 px-3.5 dark:border-slate-800"
         :class="$store.ui.sidebarCollapsed ? 'justify-center' : 'justify-between'">
        <a href="{{ ($user->isSuperAdmin() || $user->isCenterAdmin()) ? route('admin.home') : route('dashboard') }}"
           class="flex min-w-0 items-center gap-2.5 font-semibold text-slate-900 dark:text-white"
           x-show="!$store.ui.sidebarCollapsed"
           x-transition.opacity.duration.200ms>
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white shadow-2xs">
                <x-icon name="academic" class="h-5 w-5" />
            </span>
            <span class="truncate font-semibold tracking-tight">{{ __('Education Centers') }}</span>
        </a>

        {{-- Bouton de réduction / agrandissement pour Desktop (icône seule) --}}
        <button type="button"
                x-on:click="$store.ui.toggleCollapsed()"
                :aria-label="$store.ui.sidebarCollapsed ? @js(__('Agrandir la sidebar')) : @js(__('Réduire la sidebar'))"
                :title="$store.ui.sidebarCollapsed ? @js(__('Agrandir la sidebar')) : @js(__('Réduire la sidebar'))"
                :aria-expanded="!$store.ui.sidebarCollapsed"
                class="hidden lg:inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700/80 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white transition-colors">
            <x-icon name="chevron-left" class="ec-collapse-chevron h-4 w-4 transition-all duration-200" x-bind:class="$store.ui.sidebarCollapsed ? 'rotate-180' : ''" />
        </button>
    </div>

    @if($user->isSuperAdmin() || ($user->isCenterAdmin() && $isFinance))
        @if($isFinance)
            {{-- BANNER DU CONTEXTE : GESTION FINANCIÈRE (STYLE CAD SYNDIC) --}}
            {{-- Identique pour Super Admin (vue globale) et Admin Centre (vue centre). --}}
            <div class="p-3 border-b border-slate-200/80 dark:border-slate-800">
                <div class="rounded-xl border border-slate-200/90 bg-white p-2.5 shadow-2xs dark:border-slate-800 dark:bg-slate-900" x-show="!$store.ui.sidebarCollapsed">
                    <div class="flex items-center gap-2.5 mb-2">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#ECFDF5] text-[#0DAD98] dark:bg-[#0DAD98]/20 dark:text-[#0DAD98] font-bold text-xs shadow-2xs">
                            📊
                        </span>
                        <div class="min-w-0">
                            <span class="block text-2xs uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500">{{ __('Contexte actif') }}</span>
                            <span class="block truncate text-xs font-bold text-slate-900 dark:text-white">{{ __('Gestion financière') }}</span>
                        </div>
                    </div>
                    <a href="{{ route('admin.home') }}"
                       id="sidebar-changer-finance"
                       class="w-full flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 py-1.5 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition-colors"
                       title="{{ __('Retour à l\'accueil') }}">
                        <x-icon name="home" class="h-3 w-3 text-slate-400" />
                        <span>{{ __('Accueil') }}</span>
                    </a>
                </div>
                <div class="flex justify-center" x-show="$store.ui.sidebarCollapsed">
                    <a href="{{ route('admin.home') }}"
                       class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#ECFDF5] text-[#0DAD98] hover:bg-[#0DAD98] hover:text-white transition-colors shadow-2xs"
                       title="{{ __('Gestion financière — Retour à l\'accueil') }}">
                        <x-icon name="banknotes" class="h-4.5 w-4.5" />
                    </a>
                </div>
            </div>
        @elseif($isStagiaires)
            {{-- BANNER DU CONTEXTE : GESTION ADMINISTRATIVE (STAGIAIRES) --}}
            <div class="border-b border-slate-200 bg-purple-50/80 p-3 dark:border-slate-800 dark:bg-purple-950/30">
                <div class="flex items-center justify-between gap-2" x-show="!$store.ui.sidebarCollapsed">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-purple-600 text-white shadow-2xs text-sm">
                            🧑‍💼
                        </span>
                        <div class="min-w-0">
                            <span class="block text-2xs uppercase font-bold tracking-wider text-purple-800 dark:text-purple-300">{{ __('Espace administratif') }}</span>
                            <span class="block truncate text-xs font-bold text-slate-900 dark:text-white">{{ __('Stagiaires') }}</span>
                        </div>
                    </div>
                    <a href="{{ route('admin.home') }}"
                       class="inline-flex items-center gap-1 rounded-lg border border-purple-300/80 bg-white px-2 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-100 hover:text-purple-800 dark:border-purple-700/80 dark:bg-slate-900 dark:text-purple-300 dark:hover:bg-purple-900/40 transition-colors shadow-2xs shrink-0"
                       title="{{ __('Retour à l\'accueil') }}">
                        <x-icon name="home" class="h-3 w-3" />
                        <span>{{ __('Accueil') }}</span>
                    </a>
                </div>
                <div class="flex justify-center" x-show="$store.ui.sidebarCollapsed">
                    <a href="{{ route('admin.home') }}"
                       class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition-colors shadow-2xs"
                       title="{{ __('Stagiaires — Retour à l\'accueil') }}">
                        <x-icon name="home" class="h-4.5 w-4.5" />
                    </a>
                </div>
            </div>
        @else
            {{-- BANNER DU CONTEXTE : DOMAINE DE FORMATION (dynamique) --}}
            <div class="border-b border-slate-200 {{ $pal['banner_bg'] }} p-3 dark:border-slate-800"
                 data-domain="{{ $activeDomain }}"
                 id="sidebar-domain-banner">
                <div class="flex items-center justify-between gap-2" x-show="!$store.ui.sidebarCollapsed">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $pal['icon_bg'] }} text-white shadow-2xs">
                            <x-icon name="{{ $domainIcon }}" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            <span class="block text-2xs uppercase font-bold tracking-wider {{ $pal['label_text'] }}">{{ __('Domaine') }}</span>
                            <span class="block truncate text-xs font-bold text-slate-900 dark:text-white">{{ $domainLabel }}</span>
                        </div>
                    </div>
                    <a href="{{ route('admin.home') }}"
                       class="inline-flex items-center gap-1 rounded-lg border {{ $pal['btn_border'] }} bg-white px-2 py-1 text-xs font-semibold {{ $pal['btn_text'] }} {{ $pal['btn_hover'] }} dark:bg-slate-900 transition-colors shadow-2xs shrink-0"
                       title="{{ __('Retour à l\'accueil') }}"
                       id="sidebar-changer-domaine">
                        <x-icon name="home" class="h-3 w-3" />
                        <span>{{ __('Accueil') }}</span>
                    </a>
                </div>
                <div class="flex justify-center" x-show="$store.ui.sidebarCollapsed">
                    <a href="{{ route('admin.home') }}"
                       class="flex h-8 w-8 items-center justify-center rounded-lg {{ $pal['icon_bg'] }} text-white {{ $pal['icon_hover'] }} transition-colors shadow-2xs"
                       title="{{ $domainLabel }} — {{ __('Retour à l\'accueil') }}">
                        <x-icon name="home" class="h-4.5 w-4.5" />
                    </a>
                </div>
            </div>
        @endif
    @elseif($user->isCenterAdmin() && $isStagiaires)
        {{-- BANNER DU CONTEXTE : GESTION DES STAGIAIRES (ADMIN CENTRE, périmètre = son centre) --}}
        <div class="border-b border-slate-200 bg-purple-50/80 p-3 dark:border-slate-800 dark:bg-purple-950/30">
            <div class="flex items-center justify-between gap-2" x-show="!$store.ui.sidebarCollapsed">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-purple-600 text-white shadow-2xs text-sm">
                        🧑‍💼
                    </span>
                    <div class="min-w-0">
                        <span class="block text-2xs uppercase font-bold tracking-wider text-purple-800 dark:text-purple-300">{{ __('Espace administratif') }}</span>
                        <span class="block truncate text-xs font-bold text-slate-900 dark:text-white">{{ __('Stagiaires') }}</span>
                    </div>
                </div>
                <a href="{{ route('admin.home') }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-purple-300/80 bg-white px-2 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-100 hover:text-purple-800 dark:border-purple-700/80 dark:bg-slate-900 dark:text-purple-300 dark:hover:bg-purple-900/40 transition-colors shadow-2xs shrink-0"
                   title="{{ __('Retour à l\'accueil') }}">
                    <x-icon name="home" class="h-3 w-3" />
                    <span>{{ __('Accueil') }}</span>
                </a>
            </div>
            <div class="flex justify-center" x-show="$store.ui.sidebarCollapsed">
                <a href="{{ route('admin.home') }}"
                   class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition-colors shadow-2xs"
                   title="{{ __('Stagiaires — Retour à l\'accueil') }}">
                    <x-icon name="home" class="h-4.5 w-4.5" />
                </a>
            </div>
        </div>
    @elseif($user->isCenterAdmin() && !empty($activeDomain))
        {{-- BANNER DU CONTEXTE : DOMAINE ACTIF (ADMIN CENTRE, périmètre = son centre) --}}
        <div class="border-b border-slate-200 {{ $pal['banner_bg'] }} p-3 dark:border-slate-800"
             data-domain="{{ $activeDomain }}"
             id="sidebar-domain-banner-center">
            <div class="flex items-center justify-between gap-2" x-show="!$store.ui.sidebarCollapsed">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $pal['icon_bg'] }} text-white shadow-2xs">
                        <x-icon name="{{ $domainIcon }}" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <span class="block text-2xs uppercase font-bold tracking-wider {{ $pal['label_text'] }}">{{ __('Domaine') }}</span>
                        <span class="block truncate text-xs font-bold text-slate-900 dark:text-white">{{ $domainLabel }}</span>
                    </div>
                </div>
                <a href="{{ route('admin.home') }}"
                   class="inline-flex items-center gap-1 rounded-lg border {{ $pal['btn_border'] }} bg-white px-2 py-1 text-xs font-semibold {{ $pal['btn_text'] }} {{ $pal['btn_hover'] }} dark:bg-slate-900 transition-colors shadow-2xs shrink-0"
                   title="{{ __('Retour à l\'accueil') }}"
                   id="sidebar-changer-domaine-center">
                    <x-icon name="home" class="h-3 w-3" />
                    {{-- Ramène à la page d'accueil générale de l'Admin Centre
                         (/admin/accueil) : destination et design inchangés,
                         seul le libellé redevient « Accueil ». Le tableau de
                         bord pédagogique de la formation active s'ouvre par le
                         lien « Dashboard » de la navigation. --}}
                    <span>{{ __('Accueil') }}</span>
                </a>
            </div>
            <div class="mt-2 flex items-center gap-2" x-show="!$store.ui.sidebarCollapsed">
                <a href="{{ route('formations-generales.index') }}"
                   class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border {{ $pal['btn_border'] }} bg-white/70 px-2 py-1 text-xs font-semibold {{ $pal['btn_text'] }} {{ $pal['btn_hover'] }} dark:bg-slate-900/60 transition-colors"
                   id="sidebar-tous-domaines-center">
                    <x-icon name="building" class="h-3 w-3" />
                    <span>{{ __('Toutes les formations') }}</span>
                </a>
            </div>
            <div class="flex justify-center" x-show="$store.ui.sidebarCollapsed">
                <a href="{{ route('admin.home') }}"
                   class="flex h-8 w-8 items-center justify-center rounded-lg {{ $pal['icon_bg'] }} text-white {{ $pal['icon_hover'] }} transition-colors shadow-2xs"
                   title="{{ $domainLabel }} — {{ __('Retour à l\'accueil') }}">
                    <x-icon name="home" class="h-4.5 w-4.5" />
                </a>
            </div>
        </div>
    @endif

    <nav class="ec-nav-in flex-1 space-y-4 overflow-y-auto p-3">
        @foreach($groups as $index => $group)
            @if(!empty($group['items']))
                @php
                    $hasActiveItem = collect($group['items'])->contains('active', true);
                    $sectionKey = 'ec-sidebar-sec-' . Str::slug($group['title'] ?? 'group-' . $index);
                    $isCollapsible = !empty($group['title']);
                @endphp

                @if($isCollapsible)
                    <div x-data="{
                        open: {{ $hasActiveItem ? 'true' : "localStorage.getItem('$sectionKey') !== null ? localStorage.getItem('$sectionKey') === '1' : false" }},
                        toggle() {
                            this.open = !this.open;
                            try { localStorage.setItem('{{ $sectionKey }}', this.open ? '1' : '0'); } catch (e) {}
                        }
                    }" class="space-y-1">
                        {{-- Bouton accordéon (visible lorsque la sidebar n'est pas réduite) --}}
                        <button type="button"
                                x-on:click="toggle()"
                                x-show="!$store.ui.sidebarCollapsed"
                                x-transition.opacity.duration.200ms
                                class="group flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-start text-xs font-bold uppercase tracking-wider text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus:outline-none dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-200 transition-colors"
                                :aria-expanded="open">
                            <span class="flex items-center gap-1.5 font-bold tracking-wider">
                                {{ $group['title'] }}
                            </span>
                            <span class="ec-accordion-chevron text-slate-400 transition-all duration-200 ease-in-out group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300"
                                  :class="{ 'rotate-90': open, 'rotate-0': !open }">
                                <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                            </span>
                        </button>

                        {{-- Séparateur discret si sidebar repliée en mode icônes --}}
                        <div x-show="$store.ui.sidebarCollapsed" class="my-2 border-t border-slate-200 dark:border-slate-800"></div>

                        {{-- Liste des liens de la section --}}
                        <div x-show="open || $store.ui.sidebarCollapsed"
                             x-collapse
                             class="space-y-0.5">
                            <ul class="space-y-0.5">
                                @foreach($group['items'] as $item)
                                    @php
                                        $linkClass = 'ec-nav-link';
                                        if ($item['active']) {
                                            $linkClass = $isFinance
                                                ? 'flex items-center gap-3 rounded-e-lg border-s-4 border-[#0DAD98] bg-[#ECFDF5] px-2.5 py-2 text-base font-semibold text-[#0DAD98] dark:border-[#0DAD98] dark:bg-[#0DAD98]/15 dark:text-[#0DAD98]'
                                                : 'ec-nav-link ec-nav-link-active';
                                        }
                                    @endphp
                                    <li>
                                        <a href="{{ $item['url'] }}"
                                           class="{{ $linkClass }}"
                                           @if($item['active']) aria-current="page" @endif
                                           title="{{ $item['label'] }}">
                                            <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                                            <span class="flex-1 truncate" x-show="!$store.ui.sidebarCollapsed" x-transition.opacity.duration.150ms>{{ $item['label'] }}</span>
                                            @if($item['icon'] === 'message' && ($user->isSuperAdmin() || $user->isCenterAdmin()))
                                                <span x-data="{ count: 0 }"
                                                      x-init="
                                                          fetch('{{ route('messages.unread-count') }}')
                                                              .then(r => r.json())
                                                              .then(d => count = d.unread_count);
                                                          setInterval(() => fetch('{{ route('messages.unread-count') }}')
                                                              .then(r => r.json())
                                                              .then(d => count = d.unread_count), 5000);
                                                      "
                                                      x-show="count > 0 && !$store.ui.sidebarCollapsed"
                                                      x-text="count > 99 ? '99+' : count"
                                                      class="ms-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-[#0DAD98] px-1 text-2xs font-bold text-white">
                                                </span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @else
                    {{-- Section sans titre (ex: Dashboard du domaine actif) --}}
                    <div>
                        <ul class="space-y-0.5">
                            @foreach($group['items'] as $item)
                                @php
                                    $linkClass = 'ec-nav-link';
                                    if ($item['active']) {
                                        $linkClass = $isFinance
                                            ? 'flex items-center gap-3 rounded-e-lg border-s-4 border-[#0DAD98] bg-[#ECFDF5] px-2.5 py-2 text-base font-semibold text-[#0DAD98] dark:border-[#0DAD98] dark:bg-[#0DAD98]/15 dark:text-[#0DAD98]'
                                            : 'ec-nav-link ec-nav-link-active';
                                    }
                                @endphp
                                <li>
                                    <a href="{{ $item['url'] }}"
                                       class="{{ $linkClass }}"
                                       @if($item['active']) aria-current="page" @endif
                                       title="{{ $item['label'] }}">
                                        <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                                        <span class="flex-1 truncate" x-show="!$store.ui.sidebarCollapsed" x-transition.opacity.duration.150ms>{{ $item['label'] }}</span>
                                        @if($item['icon'] === 'message' && ($user->isSuperAdmin() || $user->isCenterAdmin()))
                                            <span x-data="{ count: 0 }"
                                                  x-init="
                                                      fetch('{{ route('messages.unread-count') }}')
                                                          .then(r => r.json())
                                                          .then(d => count = d.unread_count);
                                                      setInterval(() => fetch('{{ route('messages.unread-count') }}')
                                                          .then(r => r.json())
                                                          .then(d => count = d.unread_count), 5000);
                                                  "
                                                  x-show="count > 0 && !$store.ui.sidebarCollapsed"
                                                  x-text="count > 99 ? '99+' : count"
                                                  class="ms-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-[#0DAD98] px-1 text-2xs font-bold text-white">
                                            </span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        @endforeach
    </nav>

    {{-- ── User card footer ────────────────────────────────────────────── --}}
    @php
        $sbRoleLabel = match ($user->role) {
            'super_admin' => __('Super Admin'),
            'center_admin' => __('Admin Centre'),
            'teacher' => __('Professeur'),
            'student' => __('Élève'),
            default => $user->role,
        };
        $sbCenterLabel = $user->isSuperAdmin() ? __('Tous les centres') : ($user->center?->name ?? __('N/A'));
    @endphp
    <div class="border-t border-slate-200 p-3 dark:border-slate-800">
        <div x-show="!$store.ui.sidebarCollapsed"
             x-transition.opacity.duration.200ms
             class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50">
            <x-ui.avatar :name="$user->name" size="sm" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $sbRoleLabel }} · {{ $sbCenterLabel }}</p>
            </div>
            <form action="{{ route('logout') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" title="{{ __('Déconnexion') }}" aria-label="{{ __('Déconnexion') }}">
                    <x-icon name="logout" class="h-4 w-4" />
                </button>
            </form>
        </div>
        <div class="flex justify-center" x-show="$store.ui.sidebarCollapsed">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" title="{{ __('Déconnexion') }}" aria-label="{{ __('Déconnexion') }}">
                    <x-icon name="logout" class="h-5 w-5" />
                </button>
            </form>
        </div>
    </div>
</aside>
