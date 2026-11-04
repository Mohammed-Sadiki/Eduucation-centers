@extends('layouts.app')

@section('title', __('Tableau de bord financier') . ' — ' . config('app.name'))

@section('content')
@php
    $monthsNames = $monthsNames ?? \App\Services\FinanceService::monthNames();
@endphp
<div class="space-y-6" x-data="{
    paymentModalOpen: false,
    salaryModalOpen: false,
    selectedStudentId: '',
    selectedFormationId: '',
    selectedTeacherId: '',
    paymentAmount: '',
    prefilledExpectedSalary: '',
    prefilledPaidSalary: '',
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

    openQuickPayment(studentId) {
        if (studentId) {
            this.selectedStudentId = String(studentId);
            this.onStudentChange();
        } else {
            this.selectedStudentId = '';
            this.selectedFormationId = '';
            this.paymentAmount = '';
        }
        this.paymentModalOpen = true;
    },

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
                <span>📊</span>
                @if(user_context()->isCenterAdmin())
                    <span>{{ __('Gestion financière · Vue centre') }}</span>
                @else
                    <span>{{ __('Gestion financière · Vue globale') }}</span>
                @endif
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Gestion financière') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                @if(user_context()->isCenterAdmin())
                    {{ __('Vue d\'ensemble des finances de votre centre.') }}
                @else
                    {{ __('Vue d\'ensemble des finances des centres de formation.') }}
                @endif
            </p>
        </div>

        {{-- Actions rapides --}}
        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button"
                    x-on:click="openQuickPayment('')"
                    class="finance-btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                <span>{{ __('Encaisser scolarité') }}</span>
            </button>
            <button type="button"
                    x-on:click="openTeacherSalary('', '', '')"
                    class="finance-btn-secondary">
                <x-icon name="banknotes" class="h-4 w-4 text-slate-500" />
                <span>{{ __('Régler salaire') }}</span>
            </button>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         BARRE DE FILTRES COMPACTE (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('super-admin.finance.dashboard') }}"
          class="rounded-xl border border-slate-200/90 bg-white p-3.5 shadow-[0_1px_3px_rgba(0,0,0,0.02)] dark:border-slate-800 dark:bg-slate-900">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5 items-center">
            {{-- Filtre Formation (métadonnée technique `domain` conservée en interne) --}}
            <div>
                <label for="filter-domain" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    {{ __('Formation') }}
                </label>
                <select name="domain" id="filter-domain" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="all" {{ $domain === 'all' ? 'selected' : '' }}>{{ __('Toutes les formations') }}</option>
                    @foreach($domains as $slug => $d)
                        <option value="{{ $slug }}" {{ $domain === $slug ? 'selected' : '' }}>{{ __(is_array($d) ? ($d['name'] ?? $slug) : $d) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre Centre : sélecteur réservé au Super Admin. Un Admin Centre
                 est figé sur SON centre (imposé côté serveur). --}}
            <div>
                <label for="filter-center" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    {{ __('Centre') }}
                </label>
                @if(user_context()->canSwitchCenter())
                    <select name="center_id" id="filter-center" onchange="this.form.submit()"
                            class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="" {{ empty($centerId) ? 'selected' : '' }}>{{ __('Tous les centres') }}</option>
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

            {{-- Filtre Année scolaire --}}
            <div>
                <label for="filter-school-year" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    {{ __('Année scolaire') }}
                </label>
                <select name="school_year" id="filter-school-year" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    @foreach($schoolYears as $sy)
                        <option value="{{ $sy }}" {{ $schoolYear === $sy ? 'selected' : '' }}>{{ $sy }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre Mois --}}
            <div>
                <label for="filter-month" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    {{ __('Mois salaires') }}
                </label>
                <select name="month" id="filter-month" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    @foreach($monthsNames as $mNum => $mLabel)
                        <option value="{{ $mNum }}" {{ $month === $mNum ? 'selected' : '' }}>{{ $mLabel }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre Année --}}
            <div>
                <label for="filter-year" class="block text-2xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    {{ __('Année civile') }}
                </label>
                <select name="year" id="filter-year" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    @foreach([2025, 2026, 2027, 2028] as $y)
                        <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    {{-- ────────────────────────────────────────────────────────────
         PREMIÈRE SECTION : LIGNE DE 4 CARTES KPI (EXACTEMENT CAPTURE 2 CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        {{-- Carte 1 : RECETTES (Total encaissements étudiants) --}}
        <div class="finance-card p-4.5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Recettes · Total encaissé') }}
                    </span>
                    <div class="mt-2 text-2xl lg:text-3xl font-extrabold text-[#0DAD98] whitespace-nowrap">
                        {{ format_price($kpis['total_encaisse'] ?? 0, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#0DAD98] dark:bg-[#0DAD98]/20 dark:text-[#0DAD98]">
                    <x-icon name="arrow-trending-up" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2.5">
                <span>{{ __('Taux de recouvrement') }}</span>
                <span class="font-bold text-[#0DAD98]">{{ $kpis['taux_recouvrement'] ?? 0 }}%</span>
            </div>
        </div>

        {{-- Carte 2 : DÉPENSES (Dépenses réelles + Salaires versés) --}}
        @php
            $depensesReelles = (float) \App\Models\Expense::when($centerId, fn($q) => $q->where('center_id', $centerId))->where('status', 'paid')->sum('amount');
            $salairesVerses  = (float) ($kpis['salaires_payes'] ?? 0);
            $totalDepensesEngagees = $depensesReelles + $salairesVerses;
        @endphp
        <div class="finance-card p-4.5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Dépenses') }}
                    </span>
                    <div class="mt-2 text-2xl lg:text-3xl font-extrabold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                        {{ format_price($totalDepensesEngagees, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                    <x-icon name="banknotes" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2.5">
                <span>{{ __('Salaires payés') }}</span>
                <span class="font-bold text-slate-700 dark:text-slate-300">{{ format_price($salairesVerses, 'MAD') }}</span>
            </div>
        </div>

        {{-- Carte 3 : TRÉSORERIE (Solde net de trésorerie réel) --}}
        @php
            $soldeTresorerie = (float) ($kpis['total_encaisse'] ?? 0) - $totalDepensesEngagees;
        @endphp
        <div class="finance-card p-4.5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Trésorerie') }}
                    </span>
                    <div class="mt-2 text-2xl lg:text-3xl font-extrabold {{ $soldeTresorerie >= 0 ? 'text-[#0DAD98]' : 'text-rose-600' }} whitespace-nowrap">
                        {{ format_price($soldeTresorerie, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#0DAD98] dark:bg-[#0DAD98]/20 dark:text-[#0DAD98]">
                    <x-icon name="building" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2.5">
                <span>{{ __('Salaires restant à payer') }}</span>
                <span class="font-bold text-amber-600">{{ format_price($kpis['salaires_restant'] ?? 0, 'MAD') }}</span>
            </div>
        </div>

        {{-- Carte 4 : IMPAYÉS (Total des scolarités restant à encaisser) --}}
        <div class="finance-card p-4.5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Impayés · Restant à encaisser') }}
                    </span>
                    <div class="mt-2 text-2xl lg:text-3xl font-extrabold text-amber-600 dark:text-amber-400 whitespace-nowrap">
                        {{ format_price($kpis['total_restant_etudiants'] ?? 0, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                    <x-icon name="clock" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2.5">
                <span>{{ __('Non payés :') }} {{ $kpis['etudiants_impayes'] ?? 0 }}</span>
                <span class="font-bold text-amber-600">{{ __('Partiels :') }} {{ $kpis['etudiants_partiels'] ?? 0 }}</span>
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         DEUXIÈME SECTION : RÉCAPITULATIF (STYLE BLOC BUDGET CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div class="finance-card p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-2xs font-bold uppercase tracking-wider text-slate-400">{{ __('Recouvrement des scolarités') }}</span>
                <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">
                    {{ __('Recouvrement des scolarités ·') }} {{ $schoolYear }}
                </h2>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <div>
                    <span class="text-slate-400">{{ __('Total attendu :') }}</span>
                    <span class="text-slate-900 dark:text-white font-bold ms-1 whitespace-nowrap">
                        {{ format_price($kpis['total_attendu'] ?? 0, 'MAD') }}
                    </span>
                </div>
                <div class="h-4 w-px bg-slate-200 dark:bg-slate-700"></div>
                <div>
                    <span class="text-slate-400">{{ __('Total encaissé :') }}</span>
                    <span class="text-[#0DAD98] font-bold ms-1 whitespace-nowrap">
                        {{ format_price($kpis['total_encaisse'] ?? 0, 'MAD') }}
                    </span>
                </div>
                <div class="h-4 w-px bg-slate-200 dark:bg-slate-700"></div>
                <a href="{{ route('super-admin.finance.students') }}" class="text-[#0DAD98] hover:underline font-bold">
                    {{ __('Voir les détails') }} &rarr;
                </a>
            </div>
        </div>

        {{-- Barre de progression fine style Cad Syndic --}}
        <div class="mt-5 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-slate-600 dark:text-slate-300">{{ __('Progression globale de l\'encaissement') }}</span>
                <span class="font-bold text-[#0DAD98]">{{ $kpis['taux_recouvrement'] ?? 0 }}{{ __('% recouvré') }}</span>
            </div>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div class="h-full rounded-full bg-[#0DAD98] transition-all duration-500 ease-out"
                     style="width: {{ min(100, max(0, $kpis['taux_recouvrement'] ?? 0)) }}%"></div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                <span>{{ format_price(0, 'MAD') }}</span>
                <span>{{ __('Restant à percevoir :') }} {{ format_price($kpis['total_restant_etudiants'] ?? 0, 'MAD') }}</span>
                <span>{{ format_price($kpis['total_attendu'] ?? 0, 'MAD') }}</span>
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         TROISIÈME SECTION : ALERTES FINANCIÈRES (STYLE CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    @php
        $activeAlertsCount = 0;
        $unpaidStudentsCount = $kpis['etudiants_impayes'] ?? 0;
        if ($unpaidStudentsCount > 0) $activeAlertsCount++;
        $unpaidSalariesAmount = (float) ($kpis['salaires_restant'] ?? 0);
        if ($unpaidSalariesAmount > 0) $activeAlertsCount++;
    @endphp
    <div class="finance-card p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 text-sm">
                    ⚠️
                </span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                        {{ __('Alertes financières') }}
                    </h3>
                    <p class="text-xs text-slate-500">{{ __('Points d\'attention nécessitant une action prioritaire') }}</p>
                </div>
            </div>
            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
                {{ $activeAlertsCount }} {{ __('alerte(s) active(s)') }}
            </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            {{-- Alerte 1 : Impayés scolarité --}}
            <div class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 dark:border-slate-800 dark:bg-slate-800/40">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-900/40">
                    <x-icon name="exclamation-circle" class="h-4 w-4" />
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Scolarités en retard') }}</h4>
                        <a href="{{ route('super-admin.finance.recovery') }}" class="text-xs font-semibold text-[#0DAD98] hover:underline">{{ __('Relancer') }} &rarr;</a>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $kpis['etudiants_impayes'] ?? 0 }} {{ __('étudiant(s) avec scolarité non réglée (créance :') }} {{ format_price($kpis['total_restant_etudiants'] ?? 0, 'MAD') }}).
                    </p>
                </div>
            </div>

            {{-- Alerte 2 : Salaires restant à régler --}}
            <div class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 dark:border-slate-800 dark:bg-slate-800/40">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40">
                    <x-icon name="clock" class="h-4 w-4" />
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Salaires professeurs en attente') }}</h4>
                        <a href="{{ route('super-admin.finance.teachers') }}" class="text-xs font-semibold text-[#0DAD98] hover:underline">{{ __('Régler') }} &rarr;</a>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ __('Reste à verser sur le mois :') }} <strong class="text-slate-800 dark:text-slate-200">{{ format_price($kpis['salaires_restant'] ?? 0, 'MAD') }}</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         QUATRIÈME SECTION : GRILLE DES 12 CARTES MENSUELLES (CAPTURE 2 CAD SYNDIC)
    ──────────────────────────────────────────────────────────── --}}
    <div>
        <div class="flex items-center justify-between mb-3.5">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ __('Périodes mensuelles · Année') }} {{ $year }}
                </h3>
                <p class="text-xs text-slate-500">{{ __('Solde net par mois (vert si positif, rouge si négatif)') }}</p>
            </div>
            <span class="text-xs text-slate-400">{{ __('12 mois civils') }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            @for($m = 1; $m <= 12; $m++)
                @php
                    $mName = $monthsNames[$m] ?? $m;
                    // Évolution ou données du mois
                    $matchingEvo = collect($kpis['monthly_evolution']['items'] ?? [])->firstWhere('label', substr($mName, 0, 3));
                    $mCollected = (float) ($matchingEvo['collected'] ?? 0);
                    $mSalaries  = (float) ($matchingEvo['salaries'] ?? 0);
                    $mNet = $mCollected - $mSalaries;
                    $isPos = $mNet >= 0;
                @endphp
                <div class="finance-card p-3.5 hover:border-slate-300 transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $mName }}</span>
                            <span class="rounded-md {{ $isPos ? 'bg-[#ECFDF5] text-[#0DAD98]' : 'bg-rose-50 text-rose-600' }} px-1.5 py-0.5 text-2xs font-bold">
                                {{ $isPos ? '+' : '-' }}
                            </span>
                        </div>
                        <div class="text-sm font-extrabold {{ $isPos ? 'text-[#0DAD98]' : 'text-rose-600' }} whitespace-nowrap">
                            {{ format_price($mNet, 'MAD') }}
                        </div>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-2xs text-slate-400">
                        <span>{{ __('Enc:') }} {{ format_number($mCollected, 0) }}</span>
                        <span>{{ __('Sal:') }} {{ format_number($mSalaries, 0) }}</span>
                    </div>
                </div>
            @endfor
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         MODAL 1 : ENREGISTRER UN VERSEMENT ÉTUDIANT (ALPINE.JS)
    ──────────────────────────────────────────────────────────── --}}
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
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Enregistrer un versement') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('Scolarité annuelle · Paiement partiel ou solde') }}</p>
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

    {{-- ────────────────────────────────────────────────────────────
         MODAL 2 : RÈGLEMENT DU SALAIRE PROFESSEUR (ALPINE.JS)
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
                        <p class="text-xs text-slate-500">{{ __('Mois de') }} {{ $monthsNames[$month] ?? $month }} {{ $year }}</p>
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
                            {{ __('Salaire mensuel prévu (DH)') }}
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
                        {{ __('Note / Réf de virement') }}
                    </label>
                    <input type="text" name="notes" placeholder="{{ __('Ex: Salaire Octobre 2026 - Virement émis') }}"
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
