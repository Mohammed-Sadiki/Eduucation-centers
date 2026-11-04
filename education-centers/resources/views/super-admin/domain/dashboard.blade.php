@php
    /**
     * Configuration visuelle par domaine.
     */
    $domainConfig = [
        'sante'            => ['label' => __('Santé'),            'color' => 'emerald', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-700 dark:text-emerald-300'],
        'informatique'     => ['label' => __('Informatique'),     'color' => 'blue',    'dot' => 'bg-blue-500',    'text' => 'text-blue-700 dark:text-blue-300'],
        'hotellerie'       => ['label' => __('Hôtellerie'),       'color' => 'amber',   'dot' => 'bg-amber-500',   'text' => 'text-amber-700 dark:text-amber-300'],
        'maconnerie'       => ['label' => __('Maçonnerie'),       'color' => 'orange',  'dot' => 'bg-orange-500',  'text' => 'text-orange-700 dark:text-orange-300'],
        'design-interieur' => ['label' => __('Design intérieur'), 'color' => 'violet',  'dot' => 'bg-violet-500',  'text' => 'text-violet-700 dark:text-violet-300'],
        'design-graphique' => ['label' => __('Design graphique'), 'color' => 'pink',    'dot' => 'bg-pink-500',    'text' => 'text-pink-700 dark:text-pink-300'],
    ];
    $cfg = $domainConfig[$domain] ?? $domainConfig['sante'];
    // Libellé issu de la source de vérité (table domains) avec repli canonique.
    $domainLabel = \App\Support\DomainRegistry::labels()[$domain] ?? $cfg['label'];
    $user = auth()->user();

    // Vue partagée Super Admin / Admin Centre. La version simplifiée affichée
    // à l'Admin Centre (4 KPI, sans graphiques ni tableau des inscriptions)
    // est obtenue par condition de rôle : aucune donnée, route ou calcul
    // n'est retiré — seul l'affichage change.
    $isCenterAdmin = $user->isCenterAdmin();

    // Séries prêtes pour Chart.js (à partir des collections du contrôleur)
    $monthLabels = collect(range(1, 12))
        ->map(fn (int $m) => \Illuminate\Support\Carbon::create(null, $m)->translatedFormat('M'))
        ->values()
        ->all();
    $trendValues = array_values($enrollmentTrend->all());
    $distLabels  = array_keys($studentsByCenter->all());
    $distValues  = array_values($studentsByCenter->all());
@endphp

@extends('layouts.app')

@section('title', __('Dashboard') . ' ' . $domainLabel . ' — ' . __('Super Admin'))
@section('description', __('Tableau de bord du domaine') . ' ' . $domainLabel)

@section('content')

    {{-- ── EN-TÊTE ────────────────────────────────────────────────────── --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            {{-- Badge "Domaine actif" + titre de l'espace domaine --}}
            <span class="mb-2 inline-flex items-center gap-1.5 rounded-full {{ $cfg['dot'] }}/15 px-2.5 py-0.5 text-xs font-semibold {{ $cfg['text'] }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $cfg['dot'] }}"></span>
                {{ __('Domaine actif') }}
            </span>
            <h1 class="ec-page-title text-slate-900 dark:text-white">
                {{ __('Espace') }} {{ $domainLabel }}
            </h1>
            <p class="mt-1.5 text-base text-slate-500 dark:text-slate-400">
                @if($user->isSuperAdmin())
                    {{ __('Bonjour') }} {{ $user->name }} — {{ __('voici un aperçu de la filière') }} {{ $domainLabel }} {{ __('pour tous les centres.') }}
                @else
                    {{ __('Bonjour') }} {{ $user->name }} — {{ __('voici un aperçu de la filière') }} {{ $domainLabel }} {{ __('pour votre centre') }} · {{ $user->center?->name ?? '' }}.
                @endif
            </p>
        </div>
        <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
            {{-- Bouton changer de domaine (retour au portail) --}}
            <a href="{{ $user->isSuperAdmin() ? route('super-admin.domaines') : route('center-admin.domaines') }}"
               id="btn-retour-domaines"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-2xs transition hover:-translate-y-px hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
               title="{{ __('Retourner au portail de sélection des domaines') }}">
                <x-icon name="arrow-uturn-left" class="h-4 w-4 text-slate-500 dark:text-slate-400" />
                <span>{{ __('Changer de domaine') }}</span>
            </a>
            <div class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-2xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <x-icon name="calendar" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
                {{ now()->translatedFormat('d F Y') }}
            </div>
        </div>
    </div>

    {{-- ── ÉTAT VIDE (aucune formation dans ce domaine pour ce périmètre) ── --}}
    @if(($stats['formations'] ?? 0) === 0)
        <div class="mb-8 overflow-hidden rounded-3xl border border-dashed border-slate-300 bg-white/60 px-6 py-10 text-center dark:border-slate-700 dark:bg-slate-900/40">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl {{ $cfg['dot'] }}/15 {{ $cfg['text'] }}">
                <x-icon name="book" class="h-7 w-7" />
            </span>
            <h2 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                {{ __('Aucune formation dans ce domaine pour le moment') }}
            </h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                {{ __('Le domaine :domain est disponible pour votre centre. Ajoutez votre première formation depuis la page « Domaines de formation » pour commencer à gérer étudiants, classes et séances.', ['domain' => $domainLabel]) }}
            </p>
        </div>
    @endif

    {{-- ── KPI ────────────────────────────────────────────────────────── --}}
    {{-- Cartes cliquables : chaque destination est filtrée centre + domaine
         côté serveur (session active_domain + isolation propre au rôle).
         Le paramètre ?domain rend en plus les URL explicites et partageables. --}}
    @php
        $domainQuery = ['domain' => $domain];
        $cardUrls = [
            'students'    => route('students.index', $domainQuery),
            'teachers'    => route('teachers.index', $domainQuery),
            'formations'  => route('formations.index', $domainQuery),
            'classes'     => route('classes.index', $domainQuery),
            'enrollments' => route('enrollments.index', $domainQuery),
            // Certificats délivrés uniquement (même critère que la stat).
            'certificates' => route('certificates.index', $domainQuery + ['status' => 'issued']),
            // Présences du jour pour les classes du domaine (même critère que la stat).
            'attendances' => route('attendances.index', $domainQuery + ['date' => now()->toDateString()]),
            // Paiements réellement encaissés dans le domaine (même critère que la stat).
            'payments' => route('payments.index', $domainQuery + ['status' => 'paid']),
        ];
    @endphp
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Étudiants')" :value="format_number($stats['students'])" icon="users" tone="primary" :hint="__('inscrits')" :href="$cardUrls['students']" />
        @unless($isCenterAdmin)
            {{-- Cartes réservées au Super Admin : Professeurs, Formations,
                 Classes et Inscriptions ne sont plus affichées dans le
                 dashboard pédagogique de l'Admin Centre. Les pages, routes,
                 contrôleurs et compteurs correspondants restent intacts. --}}
            <x-ui.stat :label="__('Professeurs')" :value="format_number($stats['teachers'])" icon="user" tone="info" :hint="__('enseignants')" :href="$cardUrls['teachers']" />
            <x-ui.stat :label="__('Formations')" :value="format_number($stats['formations'])" icon="book" tone="success" :hint="__('disponibles')" :href="$cardUrls['formations']" />
            <x-ui.stat :label="__('Classes')" :value="format_number($stats['classes'])" icon="classes" tone="info" :hint="__('actives')" :href="$cardUrls['classes']" />
            <x-ui.stat :label="__('Inscriptions')" :value="format_number($stats['enrollments'])" icon="clipboard" tone="primary" :hint="__('au total')" :href="$cardUrls['enrollments']" />
        @endunless
        <x-ui.stat :label="__('Certificats')" :value="format_number($stats['certificates_issued'])" icon="certificate" tone="success" :hint="__('délivrés')" :href="$cardUrls['certificates']" />
        <x-ui.stat :label="__('Présences (jour)')" :value="format_number($stats['attendances_today'])" icon="clock" tone="info" :hint="__('aujourd\'hui')" :href="$cardUrls['attendances']" />
        <x-ui.stat :label="__('Revenus (payés)')" :value="format_price($stats['payments_total'])" icon="banknotes" tone="warning" :hint="__('encaissés')" :href="$cardUrls['payments']" />
    </div>

    {{-- Filtre centre secondaire (Super Admin) : périmètre du domaine --}}
    @if($user->isSuperAdmin())
        <form method="GET" action="{{ url()->current() }}" class="mb-6 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300">
                <x-icon name="building" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
                {{ __('Centre') }}
            </span>
            <select name="center_id" onchange="this.form.submit()" class="ec-input max-w-[16rem] py-1.5 text-sm" aria-label="{{ __('Filtrer par centre') }}">
                <option value="">{{ __('Tous les centres') }}</option>
                @foreach($centers as $c)
                    <option value="{{ $c->id }}" @selected($centerId === $c->id)>{{ $c->translated('name') }}</option>
                @endforeach
            </select>
            @if($centerId)
                <a href="{{ url()->current() }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">{{ __('Réinitialiser') }}</a>
            @endif
        </form>
    @endif

    {{-- ── GRAPHIQUES (Super Admin uniquement) ────────────────────────── --}}
    {{-- Le dashboard Admin Centre est épuré : « Évolution des inscriptions »
         et « Répartition des étudiants » ne sont plus rendus pour ce rôle.
         La bibliothèque Chart.js et les données restent utilisées ailleurs. --}}
    @unless($isCenterAdmin)
    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        <div class="ec-panel xl:col-span-2">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="chart" class="h-5 w-5" />
                    </span>
                    {{ __('Évolution des inscriptions') }}
                </div>
                <span class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $year }}</span>
            </div>
            <div class="p-4 sm:p-5">
                <div class="h-72"><canvas id="enrollTrendChart"></canvas></div>
            </div>
        </div>

        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-turquoise-50 text-turquoise-600 dark:bg-turquoise-500/15 dark:text-turquoise-400">
                        <x-icon name="users" class="h-5 w-5" />
                    </span>
                    {{ __('Répartition des étudiants') }}
                </div>
            </div>
            <div class="p-4 sm:p-5">
                <div class="mx-auto h-64 max-w-[18rem]"><canvas id="studentsDistChart"></canvas></div>
            </div>
        </div>
    </div>
    @endunless

    {{-- ── TABLEAU + PROCHAINS COURS ──────────────────────────────────── --}}
    {{-- Admin Centre : le tableau « Dernières inscriptions » est retiré, la
         section « Prochains cours » occupe alors toute la largeur (pas de
         colonne vide). --}}
    <div class="grid gap-6{{ $isCenterAdmin ? '' : ' xl:grid-cols-3' }}">
        @unless($isCenterAdmin)
        <div class="ec-panel xl:col-span-2">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="clipboard" class="h-5 w-5" />
                    </span>
                    {{ __('Dernières inscriptions') }}
                </div>
                <a href="{{ route('enrollments.index') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">{{ __('Voir tout') }}</a>
            </div>
            <div class="ec-table-wrap rounded-none border-0">
                <table class="ec-table">
                    <thead>
                        <tr>
                            <th>{{ __('Étudiant') }}</th>
                            <th>{{ __('Formation') }}</th>
                            <th>{{ __('Centre') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Statut') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEnrollments as $e)
                            <tr>
                                <td class="font-medium text-slate-800 dark:text-slate-100">{{ $e->student?->full_name ?? '—' }}</td>
                                <td>{{ $e->formation?->name ?? '—' }}</td>
                                <td>{{ $e->center?->name ?? '—' }}</td>
                                <td class="text-slate-500">{{ $e->created_at?->translatedFormat('d/m/Y') ?? '—' }}</td>
                                <td><x-status-badge type="enrollment" :status="$e->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('Aucune inscription récente.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endunless

        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                        <x-icon name="calendar" class="h-5 w-5" />
                    </span>
                    {{ __('Prochains cours') }}
                </div>
            </div>
            <div class="divide-y divide-slate-100 p-2 dark:divide-slate-800">
                @forelse($upcomingSchedules as $schedule)
                    <div class="flex items-center gap-3 px-3 py-3">
                        <div class="flex w-14 shrink-0 flex-col items-center rounded-lg bg-brand-50 py-1.5 text-center dark:bg-brand-500/15">
                            <span class="text-xs font-bold text-brand-700 dark:text-brand-300">{{ substr($schedule->start_time, 0, 5) }}</span>
                            <span class="text-2xs text-slate-400">{{ substr($schedule->end_time, 0, 5) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $schedule->classRoom?->name ?? '—' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $schedule->teacher?->user?->name ?? '—' }} · {{ $schedule->room?->code ?? '—' }}</p>
                        </div>
                        <x-status-badge type="schedule_type" :status="$schedule->type ?? 'course'" />
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-slate-500">{{ __('Aucun cours planifié à venir.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

@endsection

@unless($isCenterAdmin)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(148,163,184,0.15)' : 'rgba(148,163,184,0.2)';
    const tickColor = isDark ? '#94a3b8' : '#64748b';

    const trendEl = document.getElementById('enrollTrendChart');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'bar',
            data: {
                labels: @js($monthLabels),
                datasets: [{
                    label: @js(__('Inscriptions')),
                    data: @js($trendValues),
                    backgroundColor: 'rgba(99,102,241,0.85)',
                    hoverBackgroundColor: '#4f46e5',
                    borderRadius: 6,
                    maxBarThickness: 26,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: { legend: { display: false }, tooltip: { animation: { duration: 150 } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                },
            },
        });
    }

    const distEl = document.getElementById('studentsDistChart');
    if (distEl) {
        new Chart(distEl, {
            type: 'doughnut',
            data: {
                labels: @js($distLabels),
                datasets: [{
                    data: @js($distValues),
                    backgroundColor: ['#4f46e5', '#6366f1', '#0dad98', '#f59e0b', '#ec4899', '#94a3b8'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: { legend: { position: 'bottom', labels: { color: tickColor, usePointStyle: true, padding: 14, boxWidth: 8 } }, tooltip: { animation: { duration: 150 } } },
            },
        });
    }
});
</script>
@endpush
@endunless
