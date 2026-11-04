@php
    $user = auth()->user();
    $unreadCount = \App\Models\AppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    $roleLabel = match ($user->role) {
        'super_admin' => __('Super Admin'),
        'center_admin' => __('Admin Centre'),
        'teacher' => __('Professeur'),
        'student' => __('Élève'),
        default => $user->role,
    };
    $centerLabel = $user->isSuperAdmin() ? __('Tous les centres') : ($user->center?->name ?? __('N/A'));
    $isStagiaires = request()->routeIs('stagiaires.*');
    $isFinance = request()->routeIs('super-admin.finance.*');
    $isAdmin = $user->isSuperAdmin() || $user->isCenterAdmin();

    // Détection du domaine actif (session ou route)
    $activeDomain = session('active_domain');
    if (!$activeDomain && request()->routeIs('super-admin.domain.dashboard')) {
        $activeDomain = request()->route('domain');
    }
    if (!$activeDomain && request()->routeIs('super-admin.sante.dashboard')) {
        $activeDomain = 'sante';
    }

    // Labels et couleurs par domaine pour la topbar
    $domainTopbarConfig = [
        'sante'            => ['label' => __('Santé'),            'classes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300', 'icon' => 'heart'],
        'informatique'     => ['label' => __('Informatique'),     'classes' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',             'icon' => 'monitor'],
        'hotellerie'       => ['label' => __('Hôtellerie'),       'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',          'icon' => 'building'],
        'maconnerie'       => ['label' => __('Maçonnerie'),       'classes' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',      'icon' => 'wrench'],
        'design-interieur' => ['label' => __('Design intérieur'), 'classes' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',      'icon' => 'home'],
        'design-graphique' => ['label' => __('Design graphique'), 'classes' => 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300',              'icon' => 'brush'],
    ];
    $topbarDomain = $domainTopbarConfig[$activeDomain] ?? null;

    $homeUrl = ($user->isSuperAdmin() || $user->isCenterAdmin())
        ? route('admin.home')
        : route('dashboard');
@endphp

<header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90"
        x-data="{ focusSearch(e){ if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); $refs.search?.focus(); } } }"
        @keydown.window="focusSearch($event)">
    {{-- ── LEFT: menu, scope, domain, search ───────────────────────────── --}}
    <div class="flex min-w-0 items-center gap-2.5">
        <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 transition-colors lg:hidden dark:text-slate-300 dark:hover:bg-slate-800" x-on:click="$store.ui.toggleSidebar()" aria-label="{{ __('Ouvrir le menu') }}">
            <x-icon name="menu" class="h-5 w-5" />
        </button>

        {{-- Scope chip (centre courant / tous les centres) --}}
        @if($user->isSuperAdmin())
            <a href="{{ route('admin.home') }}"
               class="hidden shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-2xs transition hover:border-slate-300 hover:bg-slate-50 sm:inline-flex dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
               title="{{ __('Retour à l\'accueil') }}">
                <x-icon name="building" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
                <span class="max-w-[12rem] truncate">{{ $centerLabel }}</span>
                <x-icon name="home" class="h-3.5 w-3.5 text-slate-400" />
            </a>
        @else
            <span class="hidden shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-2xs sm:inline-flex dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <x-icon name="building" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
                <span class="max-w-[12rem] truncate">{{ $centerLabel }}</span>
            </span>
        @endif

        {{-- Domain / context badge (Super Admin) --}}
        @if($user->isSuperAdmin())
            @if($isFinance)
                <span class="hidden items-center gap-1.5 rounded-full bg-turquoise-50 px-2.5 py-1 text-xs font-semibold text-turquoise-600 ring-1 ring-turquoise-500/25 md:inline-flex dark:bg-turquoise-500/15 dark:text-turquoise-400">
                    <x-icon name="chart" class="h-3.5 w-3.5" />
                    {{ __('Gestion financière') }}
                </span>
            @elseif($isStagiaires)
                <span class="hidden items-center gap-1.5 rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700 md:inline-flex dark:bg-purple-900/40 dark:text-purple-300">
                    <x-icon name="briefcase" class="h-3.5 w-3.5" />
                    {{ __('Stagiaires') }}
                </span>
            @elseif($topbarDomain)
                <span class="hidden items-center gap-1.5 rounded-full {{ $topbarDomain['classes'] }} px-2.5 py-1 text-xs font-semibold md:inline-flex">
                    <x-icon name="{{ $topbarDomain['icon'] }}" class="h-3.5 w-3.5" />
                    {{ $topbarDomain['label'] }}
                </span>
            @endif
        @elseif($user->isCenterAdmin() && $isFinance)
            {{-- Badge de contexte financier : identique au Super Admin (module partagé). --}}
            <span class="hidden items-center gap-1.5 rounded-full bg-turquoise-50 px-2.5 py-1 text-xs font-semibold text-turquoise-600 ring-1 ring-turquoise-500/25 md:inline-flex dark:bg-turquoise-500/15 dark:text-turquoise-400">
                <x-icon name="chart" class="h-3.5 w-3.5" />
                {{ __('Gestion financière') }}
            </span>
        @endif

        {{-- Global search (admins only — routes to a real filtered list) --}}
        @if($isAdmin)
            <form action="{{ route('students.index') }}" method="GET" class="relative hidden w-full max-w-md lg:block">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input x-ref="search" type="search" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('Rechercher un étudiant…') }}"
                       class="ec-input ps-9 pe-16 py-2" aria-label="{{ __('Rechercher un étudiant') }}">
                <kbd class="pointer-events-none absolute inset-y-0 end-0 my-auto me-2 hidden h-6 items-center rounded-md border border-slate-200 bg-slate-50 px-1.5 text-xs font-semibold text-slate-400 xl:inline-flex dark:border-slate-700 dark:bg-slate-800">Ctrl K</kbd>
            </form>
        @endif
    </div>

    {{-- ── RIGHT: lang, theme, messages, notifications, user ────────────── --}}
    <div class="flex items-center gap-1.5 sm:gap-2">
        {{-- FR / AR pill --}}
        @include('layouts.partials.language-switcher')

        {{-- Theme toggle --}}
        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 transition-colors dark:hover:bg-slate-800" x-on:click="$store.ui.toggleTheme()" aria-label="{{ __('Basculer le thème') }}">
            <x-icon name="moon" class="h-5 w-5" x-show="!$store.ui.dark" />
            <x-icon name="sun" class="h-5 w-5" x-show="$store.ui.dark" x-cloak />
        </button>

        {{-- Messages (admins) --}}
        @if($isAdmin)
        <div class="relative" x-data="{ msgCount: 0 }"
             x-init="
                 fetch('{{ route('messages.unread-count') }}').then(r => r.json()).then(d => msgCount = d.unread_count);
                 setInterval(() => fetch('{{ route('messages.unread-count') }}').then(r => r.json()).then(d => msgCount = d.unread_count), 5000);
             ">
            <a href="{{ route('messages.index') }}" id="topbar-messages-btn"
               class="relative flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 {{ request()->routeIs('messages.*') ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300' : '' }}"
               aria-label="{{ __('Messagerie') }}">
                <x-icon name="message" class="h-5 w-5" />
                <span x-show="msgCount > 0" x-text="msgCount > 9 ? '9+' : msgCount"
                      class="absolute end-0.5 top-0.5 flex min-w-4 items-center justify-center rounded-full bg-turquoise-500 px-1 py-0.5 text-2xs font-bold leading-none text-white"></span>
            </a>
        </div>
        @endif

        {{-- Notifications --}}
        <div class="relative"
             x-data="{
                 open: false,
                 unread: {{ $unreadCount }},
                 refresh() {
                     fetch('{{ route('notifications.dropdown') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                         .then(r => r.ok ? r.text() : '')
                         .then(html => {
                             const box = $refs.body;
                             if (! box || ! html) return;
                             box.innerHTML = html;
                             const card = box.querySelector('[data-notif-body]');
                             if (card) { unread = parseInt(card.getAttribute('data-unread') || '0', 10) || 0; }
                         })
                         .catch(() => {});
                 },
             }"
             x-init="refresh(); setInterval(() => { if (! document.hidden) refresh(); }, {{ (int) config('notifications.poll_interval', 45) }} * 1000);"
             @click.outside="open = false"
             @keydown.escape.window="open = false">
            <button type="button" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 transition-colors dark:hover:bg-slate-800" x-on:click="open = !open; if(open) refresh()" aria-label="{{ __('Notifications') }}" aria-haspopup="true" :aria-expanded="open">
                <x-icon name="bell" class="h-5 w-5" />
                <span x-show="unread > 0" x-cloak x-text="unread > 9 ? '9+' : unread"
                      class="absolute end-1 top-1 inline-flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-2xs font-semibold text-white"></span>
            </button>
            <div x-ref="body" x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                @include('notifications.partials.dropdown', ['user' => $user])
            </div>
        </div>

        {{-- User menu --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" class="flex items-center gap-2 rounded-xl p-1 pe-1.5 hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="open = !open" aria-haspopup="true" :aria-expanded="open">
                <x-ui.avatar :name="$user->name" size="sm" />
                <span class="hidden text-start sm:block">
                    <span class="block max-w-[140px] truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $user->name }}</span>
                    <span class="block max-w-[140px] truncate text-xs text-slate-500">{{ $roleLabel }} · {{ $centerLabel }}</span>
                </span>
                <x-icon name="chevron-down" class="hidden h-4 w-4 text-slate-400 sm:block" />
            </button>
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute end-0 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <p class="truncate font-semibold">{{ $user->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Centre :') }} <strong class="text-slate-700 dark:text-slate-200">{{ $centerLabel }}</strong></p>
                </div>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                    <x-icon name="home" class="h-4 w-4" /> {{ __('Dashboard') }}
                </a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40">
                        <x-icon name="logout" class="h-4 w-4" /> {{ __('Déconnexion') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
