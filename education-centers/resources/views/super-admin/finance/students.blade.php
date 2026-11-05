@extends('layouts.app')

@section('title', __('Paiements étudiants') . ' — ' . config('app.name'))

@section('content')
<div class="space-y-6" x-data="{
    paymentModalOpen: false,
    selectedStudentId: '',
    selectedFormationId: '',
    paymentAmount: '',
    studentFinancialData: {{ Js::from($studentFinancialMap ?? []) }},

    onStudentChange() {
        const student = this.studentFinancialData[this.selectedStudentId];
        if (student) {
            this.selectedFormationId = student.formation_id || '';
            this.paymentAmount = student.remaining_amount > 0 ? student.remaining_amount : '';
        } else {
            this.paymentAmount = '';
        }
    },

    openQuickPayment(studentId, formationId, remainingAmount) {
        if (studentId) {
            this.selectedStudentId = String(studentId);
            this.selectedFormationId = formationId ? String(formationId) : '';
            this.paymentAmount = remainingAmount > 0 ? remainingAmount : '';
        } else {
            this.selectedStudentId = '';
            this.selectedFormationId = '';
            this.paymentAmount = '';
        }
        this.paymentModalOpen = true;
    }
}">

    {{-- ────────────────────────────────────────────────────────────
         EN-TÊTE DE LA PAGE (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-[#ECFDF5] px-2.5 py-0.5 text-xs font-semibold text-[#0DAD98] border border-[#0DAD98]/20 dark:bg-[#0DAD98]/15 dark:text-[#0DAD98] mb-1.5 shadow-2xs">
                <span>🎓</span>
                <span>{{ __('Gestion financière · Scolarités & Versements') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Paiements étudiants') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Suivi des versements, échéances réelles et recouvrement par centre et filière.') }}
            </p>
        </div>

        @if(auth()->user()->isSuperAdmin())
        <div class="flex items-center gap-2.5">
            <button type="button"
                    x-on:click="openQuickPayment('', '', '')"
                    class="finance-btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                <span>{{ __('Encaisser un versement') }}</span>
            </button>
        </div>
        @endif
    </div>

    {{-- ────────────────────────────────────────────────────────────
         RÉPARTITION FINANCIÈRE 70/30 (DEUX GRANDES CARTES HORIZONTALES)
    ──────────────────────────────────────────────────────────── --}}
    <div class="grid gap-6 md:grid-cols-2">
        {{-- Carte 1 — Part du Super Admin --}}
        <div class="ec-card relative overflow-hidden rounded-2xl border border-purple-100 bg-linear-to-br from-white via-purple-50/20 to-purple-50/40 p-6 shadow-sm transition-all hover:shadow-md dark:border-purple-900/30 dark:from-slate-850 dark:via-purple-950/10 dark:to-slate-850">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1.5">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-purple-100/80 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-purple-500"></span>
                        {{ __('Part du Super Admin') }}
                    </span>
                    <h3 class="text-base font-semibold text-slate-600 dark:text-slate-300">
                        {{ __('Quote-part Super Admin (70% ou 30%)') }}
                    </h3>
                </div>
                <div class="flex h-13 w-13 shrink-0 items-center justify-center rounded-2xl bg-purple-600 text-white shadow-md shadow-purple-600/20 dark:bg-purple-500">
                    <x-icon name="building-office-2" class="h-6 w-6" />
                </div>
            </div>

            <div class="mt-5 flex items-baseline gap-2">
                <span class="text-3xl sm:text-4xl font-extrabold tracking-tight text-purple-950 dark:text-purple-100">
                    {{ format_price($revenueSplit['super_admin_share'] ?? 0, 'DH') }}
                </span>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-purple-100/70 pt-3 text-xs text-slate-500 dark:border-purple-900/30 dark:text-slate-400">
                <span class="inline-flex items-center gap-1">
                    <x-icon name="check-circle" class="h-3.5 w-3.5 text-purple-600 dark:text-purple-400" />
                    {{ __('Sur encaissements réels') }}
                </span>
                <span class="font-medium text-slate-700 dark:text-slate-300">
                    {{ __('Total encaissé :') }} <strong class="text-purple-700 dark:text-purple-300">{{ format_price($revenueSplit['total_collected'] ?? 0, 'DH') }}</strong>
                </span>
            </div>
        </div>

        {{-- Carte 2 — Part du Responsable Centre --}}
        <div class="ec-card relative overflow-hidden rounded-2xl border border-emerald-100 bg-linear-to-br from-white via-emerald-50/20 to-emerald-50/40 p-6 shadow-sm transition-all hover:shadow-md dark:border-emerald-900/30 dark:from-slate-850 dark:via-emerald-950/10 dark:to-slate-850">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1.5">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-100/80 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ __('Part du Responsable Centre') }}
                    </span>
                    <h3 class="text-base font-semibold text-slate-600 dark:text-slate-300">
                        {{ __('Quote-part Responsable Centre (70% ou 30%)') }}
                    </h3>
                </div>
                <div class="flex h-13 w-13 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20 dark:bg-emerald-500">
                    <x-icon name="user" class="h-6 w-6" />
                </div>
            </div>

            <div class="mt-5 flex items-baseline gap-2">
                <span class="text-3xl sm:text-4xl font-extrabold tracking-tight text-emerald-950 dark:text-emerald-100">
                    {{ format_price($revenueSplit['center_admin_share'] ?? 0, 'DH') }}
                </span>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-emerald-100/70 pt-3 text-xs text-slate-500 dark:border-emerald-900/30 dark:text-slate-400">
                <span class="inline-flex items-center gap-1">
                    <x-icon name="check-circle" class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                    {{ __('Sur encaissements réels') }}
                </span>
                <span class="font-medium text-slate-700 dark:text-slate-300">
                    {{ __('Périmètre :') }} <strong class="text-emerald-700 dark:text-emerald-300">{{ $centerId ? ($centers->firstWhere('id', $centerId)?->name ?? __('Centre sélectionné')) : __('Tous les centres') }}</strong>
                </span>
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         NAVIGATION PAR ONGLETS EN PILULES (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'monthly']) }}"
               class="rounded-lg px-4 py-2 text-xs font-semibold transition-all {{ ($viewMode ?? 'monthly') === 'monthly' ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                <span>{{ __('Versements par mois') }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'students']) }}"
               class="rounded-lg px-4 py-2 text-xs font-semibold transition-all {{ ($viewMode ?? '') === 'students' ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                <span>{{ __('Vue par étudiant') }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'unpaid']) }}"
               class="rounded-lg px-4 py-2 text-xs font-semibold transition-all {{ ($viewMode ?? '') === 'unpaid' ? 'bg-white text-rose-600 shadow-xs dark:bg-slate-900 dark:text-rose-400' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                <span>{{ __('Recouvrement & Impayés') }}</span>
            </a>
        </div>

        <span class="text-xs text-slate-500">
            {{ __('Affichage :') }} <strong class="text-slate-800 dark:text-slate-200">{{ $studentRows->count() }}</strong> {{ __('inscription(s)') }}
        </span>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         BARRE DE FILTRES COMPACTE ALIGNÉE
    ──────────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('super-admin.finance.students') }}"
          class="rounded-xl border border-slate-200/90 bg-white p-3.5 shadow-[0_1px_3px_rgba(0,0,0,0.02)] dark:border-slate-800 dark:bg-slate-900">
        <input type="hidden" name="view" value="{{ $viewMode ?? 'monthly' }}">

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 items-center">
            {{-- Recherche --}}
            <div class="col-span-2 sm:col-span-1">
                <label for="filter-search" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Recherche') }}
                </label>
                <input type="text" name="search" id="filter-search" value="{{ $search ?? '' }}"
                       placeholder="{{ __('Nom, matricule, réf...') }}"
                       class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            </div>

            {{-- Année scolaire --}}
            <div>
                <label for="filter-sy" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Année') }}
                </label>
                <select name="school_year" id="filter-sy" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    @foreach($schoolYears as $sy)
                        <option value="{{ $sy }}" {{ $schoolYear === $sy ? 'selected' : '' }}>{{ $sy }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Centre (sélecteur réservé au Super Admin) --}}
            <div>
                <label for="filter-cnt" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Centre') }}
                </label>
                @if(user_context()->canSwitchCenter())
                    <select name="center_id" id="filter-cnt" onchange="this.form.submit()"
                            class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="" {{ empty($centerId) ? 'selected' : '' }}>{{ __('Tous centres') }}</option>
                        @foreach($centers as $c)
                            <option value="{{ $c->id }}" {{ $centerId === $c->id ? 'selected' : '' }}>{{ $c->translated('name') }}</option>
                        @endforeach
                    </select>
                @else
                    <div class="w-full truncate rounded-lg border border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ auth()->user()->center?->translated('name') ?? auth()->user()->center?->name }}
                    </div>
                @endif
            </div>

            {{-- Domaine technique (métadonnée interne) : le parcours officiel
                 passe par le sélecteur « Formation » ci-dessous. --}}
            <div>
                <label for="filter-dom" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Famille technique') }}
                </label>
                <select name="domain" id="filter-dom" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="all" {{ $domain === 'all' ? 'selected' : '' }}>{{ __('Toutes familles') }}</option>
                    @foreach($domains as $slug => $d)
                        <option value="{{ $slug }}" {{ $domain === $slug ? 'selected' : '' }}>{{ __(is_array($d) ? ($d['name'] ?? $slug) : $d) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Formation --}}
            <div>
                <label for="filter-form" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Formation') }}
                </label>
                <select name="formation_id" id="filter-form" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="" {{ empty($formationId) ? 'selected' : '' }}>{{ __('Toutes formations') }}</option>
                    @foreach(($formationsList ?? $formations ?? []) as $f)
                        <option value="{{ $f->id }}" {{ $formationId === $f->id ? 'selected' : '' }}>{{ $f->translated('name') }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Statut --}}
            <div>
                <label for="filter-st" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                    {{ __('Statut') }}
                </label>
                <select name="status" id="filter-st" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('Tous statuts') }}</option>
                    <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>{{ __('Soldé') }}</option>
                    <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>{{ __('Partiel') }}</option>
                    <option value="unpaid" {{ $status === 'unpaid' ? 'selected' : '' }}>{{ __('Non payé') }}</option>
                </select>
            </div>
        </div>
    </form>

    {{-- ────────────────────────────────────────────────────────────
         VUE 1 : ACCORDÉONS MENSUELS (CAD SYNDIC PAIEMENTS)
    ──────────────────────────────────────────────────────────── --}}
    @if(($viewMode ?? 'monthly') === 'monthly')
        <div class="space-y-3.5">
            @forelse($monthlyGroups as $group)
                <div x-data="{ expanded: {{ $loop->first ? 'true' : 'false' }} }"
                     class="finance-card overflow-hidden transition-shadow hover:shadow-sm">

                    {{-- En-tête de l'accordéon --}}
                    <button type="button"
                            x-on:click="expanded = !expanded"
                            class="w-full p-4.5 flex items-center justify-between text-start hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                        <div class="flex items-center gap-3.5 min-w-0">
                            {{-- Badge du mois --}}
                            <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-[#ECFDF5] text-[#0DAD98] dark:bg-[#0DAD98]/15 dark:text-[#0DAD98]">
                                <span class="text-2xs font-extrabold uppercase leading-none">{{ Str::substr($group->period_label, 0, 4) }}</span>
                                <span class="text-xs font-black leading-none mt-1">{{ Str::after($group->period_label, ' ') }}</span>
                            </div>

                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ $group->period_label }}
                                </h3>
                                <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                                    <span>{{ $group->payments_count }} {{ __('versement(s)') }}</span>
                                    <span>·</span>
                                    <span>{{ $group->students_count }} {{ __('étudiant(s)') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-5">
                            <div class="text-end">
                                <span class="block text-2xs font-bold uppercase tracking-wider text-slate-400">{{ __('Encaissé ce mois') }}</span>
                                <span class="text-base sm:text-lg font-extrabold text-[#0DAD98] whitespace-nowrap">
                                    {{ format_price($group->total_collected, 'MAD') }}
                                </span>
                            </div>
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 transition-transform duration-200"
                                 :class="{ 'rotate-180': expanded }">
                                <x-icon name="chevron-down" class="h-4 w-4" />
                            </div>
                        </div>
                    </button>

                    {{-- Corps de l'accordéon : Tableau des versements du mois --}}
                    <div x-show="expanded" x-collapse>
                        <div class="border-t border-slate-100 dark:border-slate-800 overflow-x-auto">
                            <table class="w-full text-start text-xs">
                                <thead class="bg-slate-50/70 text-2xs font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-800/40">
                                    <tr>
                                        <th class="py-2.5 px-4">{{ __('Date') }}</th>
                                        <th class="py-2.5 px-4">{{ __('Réf Versement') }}</th>
                                        <th class="py-2.5 px-4">{{ __('Étudiant') }}</th>
                                        <th class="py-2.5 px-4">{{ __('Formation & Centre') }}</th>
                                        <th class="py-2.5 px-4">{{ __('Règlement') }}</th>
                                        <th class="py-2.5 px-4 text-end">{{ __('Montant') }}</th>
                                        <th class="py-2.5 px-4 text-center">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach($group->payments as $pay)
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                                {{ $pay->payment_date ? format_date($pay->payment_date, 'd/m/Y') : '—' }}
                                            </td>
                                            <td class="py-3 px-4 font-mono text-xs font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                                {{ $pay->reference ?? ('ENC-'.str_pad($pay->id, 5, '0', STR_PAD_LEFT)) }}
                                            </td>
                                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                                {{ $pay->student->full_name }}
                                            </td>
                                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                                {{ $pay->formation->general_formation_name }}
                                                <span class="text-slate-400 text-xs">({{ $pay->formation->center->name ?? '' }})</span>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300">
                                                    @if($pay->method === 'cash') {{ __('💵 Espèces') }}
                                                    @elseif($pay->method === 'bank_transfer') {{ __('🏦 Virement') }}
                                                    @elseif($pay->method === 'credit_card') {{ __('💳 Carte') }}
                                                    @else 📄 {{ ucfirst($pay->method) }}
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-end font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                                {{ format_price($pay->amount, 'MAD') }}
                                            </td>
                                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                                @if($pay->receipt_number)
                                                    <a href="{{ route('super-admin.finance.receipt.pdf', $pay->id) }}"
                                                       target="_blank"
                                                       class="inline-flex items-center gap-1 rounded-md bg-slate-100 hover:bg-slate-200 px-2 py-1 text-2xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        <span>{{ __('Reçu #') }}{{ $pay->receipt_number }}</span>
                                                    </a>
                                                @else
                                                    <span class="text-slate-400 text-xs">{{ __('Validé') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="finance-card p-12 text-center text-slate-400">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400 dark:bg-slate-800">
                        📁
                    </div>
                    <p class="mt-3 text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Aucun versement enregistré pour cette sélection.') }}</p>
                </div>
            @endforelse
        </div>

    {{-- ────────────────────────────────────────────────────────────
         VUE 2 : TABLEAU PAR ÉTUDIANT (AVEC HISTORIQUE DÉPLIABLE)
    ──────────────────────────────────────────────────────────── --}}
    @elseif(($viewMode ?? '') === 'students')
        <div class="finance-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-xs">
                    <thead class="bg-slate-50/80 border-b border-slate-100 text-2xs font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-800/50 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4">{{ __('Étudiant') }}</th>
                            <th class="py-3 px-4">{{ __('Formation & Centre') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Prix annuel') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Total payé') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Reste à payer') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('Progression') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('Statut') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($studentRows as $row)
                            <tr x-data="{ openHistory: false }" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                        {{ $row->student->full_name }}
                                    </div>
                                    <div class="text-2xs text-slate-400 font-mono">
                                        {{ $row->student->student_number ?? ('ID: '.$row->student->id) }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="font-medium text-slate-800 dark:text-slate-200">
                                        {{ $row->formation->general_formation_name }}
                                    </div>
                                    <div class="text-2xs text-slate-400">
                                        {{ $row->formation->center->name ?? '' }} · {{ $row->enrollment->school_year }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-end font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                    {{ format_price($row->annual_price, 'MAD') }}
                                </td>
                                <td class="py-3 px-4 text-end font-bold text-[#0DAD98] whitespace-nowrap">
                                    {{ format_price($row->paid_amount, 'MAD') }}
                                </td>
                                <td class="py-3 px-4 text-end font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                    {{ format_price($row->remaining_amount, 'MAD') }}
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <div class="w-20 mx-auto">
                                        <div class="flex items-center justify-between text-2xs text-slate-400 mb-1">
                                            <span>{{ $row->payment_percentage ?? 0 }}%</span>
                                        </div>
                                        <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden dark:bg-slate-800">
                                            <div class="h-full bg-[#0DAD98] rounded-full" style="width: {{ min(100, $row->payment_percentage ?? 0) }}%"></div>
                                        </div>
                                    </div>
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
                                    @if(auth()->user()->isSuperAdmin())
                                        @if($row->remaining_amount > 0)
                                            <button type="button"
                                                    x-on:click="openQuickPayment('{{ $row->student->id }}', '{{ $row->formation->id }}', '{{ $row->remaining_amount }}')"
                                                    class="finance-btn-primary py-1 px-2.5 text-xs">
                                                <span>{{ __('Encaisser') }}</span>
                                            </button>
                                        @else
                                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ __('Soldé ✓') }}</span>
                                        @endif
                                    @else
                                        @if($row->remaining_amount > 0)
                                            <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">{{ __('En attente') }}</span>
                                        @else
                                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ __('Soldé ✓') }}</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-10 text-center text-xs text-slate-400">
                                    {{ __('Aucun dossier d\'étudiant trouvé avec ces critères.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ────────────────────────────────────────────────────────────
         VUE 3 : RECOUVREMENT & IMPAYÉS (STYLE RECOUVREMENT CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    @elseif(($viewMode ?? '') === 'unpaid')
        <div class="finance-card overflow-hidden">
            <div class="p-4 bg-rose-50/50 dark:bg-rose-950/20 border-b border-rose-100 dark:border-rose-900/40 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold text-rose-900 dark:text-rose-200">
                        {{ __('Priorité de recouvrement des créances scolarité') }}
                    </h3>
                    <p class="text-xs text-rose-700/80 dark:text-rose-300/80">
                        {{ __('Liste ordonnée des étudiants ayant un reliquat supérieur à 0 DH.') }}
                    </p>
                </div>
                <span class="text-xs font-extrabold text-rose-700 dark:text-rose-300">
                    {{ __('Total créances :') }} {{ format_price($studentRows->where('remaining_amount', '>', 0)->sum('remaining_amount'), 'MAD') }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-start text-xs">
                    <thead class="bg-slate-50 text-2xs font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-800">
                        <tr>
                            <th class="py-3 px-4">{{ __('Étudiant débiteur') }}</th>
                            <th class="py-3 px-4">{{ __('Formation & Centre') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Total attendu') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Total encaissé') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Reste à payer') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('Dernier versement') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($studentRows->where('remaining_amount', '>', 0)->sortByDesc('remaining_amount') as $debtor)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                        {{ $debtor->student->full_name }}
                                    </div>
                                    <div class="text-2xs text-slate-400 font-mono">
                                        {{ $debtor->student->student_number ?? ('ID: '.$debtor->student->id) }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ $debtor->formation->general_formation_name }}</div>
                                    <div class="text-2xs text-slate-400">{{ $debtor->formation->center->name ?? '' }}</div>
                                </td>
                                <td class="py-3 px-4 text-end text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {{ format_price($debtor->annual_price, 'MAD') }}
                                </td>
                                <td class="py-3 px-4 text-end font-bold text-[#0DAD98] whitespace-nowrap">
                                    {{ format_price($debtor->paid_amount, 'MAD') }}
                                </td>
                                <td class="py-3 px-4 text-end font-black text-rose-600 dark:text-rose-400 whitespace-nowrap">
                                    {{ format_price($debtor->remaining_amount, 'MAD') }}
                                </td>
                                <td class="py-3 px-4 text-center text-slate-500 whitespace-nowrap">
                                    {{ $debtor->last_payment?->payment_date ? format_date($debtor->last_payment->payment_date, 'd/m/Y') : __('Aucun versement') }}
                                </td>
                                <td class="py-3 px-4 text-end whitespace-nowrap">
                                    @if(auth()->user()->isSuperAdmin())
                                        <button type="button"
                                                x-on:click="openQuickPayment('{{ $debtor->student->id }}', '{{ $debtor->formation->id }}', '{{ $debtor->remaining_amount }}')"
                                                class="finance-btn-primary py-1 px-2.5 text-xs">
                                            <span>{{ __('Encaisser créance') }}</span>
                                        </button>
                                    @else
                                        <span class="text-xs font-semibold text-rose-600 dark:text-rose-400">{{ __('Impayé') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-xs text-slate-400">
                                    {{ __('Félicitations, aucun impayé enregistré sur cette sélection.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ────────────────────────────────────────────────────────────
         MODAL D'ENCAISSEMENT RAPIDE (RÉSERVÉ AU SUPER ADMIN)
    ──────────────────────────────────────────────────────────── --}}
    @if(auth()->user()->isSuperAdmin())
    <div x-show="paymentModalOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs">

        <div x-on:click.outside="paymentModalOpen = false"
             class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900 dark:border dark:border-slate-800">

            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#ECFDF5] text-[#0DAD98]">
                        💰
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Encaisser un versement') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('Scolarité annuelle · Frais de scolarité') }}</p>
                    </div>
                </div>
                <button type="button" x-on:click="paymentModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <x-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            <form method="POST" action="{{ route('super-admin.finance.students.payment.store') }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Étudiant') }} <span class="text-rose-500">*</span>
                    </label>
                    <select name="student_id" x-model="selectedStudentId" x-on:change="onStudentChange()" required
                            class="w-full rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                        <option value="">{{ __('-- Sélectionner un étudiant --') }}</option>
                        @foreach($studentsList as $st)
                            <option value="{{ $st->id }}">
                                {{ $st->full_name }} ({{ $st->student_number ?? 'ID: '.$st->id }}) — {{ $st->center->name ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Formation') }} <span class="text-rose-500">*</span>
                        </label>
                        <select name="formation_id" x-model="selectedFormationId" required
                                class="w-full rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            <option value="">{{ __('-- Formation --') }}</option>
                            @foreach($formationsList as $fm)
                                <option value="{{ $fm->id }}">{{ $fm->name }} ({{ $fm->center->name ?? '' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Montant versé (DH)') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="amount" step="0.01" min="1" x-model="paymentAmount" required placeholder="{{ __('Ex: 5000') }}"
                               class="w-full rounded-lg border-slate-200 bg-white text-xs font-semibold text-slate-900 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Date du versement') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                               class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Moyen de règlement') }} <span class="text-rose-500">*</span>
                        </label>
                        <select name="payment_method" required
                                class="w-full rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            <option value="cash">{{ __('Espèces') }}</option>
                            <option value="bank_transfer">{{ __('Virement bancaire') }}</option>
                            <option value="credit_card">{{ __('Carte bancaire') }}</option>
                            <option value="check">{{ __('Chèque') }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Référence / Notes (facultatif)') }}
                    </label>
                    <input type="text" name="reference" placeholder="{{ __('Ex: VIR-2026-OCT-09') }}"
                           class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" x-on:click="paymentModalOpen = false"
                            class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">
                        {{ __('Annuler') }}
                    </button>
                    <button type="submit" class="finance-btn-primary">
                        {{ __('Valider l\'encaissement') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
