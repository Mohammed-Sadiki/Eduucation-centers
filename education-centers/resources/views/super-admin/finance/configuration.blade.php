@extends('layouts.app')

@section('title', __('Configuration des prix & salaires') . ' — ' . config('app.name'))

@section('content')
<div class="space-y-6">

    {{-- ────────────────────────────────────────────────────────────
         EN-TÊTE DE LA PAGE (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-[#ECFDF5] px-2.5 py-0.5 text-xs font-semibold text-[#0DAD98] border border-[#0DAD98]/20 dark:bg-[#0DAD98]/15 dark:text-[#0DAD98] mb-1.5 shadow-2xs">
                <span>⚙️</span>
                <span>{{ __('Gestion financière · Paramètres tarifaires & conventions') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Configuration des prix & salaires') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Tarifs annuels de scolarité par formation et rémunérations mensuelles de base des enseignants.') }}
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

    @if(session('success'))
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200 shadow-2xs">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-[#0DAD98] text-white">
                <x-icon name="check" class="h-3.5 w-3.5" />
            </span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('super-admin.finance.configuration.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Section 1 : Formations et tarifs annuels --}}
        <div class="finance-card p-5">
            <div class="border-b border-slate-100 pb-3.5 dark:border-slate-800 mb-5 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#0DAD98]"></span>
                        {{ __('Tarifs annuels des formations (MAD / DH)') }}
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ __('Montant de référence attendu par dossier de scolarité') }}
                    </p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ $formations->count() }} {{ __('formation(s)') }}
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($formations as $i => $fm)
                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/50 p-3.5 dark:border-slate-800 dark:bg-slate-800/40">
                        <input type="hidden" name="formations[{{ $i }}][id]" value="{{ $fm->id }}">
                        <input type="hidden" name="formations[{{ $i }}][domain]" value="{{ $fm->domain }}">

                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-900 dark:text-white truncate" title="{{ $fm->name }}">
                                {{ $fm->name }}
                            </span>
                            <span class="rounded-md bg-white border border-slate-200 px-1.5 py-0.5 text-2xs font-semibold text-slate-600 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">
                                {{ $fm->center->name ?? __('Tous') }}
                            </span>
                        </div>

                        <div class="relative mt-2">
                            <input type="number" step="0.01" min="0"
                                   name="formations[{{ $i }}][annual_price]"
                                   value="{{ old('formations.'.$i.'.annual_price', $fm->annual_price ?? 12000) }}"
                                   required
                                   class="w-full rounded-lg border-slate-200 bg-white py-1.5 ps-3 pe-10 text-xs font-bold text-slate-900 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            <span class="absolute inset-y-0 end-3 flex items-center text-xs font-bold text-slate-400">
                                {{ currency_symbol('MAD') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Section 2 : Professeurs et salaires mensuels --}}
        <div class="finance-card p-5">
            <div class="border-b border-slate-100 pb-3.5 dark:border-slate-800 mb-5 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#0DAD98]"></span>
                        {{ __('Salaires mensuels contractuels des enseignants') }}
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ __('Base de calcul des décaissements mensuels par enseignant') }}
                    </p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ $teachers->count() }} {{ __('enseignant(s)') }}
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($teachers as $j => $tc)
                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/50 p-3.5 dark:border-slate-800 dark:bg-slate-800/40">
                        <input type="hidden" name="teachers[{{ $j }}][id]" value="{{ $tc->id }}">
                        <input type="hidden" name="teachers[{{ $j }}][domain]" value="{{ $tc->domain ?? 'general' }}">

                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                {{ $tc->full_name }}
                            </span>
                            <span class="rounded-md bg-white border border-slate-200 px-1.5 py-0.5 text-2xs font-semibold text-slate-600 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">
                                {{ $tc->center->name ?? __('Tous') }}
                            </span>
                        </div>

                        <div class="relative mt-2">
                            <input type="number" step="0.01" min="0"
                                   name="teachers[{{ $j }}][monthly_salary]"
                                   value="{{ old('teachers.'.$j.'.monthly_salary', $tc->monthly_salary ?? 6000) }}"
                                   required
                                   class="w-full rounded-lg border-slate-200 bg-white py-1.5 ps-3 pe-10 text-xs font-bold text-slate-900 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            <span class="absolute inset-y-0 end-3 flex items-center text-xs font-bold text-slate-400">
                                {{ currency_symbol('MAD') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Actions de soumission --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('super-admin.finance.dashboard') }}"
               class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">
                {{ __('Annuler') }}
            </a>
            <button type="submit" class="finance-btn-primary py-2.5 px-5">
                <x-icon name="check" class="h-4 w-4" />
                <span>{{ __('Enregistrer la nouvelle tarification') }}</span>
            </button>
        </div>
    </form>

</div>
@endsection
