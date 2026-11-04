@extends('layouts.app')

@section('title', __('Rapports financiers') . ' — ' . config('app.name'))

@section('content')
<div class="space-y-6">

    {{-- ────────────────────────────────────────────────────────────
         EN-TÊTE DE LA PAGE (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-[#ECFDF5] px-2.5 py-0.5 text-xs font-semibold text-[#0DAD98] border border-[#0DAD98]/20 dark:bg-[#0DAD98]/15 dark:text-[#0DAD98] mb-1.5 shadow-2xs">
                <span>📊</span>
                <span>{{ __('Gestion financière · Synthèses analytiques & Exports') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Rapports financiers') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Centre d\'exportation comptable, bilans annuels consolidés et synthèses d\'activité par centre et filière.') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('super-admin.finance.dashboard') }}"
               class="finance-btn-secondary">
                <x-icon name="arrow-left" class="h-4 w-4 text-slate-500" />
                <span>{{ __('Dashboard') }}</span>
            </a>
        </div>
    </div>

    {{-- Grille des modules de rapport --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

        <div class="finance-card p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#0DAD98] text-xl">
                        📑
                    </span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-2xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ __('Prévu v2') }}
                    </span>
                </div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Export comptable des encaissements') }}</h2>
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Extraction au format CSV et Excel de toutes les écritures d\'encaissement avec références, dates et ventilation par centre.') }}
                </p>
            </div>
            <div class="mt-5 pt-3.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span>{{ __('Format : CSV / XLSX') }}</span>
                <span class="font-semibold text-slate-500">{{ __('Prochain jalon') }}</span>
            </div>
        </div>

        <div class="finance-card p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 text-xl">
                        💼
                    </span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-2xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ __('Prévu v2') }}
                    </span>
                </div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('État récapitulatif de la paie') }}</h2>
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Bordereaux de virement mensuels pour chaque enseignant avec historique des paiements et émargements par filière.') }}
                </p>
            </div>
            <div class="mt-5 pt-3.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span>{{ __('Format : PDF / CSV') }}</span>
                <span class="font-semibold text-slate-500">{{ __('Prochain jalon') }}</span>
            </div>
        </div>

        <div class="finance-card p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 text-xl">
                        ⚖️
                    </span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-2xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ __('Prévu v2') }}
                    </span>
                </div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Synthèse annuelle des impayés') }}</h2>
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Rapport de recouvrement avec ventilation par tranche de retard et analyse du reste à recouvrer par filière.') }}
                </p>
            </div>
            <div class="mt-5 pt-3.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span>{{ __('Format : PDF analytique') }}</span>
                <span class="font-semibold text-slate-500">{{ __('Prochain jalon') }}</span>
            </div>
        </div>

    </div>

    {{-- Bannière d'information technique --}}
    <div class="finance-card p-8 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#ECFDF5] text-xl text-[#0DAD98]">
            📊
        </div>
        <h2 class="mt-3 text-base font-bold text-slate-900 dark:text-white">
            {{ __('Architecture de reporting financier prête pour extension') }}
        </h2>
        <p class="mx-auto mt-1.5 max-w-xl text-xs text-slate-500 leading-relaxed">
            {{ __('Toutes les données sources (transactions, scolarités, affectations pédagogiques et émargements) sont déjà agrégées et exploitables. Les générateurs d\'exports CSV/PDF seront intégrés après validation formelle des gabarits comptables.') }}
        </p>
        <div class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            <span class="h-2 w-2 rounded-full bg-[#0DAD98]"></span>
            {{ __('Données opérationnelles connectées') }}
        </div>
    </div>

</div>
@endsection
