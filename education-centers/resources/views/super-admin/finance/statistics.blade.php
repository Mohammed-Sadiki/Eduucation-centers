@extends('layouts.app')

@section('title', __('Statistiques financières') . ' — ' . config('app.name'))

@section('content')
<div class="space-y-6">

    {{-- En-tête de la page --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-[#ECFDF5] px-2.5 py-0.5 text-xs font-semibold text-[#0DAD98] border border-[#0DAD98]/20 dark:bg-[#0DAD98]/15 dark:text-[#0DAD98] mb-1.5 shadow-2xs">
                <span>📈</span>
                <span>{{ __('Gestion financière · Analytique avancée & Métriques') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Statistiques financières') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Analyse comparative des encaissements, dépenses opérationnelles et masse salariale.') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('super-admin.finance.dashboard') }}" class="finance-btn-secondary">
                <x-icon name="arrow-left" class="h-4 w-4 text-slate-500" />
                <span>{{ __('Dashboard') }}</span>
            </a>
            <a href="{{ route('super-admin.finance.reports') }}" class="finance-btn-primary">
                <x-icon name="arrow-down-tray" class="h-4 w-4" />
                <span>{{ __('Exporter le rapport') }}</span>
            </a>
        </div>
    </div>

    {{-- Filtres compacts --}}
    <form method="GET" action="{{ route('super-admin.finance.statistics') }}"
          class="rounded-xl border border-slate-200/90 bg-white p-3.5 shadow-[0_1px_3px_rgba(0,0,0,0.02)] dark:border-slate-800 dark:bg-slate-900">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 items-center">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Formation') }}</label>
                <select name="domain" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
                    <option value="all" {{ $domain === 'all' ? 'selected' : '' }}>{{ __('Toutes les formations') }}</option>
                    @foreach($domains as $slug => $d)
                        <option value="{{ $slug }}" {{ $domain === $slug ? 'selected' : '' }}>{{ __(is_array($d) ? ($d['name'] ?? $slug) : $d) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Centre') }}</label>
                <select name="center_id" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
                    <option value="" {{ empty($centerId) ? 'selected' : '' }}>{{ __('Tous les centres') }}</option>
                    @foreach($centers as $c)
                        <option value="{{ $c->id }}" {{ $centerId === $c->id ? 'selected' : '' }}>{{ $c->translated('name') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Année scolaire') }}</label>
                <select name="school_year" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
                    @foreach($schoolYears as $sy)
                        <option value="{{ $sy }}" {{ $schoolYear === $sy ? 'selected' : '' }}>{{ $sy }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Année civile') }}</label>
                <select name="year" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
                    @foreach([2025, 2026, 2027, 2028] as $y)
                        <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    {{-- 3 KPI Épurés (Capture 3 Cad Syndic) --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="finance-card p-4.5">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Total attendu') }}</span>
            <div class="mt-2 text-2xl lg:text-3xl font-extrabold text-slate-900 dark:text-white whitespace-nowrap">
                {{ format_price($kpis['total_attendu'] ?? 0, 'MAD') }}
            </div>
            <div class="mt-3 text-[11px] text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Base annuelle scolarités') }}
            </div>
        </div>

        <div class="finance-card p-4.5">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Reste impayé') }}</span>
            <div class="mt-2 text-2xl lg:text-3xl font-extrabold text-amber-600 dark:text-amber-400 whitespace-nowrap">
                {{ format_price($kpis['total_restant_etudiants'] ?? 0, 'MAD') }}
            </div>
            <div class="mt-3 text-[11px] text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Créances à percevoir') }}
            </div>
        </div>

        <div class="finance-card p-4.5">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Taux de recouvrement') }}</span>
            <div class="mt-2 text-2xl lg:text-3xl font-extrabold text-[#0DAD98] whitespace-nowrap">
                {{ $kpis['taux_recouvrement'] ?? 0 }}%
            </div>
            <div class="mt-3 text-[11px] text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Encaissé :') }} {{ format_price($kpis['total_encaisse'] ?? 0, 'MAD') }}
            </div>
        </div>
    </div>

    {{-- Récapitulatif avec grande barre de progression --}}
    <div class="finance-card p-5 sm:p-6">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Recouvrement général des scolarités') }}</h2>
            <span class="text-xs font-bold text-[#0DAD98]">{{ $kpis['taux_recouvrement'] ?? 0 }}{{ __('% validé') }}</span>
        </div>
        <div class="mt-4 space-y-2">
            <div class="h-3 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-full rounded-full bg-[#0DAD98] transition-all duration-500"
                     style="width: {{ min(100, max(0, $kpis['taux_recouvrement'] ?? 0)) }}%"></div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                <span>{{ format_price(0, 'MAD') }}</span>
                <span>{{ __('Restant :') }} {{ format_price($kpis['total_restant_etudiants'] ?? 0, 'MAD') }}</span>
                <span>{{ __('Attendu :') }} {{ format_price($kpis['total_attendu'] ?? 0, 'MAD') }}</span>
            </div>
        </div>
    </div>

    {{-- Deux Graphiques côte à côte (Capture 3 Cad Syndic) --}}
    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Graphique 1 : Encaissements par mois (Courbe / Barres) --}}
        <div class="finance-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Encaissements par mois') }}</h3>
                    <p class="text-[11px] text-slate-500">{{ __('Montants réels perçus sur l\'année') }}</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-[#0DAD98]">
                    {{ __('Recettes réelles') }}
                </span>
            </div>

            @php
                $monthlyEvo = $kpis['monthly_evolution'] ?? [];
                $evolution  = $monthlyEvo['items'] ?? [];
                $maxVal     = max(1, $monthlyEvo['max_amount'] ?? 1);
            @endphp

            <div class="h-48 w-full flex items-end justify-between gap-3 pt-6 pb-2 border-b border-slate-100 dark:border-slate-800">
                @foreach($evolution as $ev)
                    @php
                        $hEnc = $maxVal > 0 ? round((($ev['collected'] ?? 0) / $maxVal) * 100) : 0;
                    @endphp
                    <div class="flex-1 flex flex-col items-center h-full justify-end group relative">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-8 bg-slate-900 text-white text-[10px] py-1 px-2 rounded whitespace-nowrap z-10 shadow">
                            {{ format_price($ev['collected'] ?? 0, 'MAD') }}
                        </div>
                        <div class="w-full bg-[#0DAD98] rounded-t-sm transition-all duration-300 group-hover:brightness-110"
                             style="height: {{ max(4, $hEnc) }}%"></div>
                        <span class="mt-2 text-[10px] font-semibold text-slate-400 truncate w-full text-center">
                            {{ $ev['label'] ?? '' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Graphique 2 : Salaires & Dépenses par mois --}}
        <div class="finance-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Salaires & Dépenses par mois') }}</h3>
                    <p class="text-[11px] text-slate-500">{{ __('Décaissements d\'exploitation mensuels') }}</p>
                </div>
                <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-600">
                    {{ __('Sorties financières') }}
                </span>
            </div>

            <div class="h-48 w-full flex items-end justify-between gap-3 pt-6 pb-2 border-b border-slate-100 dark:border-slate-800">
                @foreach($evolution as $ev)
                    @php
                        $hSal = $maxVal > 0 ? round((($ev['salaries'] ?? 0) / $maxVal) * 100) : 0;
                    @endphp
                    <div class="flex-1 flex flex-col items-center h-full justify-end group relative">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-8 bg-slate-900 text-white text-[10px] py-1 px-2 rounded whitespace-nowrap z-10 shadow">
                            {{ __('Salaires:') }} {{ format_price($ev['salaries'] ?? 0, 'MAD') }}
                        </div>
                        <div class="w-full bg-slate-400 dark:bg-slate-600 rounded-t-sm transition-all duration-300 group-hover:brightness-110"
                             style="height: {{ max(4, $hSal) }}%"></div>
                        <span class="mt-2 text-[10px] font-semibold text-slate-400 truncate w-full text-center">
                            {{ $ev['label'] ?? '' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Graphique supplémentaire : Encaissements vs Dépenses sur l'année --}}
    <div class="finance-card p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Encaissements vs Dépenses consolidées') }}</h3>
                <p class="text-[11px] text-slate-500">{{ __('Bilan comparatif annuel des flux de trésorerie') }}</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="inline-flex items-center gap-1.5 text-[#0DAD98]">
                    <span class="h-2 w-2 rounded-full bg-[#0DAD98]"></span>
                    {{ __('Recettes (') }}{{ format_price($kpis['total_encaisse'] ?? 0, 'MAD') }})
                </span>
                <span class="inline-flex items-center gap-1.5 text-rose-600">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    {{ __('Charges & Salaires (') }}{{ format_price(($kpis['salaires_payes'] ?? 0) + $expensesTotal, 'MAD') }})
                </span>
            </div>
        </div>

        @php
            $totalRecettes = (float) ($kpis['total_encaisse'] ?? 0);
            $totalSorties  = (float) ($kpis['salaires_payes'] ?? 0) + $expensesTotal;
            $maxFlux = max(1, max($totalRecettes, $totalSorties));
            $pctRec = round(($totalRecettes / $maxFlux) * 100);
            $pctSort = round(($totalSorties / $maxFlux) * 100);
        @endphp

        <div class="space-y-4 pt-3">
            <div>
                <div class="flex items-center justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-600 dark:text-slate-300">{{ __('Total Encaissé') }}</span>
                    <span class="text-[#0DAD98]">{{ format_price($totalRecettes, 'MAD') }}</span>
                </div>
                <div class="h-3 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                    <div class="h-full rounded-full bg-[#0DAD98]" style="width: {{ $pctRec }}%"></div>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-600 dark:text-slate-300">{{ __('Total Décaissé (Salaires + Dépenses)') }}</span>
                    <span class="text-rose-600">{{ format_price($totalSorties, 'MAD') }}</span>
                </div>
                <div class="h-3 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                    <div class="h-full rounded-full bg-rose-500" style="width: {{ $pctSort }}%"></div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
