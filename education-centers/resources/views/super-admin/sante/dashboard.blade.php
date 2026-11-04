@extends('layouts.app')

@section('title', __('Dashboard Santé') . ' — ' . config('app.name'))
@section('description', __('Tableau de bord de la formation générale Santé — vue d\'ensemble des données.'))

@section('breadcrumb')
    <li class="flex items-center gap-2">
        <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </svg>
        <a href="{{ route('super-admin.domaines') }}" class="hover:text-brand-700 dark:hover:text-brand-400 transition-colors">{{ __('Formations générales') }}</a>
    </li>
    <li class="flex items-center gap-2">
        <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </svg>
        <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('Santé') }}</span>
    </li>
    <li class="flex items-center gap-2">
        <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </svg>
        <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('Dashboard') }}</span>
    </li>
@endsection

@section('content')

    {{-- ────────────────────────────────────────────────────────
         EN-TÊTE DU DASHBOARD
    ──────────────────────────────────────────────────────── --}}
    <div class="mb-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                {{-- Icône domaine --}}
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-md">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                    </svg>
                </div>
                <div>
                    {{-- Label domaine --}}
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 mb-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ __('Formation générale active') }}
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Espace Santé') }}
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Tableau de bord et gestion administrative de la filière Santé') }}
                    </p>
                </div>
            </div>

            {{-- Bouton Changer de domaine --}}
            <a href="{{ route('super-admin.domaines') }}"
               id="btn-retour-domaines"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-xs
                      hover:bg-slate-50 hover:border-slate-300 hover:text-slate-900 hover:-translate-y-px
                      dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700
                      transition-all duration-200 shrink-0"
               title="{{ __('Retourner au portail de sélection des formations générales') }}">
                <x-icon name="arrow-uturn-left" class="h-4 w-4 text-slate-500 dark:text-slate-400" />
                <span>{{ __('Changer de formation générale') }}</span>
            </a>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────
         BANDEAU D'INFORMATION — DONNÉES NON FILTRÉES
    ──────────────────────────────────────────────────────── --}}
    <div class="mb-8 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 dark:border-amber-700/40 dark:bg-amber-900/20">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">{{ __('Données globales — filtrage par formation générale à venir') }}</p>
                <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">
                    {{ __('Les statistiques ci-dessous reflètent') }} <strong>{{ __('l\'ensemble des données') }}</strong> {{ __('de la plateforme (toutes formations générales confondues). Pour afficher uniquement les données de la formation générale Santé, il faudra ajouter un champ') }} <code class="rounded bg-amber-200/70 px-1 py-0.5 font-mono dark:bg-amber-800/40">{{ __('domain') }}</code>
                    {{ __('sur les modèles (formations, centres, étudiants…) et appliquer le filtre correspondant.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────
         CARTES STATISTIQUES
    ──────────────────────────────────────────────────────── --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Étudiants')"    :value="$stats['students']"            icon="academic"   tone="info"    />
        <x-ui.stat :label="__('Professeurs')"  :value="$stats['teachers']"            icon="user"        tone="success" />
        <x-ui.stat :label="__('Formations')"   :value="$stats['formations']"          icon="book"        tone="primary" />
        <x-ui.stat :label="__('Classes')"      :value="$stats['classes']"             icon="classes"     tone="info"    />
        <x-ui.stat :label="__('Inscriptions')" :value="$stats['enrollments']"         icon="clipboard"   tone="primary" />
        <x-ui.stat :label="__('En attente')"   :value="$stats['pending_enrollments']" icon="clock"       tone="warning" />
        <x-ui.stat :label="__('Présences aujourd\'hui')" :value="$stats['attendances_today']" icon="chart" tone="neutral" />
        <x-ui.stat :label="__('Revenus (payés)')"
                   :value="format_price($stats['payments_total'])"
                   icon="banknotes"
                   tone="success" />
    </div>

    {{-- ────────────────────────────────────────────────────────
         TABLEAUX : Inscriptions récentes + Prochains cours
    ──────────────────────────────────────────────────────── --}}
    <div class="grid gap-6 xl:grid-cols-2">

        {{-- Dernières inscriptions --}}
        <x-ui.card :padding="false">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/40">
                    <x-icon name="clipboard" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
                </div>
                <h2 class="font-semibold text-slate-800 dark:text-white">{{ __('Dernières inscriptions') }}</h2>
            </div>
            <div class="ec-table-wrap border-0">
                <table class="ec-table">
                    <thead>
                        <tr>
                            <th>{{ __('Étudiant') }}</th>
                            <th>{{ __('Formation') }}</th>
                            <th>{{ __('Centre') }}</th>
                            <th>{{ __('Statut') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEnrollments as $e)
                            <tr>
                                <td>{{ $e->student?->full_name ?? '—' }}</td>
                                <td>{{ $e->formation?->general_formation_name ?? '—' }}</td>
                                <td>{{ $e->center?->name ?? '—' }}</td>
                                <td><x-status-badge type="enrollment" :status="$e->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-500">{{ __('Aucune inscription récente.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        {{-- Prochains cours planifiés --}}
        <x-ui.card :padding="false">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                    <x-icon name="calendar" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <h2 class="font-semibold text-slate-800 dark:text-white">{{ __('Prochains cours planifiés') }}</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($upcomingSchedules as $schedule)
                    <div class="flex items-center justify-between px-5 py-3.5 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800 dark:text-white">
                                {{ $schedule->classRoom?->perimeter_label ?? '—' }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $schedule->teacher?->user?->name ?? '—' }}
                                ·
                                {{ $schedule->room?->name ?? '—' }}
                            </p>
                        </div>
                        <div class="shrink-0 text-end text-xs text-slate-500 dark:text-slate-400">
                            <p class="font-medium text-slate-700 dark:text-slate-200">
                                {{ \Carbon\Carbon::parse($schedule->date)->translatedFormat('d M Y') }}
                            </p>
                            <p>{{ substr($schedule->start_time, 0, 5) }} – {{ substr($schedule->end_time, 0, 5) }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Aucun cours planifié à venir.') }}
                    </p>
                @endforelse
            </div>
        </x-ui.card>

    </div>{{-- /grid --}}

@endsection
