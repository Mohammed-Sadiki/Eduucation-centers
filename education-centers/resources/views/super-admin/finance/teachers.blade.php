@extends('layouts.app')

@section('title', __('Salaires professeurs') . ' — ' . config('app.name'))

@section('content')
@php
    $monthsNames = $monthsNames ?? \App\Services\FinanceService::monthNames();
@endphp
<div class="space-y-6" x-data="{
    salaryModalOpen: false,
    selectedTeacherId: '',
    prefilledExpectedSalary: '',
    prefilledPaidSalary: '',

    openTeacherSalary(teacherId, expectedSalary, paidSalary) {
        this.selectedTeacherId = teacherId ? String(teacherId) : '';
        this.prefilledExpectedSalary = expectedSalary || '';
        const remaining = Math.max(0, parseFloat(expectedSalary || 0) - parseFloat(paidSalary || 0));
        this.prefilledPaidSalary = remaining > 0 ? remaining : expectedSalary;
        this.salaryModalOpen = true;
    }
}">

    {{-- ────────────────────────────────────────────────────────────
         EN-TÊTE DE LA PAGE (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-[#ECFDF5] px-2.5 py-0.5 text-xs font-semibold text-[#0DAD98] border border-[#0DAD98]/20 dark:bg-[#0DAD98]/15 dark:text-[#0DAD98] mb-1.5 shadow-2xs">
                <span>🧑‍🏫</span>
                <span>{{ __('Gestion financière · Équipe pédagogique & Masse salariale') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Salaires professeurs') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Période active :') }} <strong class="text-slate-800 dark:text-slate-200">{{ $monthsNames[$month] ?? $month }} {{ $year }}</strong> · {{ $teacherRows->count() }} {{ __('enseignant(s)') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button"
                    x-on:click="openTeacherSalary('', '', '')"
                    class="finance-btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                <span>{{ __('Régler un salaire') }}</span>
            </button>
            <a href="{{ route('super-admin.finance.dashboard') }}"
               class="finance-btn-secondary">
                <x-icon name="arrow-left" class="h-4 w-4 text-slate-500" />
                <span>{{ __('Dashboard') }}</span>
            </a>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         4 CARTES KPI COMPACTES (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Salaires Attendus --}}
        <div class="finance-card p-4.5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Salaires attendus') }}</span>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900 dark:text-white whitespace-nowrap">
                        {{ format_price($stats['total_attendus'] ?? 0, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <x-icon name="user-group" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                <span>{{ __('Enseignants concernés') }}</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $stats['count_teachers'] ?? 0 }}</span>
            </div>
        </div>

        {{-- Salaires Payés --}}
        <div class="finance-card p-4.5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Salaires payés') }}</span>
                    <div class="mt-2 text-2xl font-extrabold text-[#0DAD98] whitespace-nowrap">
                        {{ format_price($stats['total_payes'] ?? 0, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#0DAD98] dark:bg-[#0DAD98]/20">
                    <x-icon name="check-circle" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                <span>{{ __('Taux de règlement') }}</span>
                <span class="font-bold text-[#0DAD98]">{{ $stats['rate_paid'] ?? 0 }}%</span>
            </div>
        </div>

        {{-- Restant à payer --}}
        <div class="finance-card p-4.5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Restant à régler') }}</span>
                    <div class="mt-2 text-2xl font-extrabold text-amber-600 dark:text-amber-400 whitespace-nowrap">
                        {{ format_price($stats['total_restant'] ?? 0, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40">
                    <x-icon name="clock" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                <span>{{ __('Reste sur la période') }}</span>
                <span class="font-bold text-amber-600">{{ $monthsNames[$month] ?? $month }}</span>
            </div>
        </div>

        {{-- Acquittement total --}}
        <div class="finance-card p-4.5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Soldés intégral') }}</span>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900 dark:text-white whitespace-nowrap">
                        {{ $stats['count_paid'] ?? 0 }} <span class="text-xs font-normal text-slate-400">/ {{ $stats['count_teachers'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#0DAD98] dark:bg-[#0DAD98]/20">
                    <x-icon name="document-check" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                <span>{{ __('Partiel:') }} {{ $stats['count_partial'] ?? 0 }}</span>
                <span>{{ __('Non payé:') }} {{ $stats['count_unpaid'] ?? 0 }}</span>
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         BARRE DE FILTRES COMPACTE (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('super-admin.finance.teachers') }}"
          class="rounded-xl border border-slate-200/90 bg-white p-3.5 shadow-[0_1px_3px_rgba(0,0,0,0.02)] dark:border-slate-800 dark:bg-slate-900">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 items-center">
            {{-- Recherche --}}
            <div class="col-span-2 sm:col-span-1">
                <label for="filter-search" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Recherche') }}
                </label>
                <input type="text" name="search" id="filter-search" value="{{ $search ?? '' }}"
                       placeholder="{{ __('Nom de l\'enseignant...') }}"
                       class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            </div>

            {{-- Mois --}}
            <div>
                <label for="filter-month" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Mois') }}
                </label>
                <select name="month" id="filter-month" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    @foreach($monthsNames as $mNum => $mLabel)
                        <option value="{{ $mNum }}" {{ $month === $mNum ? 'selected' : '' }}>{{ $mLabel }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Année --}}
            <div>
                <label for="filter-year" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Année') }}
                </label>
                <select name="year" id="filter-year" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    @foreach([2025, 2026, 2027, 2028] as $y)
                        <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Centre --}}
            <div>
                <label for="filter-cnt" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Centre') }}
                </label>
                <select name="center_id" id="filter-cnt" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="" {{ empty($centerId) ? 'selected' : '' }}>{{ __('Tous centres') }}</option>
                    @foreach($centers as $c)
                        <option value="{{ $c->id }}" {{ $centerId === $c->id ? 'selected' : '' }}>{{ $c->translated('name') }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Formation (métadonnée technique `domain` conservée en interne) --}}
            <div>
                <label for="filter-dom" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Formation') }}
                </label>
                <select name="domain" id="filter-dom" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="all" {{ $domain === 'all' ? 'selected' : '' }}>{{ __('Toutes les formations') }}</option>
                    @foreach($domains as $slug => $d)
                        <option value="{{ $slug }}" {{ $domain === $slug ? 'selected' : '' }}>{{ __(is_array($d) ? ($d['name'] ?? $slug) : $d) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Statut --}}
            <div>
                <label for="filter-st" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Statut') }}
                </label>
                <select name="status" id="filter-st" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('Tous statuts') }}</option>
                    <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>{{ __('Acquitté') }}</option>
                    <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>{{ __('Partiel') }}</option>
                    <option value="unpaid" {{ $status === 'unpaid' ? 'selected' : '' }}>{{ __('Non payé') }}</option>
                </select>
            </div>
        </div>
    </form>

    {{-- ────────────────────────────────────────────────────────────
         TABLEAU BLANC ÉPURÉ (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="finance-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs">
                <thead class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-800/50 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4">{{ __('Professeur') }}</th>
                        <th class="py-3 px-4">{{ __('Centre & Filières') }}</th>
                        <th class="py-3 px-4 text-end">{{ __('Salaire mensuel') }}</th>
                        <th class="py-3 px-4 text-end">{{ __('Montant versé') }}</th>
                        <th class="py-3 px-4 text-end">{{ __('Reste à payer') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Période') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Statut') }}</th>
                        <th class="py-3 px-4 text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($teacherRows as $row)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                    {{ $row->teacher->full_name }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ $row->teacher->email }}
                                </div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ ($row->center->name ?? 'N/A') }}
                                </div>
                                <div class="text-[10px] text-slate-400 truncate max-w-xs">
                                    {{ !empty($row->domains) ? implode(', ', $row->domains) : __('Général') }}
                                </div>
                            </td>
                            <td class="py-3 px-4 text-end font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                {{ format_price($row->monthly_salary, 'MAD') }}
                            </td>
                            <td class="py-3 px-4 text-end font-bold text-[#0DAD98] whitespace-nowrap">
                                {{ format_price($row->paid_amount, 'MAD') }}
                            </td>
                            <td class="py-3 px-4 text-end font-bold {{ $row->remaining_amount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }} whitespace-nowrap">
                                {{ format_price($row->remaining_amount, 'MAD') }}
                            </td>
                            <td class="py-3 px-4 text-center text-slate-500 whitespace-nowrap">
                                {{ $monthsNames[$month] ?? $month }} {{ $year }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if($row->status === 'paid')
                                    <span class="finance-badge-paid">{{ __('Payé') }}</span>
                                @elseif($row->status === 'partial')
                                    <span class="finance-badge-partial">{{ __('Partiel') }}</span>
                                @else
                                    <span class="finance-badge-unpaid">{{ __('Non payé') }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-end whitespace-nowrap">
                                @if($row->remaining_amount > 0)
                                    <button type="button"
                                            x-on:click="openTeacherSalary('{{ $row->teacher->id }}', '{{ $row->monthly_salary }}', '{{ $row->paid_amount }}')"
                                            class="finance-btn-primary py-1 px-2.5 text-[11px]">
                                        <span>{{ __('Régler') }}</span>
                                    </button>
                                @else
                                    <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">{{ __('Réglé ✓') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-xs text-slate-400">
                                {{ __('Aucun professeur trouvé pour cette période et ce centre.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         MODAL : RÈGLEMENT DU SALAIRE PROFESSEUR (ALPINE.JS)
    ──────────────────────────────────────────────────────────── --}}
    <div x-show="salaryModalOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs">

        <div x-on:click.outside="salaryModalOpen = false"
             class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900 dark:border dark:border-slate-800">

            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#ECFDF5] text-[#0DAD98]">
                        🧑‍🏫
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Règlement salaire professeur') }}</h3>
                        <p class="text-[11px] text-slate-500">{{ __('Mois de') }} {{ $monthsNames[$month] ?? $month }} {{ $year }}</p>
                    </div>
                </div>
                <button type="button" x-on:click="salaryModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <x-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            <form method="POST" action="{{ route('super-admin.finance.teachers.salary.store') }}" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="school_year" value="{{ $schoolYear }}">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Professeur') }} <span class="text-rose-500">*</span>
                    </label>
                    <select name="teacher_id" x-model="selectedTeacherId" required
                            class="w-full rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                        <option value="">{{ __('-- Sélectionner un enseignant --') }}</option>
                        @foreach($teachersList as $tch)
                            <option value="{{ $tch->id }}">
                                {{ $tch->full_name }} ({{ $tch->center->name ?? '' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Salaire prévu (DH)') }}
                        </label>
                        <input type="number" step="0.01" min="0" name="monthly_salary" x-model="prefilledExpectedSalary" placeholder="{{ __('Ex: 6000') }}"
                               class="w-full rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-900 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Montant versé (DH)') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" name="paid_amount" x-model="prefilledPaidSalary" required placeholder="{{ __('Ex: 6000') }}"
                               class="w-full rounded-lg border-slate-200 bg-white text-xs font-semibold text-slate-900 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Date de versement') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                               class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Mode de paiement') }}
                        </label>
                        <select name="payment_method"
                                class="w-full rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            <option value="bank_transfer">{{ __('Virement bancaire') }}</option>
                            <option value="check">{{ __('Chèque') }}</option>
                            <option value="cash">{{ __('Espèces') }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Note / Réf virement') }}
                    </label>
                    <input type="text" name="notes" placeholder="Ex: Salaire {{ $monthsNames[$month] ?? $month }} - Virement émis"
                           class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" x-on:click="salaryModalOpen = false"
                            class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">
                        {{ __('Annuler') }}
                    </button>
                    <button type="submit" class="finance-btn-primary">
                        {{ __('Confirmer le règlement') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
