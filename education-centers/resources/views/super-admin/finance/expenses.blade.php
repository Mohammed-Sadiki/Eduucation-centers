@extends('layouts.app')

@section('title', __('Dépenses & Charges') . ' — ' . config('app.name'))

@section('content')
<div class="space-y-6" x-data="{
    addModalOpen: false,
    historyOpen: {{ request()->hasAny(['history_month', 'history_year', 'history_page']) ? 'true' : 'false' }},
    expenseTitle: '',
    expenseAmount: '',
    isSubmitting: false,

    openAddModal() {
        this.expenseTitle = '';
        this.expenseAmount = '';
        this.addModalOpen = true;
    },

    closeAddModal() {
        this.addModalOpen = false;
        this.expenseTitle = '';
        this.expenseAmount = '';
    }
}">

    {{-- En-tête de la page --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div class="flex items-center gap-3.5 [direction:ltr]" dir="ltr">
            <x-ui.back-button :fallback="route('super-admin.finance.dashboard')" />
            <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                <div class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-600 border border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 mb-1.5 shadow-2xs">
                    <span>🧾</span>
                    <span>{{ __('Gestion financière · Dépenses & Charges') }}</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Dépenses & Charges') }}
                </h1>
                <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Suivi budgétaire du centre, enregistrement des dépenses et historique des charges réelles.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Messages de feedback --}}
    @if(session('success'))
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200 shadow-2xs">
            <x-icon name="check" class="h-4 w-4 text-[#0DAD98]" />
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-center gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs font-semibold text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200 shadow-2xs">
            <x-icon name="x-mark" class="h-4 w-4 text-rose-600" />
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs font-semibold text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200 shadow-2xs">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── 1. QUATRE CARTES STATISTIQUES EXISTANTES CONSERVÉES ────────────── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Budget annuel alloué --}}
        <div class="finance-card p-4.5">
            <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Budget alloué') }}</span>
            <div class="mt-2 text-2xl font-extrabold text-slate-900 dark:text-white whitespace-nowrap">
                {{ format_price($annualBudget, 'MAD') }}
            </div>
            <div class="mt-3 text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Plafond prévisionnel annuel') }}
            </div>
        </div>

        {{-- Dépenses & charges engagées (dépenses + salaires) --}}
        <div class="finance-card p-4.5">
            <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Charges engagées') }}</span>
            <div class="mt-2 text-2xl font-extrabold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                {{ format_price($totalExpenses, 'MAD') }}
            </div>
            <div class="mt-3 text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ $chargeRows->count() }} {{ __('charge(s) (achats + salaires)') }} ·
                <span class="text-emerald-600 dark:text-emerald-400">{{ __('versé :') }} {{ format_price($realizedTotal, 'MAD') }}</span> ·
                <span class="text-amber-600 dark:text-amber-400">{{ __('reste :') }} {{ format_price($dueTotal, 'MAD') }}</span>
            </div>
        </div>

        {{-- Budget restant (calculé automatiquement) --}}
        <div class="finance-card p-4.5">
            <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Budget restant') }}</span>
            <div class="mt-2 text-2xl font-extrabold text-[#0DAD98] whitespace-nowrap">
                {{ format_price($remainingBudget, 'MAD') }}
            </div>
            <div class="mt-3 text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Solde disponible déduit en temps réel') }}
            </div>
        </div>

        {{-- Taux de consommation --}}
        <div class="finance-card p-4.5">
            <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Taux de consommation') }}</span>
            <div class="mt-2 text-2xl font-extrabold text-slate-900 dark:text-white whitespace-nowrap">
                {{ $consumptionRate }}%
            </div>
            <div class="mt-3 text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Consommé sur budget initial') }}
            </div>
        </div>
    </div>

    {{-- ── 2. DEUX BOUTONS D'ACTION SOUS LES CARTES ───────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3 pt-2">
        {{-- Bouton 1 : + Ajouter (réservé au Super Admin) --}}
        @if(auth()->user()->isSuperAdmin())
            <button type="button"
                    x-on:click="openAddModal()"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-brand-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-500/20 active:scale-[0.99] dark:bg-brand-500 dark:hover:bg-brand-600">
                <x-icon name="plus" class="h-4 w-4" />
                <span>{{ __('+ Ajouter') }}</span>
            </button>
        @endif

        {{-- Bouton 2 : Historique des dépenses (Super Admin et Responsable Centre) --}}
        <button type="button"
                x-on:click="historyOpen = !historyOpen"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-2xs transition-all hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
            <x-icon name="clock" class="h-4 w-4 text-slate-500" />
            <span>{{ __('Historique des dépenses') }}</span>
            <svg class="h-4 w-4 transition-transform duration-200" :class="historyOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </div>

    {{-- ── 3. SECTION REPLIABLE : HISTORIQUE DES DÉPENSES ─────────────────── --}}
    <div x-show="historyOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="space-y-4">

        <div class="ec-card overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
            {{-- En-tête de l'historique avec filtre mois / année et bouton fermer --}}
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-800/40">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                        <x-icon name="document-text" class="h-4 w-4" />
                    </span>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">
                        {{ __('Historique réel des dépenses') }}
                    </h2>
                </div>

                {{-- Filtre simple mois & année + fermeture --}}
                <form method="GET" action="{{ route('super-admin.finance.expenses') }}" class="flex flex-wrap items-center gap-2">
                    @if(request('center_id'))
                        <input type="hidden" name="center_id" value="{{ request('center_id') }}">
                    @endif

                    {{-- Sélection Mois --}}
                    <select name="history_month" onchange="this.form.submit()"
                            class="rounded-lg border-slate-200 bg-white py-1 px-2.5 text-xs text-slate-700 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">{{ __('Tous les mois') }}</option>
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" @selected((string)request('history_month') === (string)$m)>
                                {{ \Carbon\Carbon::create(2026, $m, 1)->locale('fr')->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Sélection Année --}}
                    <select name="history_year" onchange="this.form.submit()"
                            class="rounded-lg border-slate-200 bg-white py-1 px-2.5 text-xs text-slate-700 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">{{ __('Toutes années') }}</option>
                        @foreach([2024, 2025, 2026, 2027] as $y)
                            <option value="{{ $y }}" @selected((string)request('history_year') === (string)$y)>
                                {{ $y }}
                            </option>
                        @endforeach
                    </select>

                    @if(request()->hasAny(['history_month', 'history_year']))
                        <a href="{{ route('super-admin.finance.expenses', array_filter(['center_id' => request('center_id')])) }}"
                           class="rounded-lg border border-slate-200 bg-white py-1 px-2 text-xs text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800"
                           title="{{ __('Réinitialiser les filtres') }}">
                            ✕
                        </a>
                    @endif

                    <button type="button"
                            x-on:click="historyOpen = false"
                            class="rounded-lg border border-slate-200 bg-white py-1 px-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        {{ __('Fermer') }}
                    </button>
                </form>
            </div>

            {{-- Tableau épuré : Date | Nom / Motif | Montant --}}
            <div class="overflow-x-auto">
                <table class="w-full text-start border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200/80 bg-slate-50/70 text-start text-2xs font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                            <th class="py-3 px-4 w-32">{{ __('Date') }}</th>
                            <th class="py-3 px-4">{{ __('Nom / Motif') }}</th>
                            <th class="py-3 px-4 text-end w-36">{{ __('Montant') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($expenseHistory as $exp)
                            <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="py-3 px-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {{ $exp->expense_date ? $exp->expense_date->format('d/m/Y') : '—' }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                    {{ $exp->title }}
                                </td>
                                <td class="py-3 px-4 text-end font-extrabold text-slate-900 dark:text-white whitespace-nowrap">
                                    {{ number_format($exp->amount, 2, ',', ' ') }} DH
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-8 text-center text-xs text-slate-400">
                                    {{ __('Aucune dépense enregistrée sur cette période.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination de l'historique --}}
            @if($expenseHistory->hasPages())
                <div class="p-3 border-t border-slate-100 dark:border-slate-800">
                    {{ $expenseHistory->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ── 4. MODALE SIMPLE D'AJOUT DE DÉPENSE (DEUX CHAMPS) ──────────────── --}}
    @if(auth()->user()->isSuperAdmin())
        <div x-show="addModalOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs">

            <div x-on:click.outside="closeAddModal()"
                 class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800">

                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            🧾
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Ajouter une dépense') }}</h3>
                            <p class="text-xs text-slate-400">{{ __('Enregistrement immédiat dans le centre actif') }}</p>
                        </div>
                    </div>
                    <button type="button" x-on:click="closeAddModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        ✕
                    </button>
                </div>

                <form method="POST" action="{{ route('super-admin.finance.expenses.store') }}" class="mt-4 space-y-4" x-on:submit="isSubmitting = true">
                    @csrf

                    @if(!empty($centerId))
                        <input type="hidden" name="center_id" value="{{ $centerId }}">
                    @endif

                    {{-- Champ 1 : Nom / Motif de la dépense --}}
                    <div>
                        <label for="modal_title" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Nom / Motif de la dépense') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="modal_title"
                               name="title"
                               x-model="expenseTitle"
                               required
                               placeholder="{{ __('Ex: Fournitures de bureau, Transport, etc.') }}"
                               class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    </div>

                    {{-- Champ 2 : Prix (DH) --}}
                    <div>
                        <label for="modal_amount" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Prix (DH)') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="number"
                               id="modal_amount"
                               name="amount"
                               x-model="expenseAmount"
                               step="0.01"
                               min="0.01"
                               required
                               placeholder="{{ __('Ex: 250') }}"
                               class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    </div>

                    {{-- Boutons d'action --}}
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                x-on:click="closeAddModal()"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {{ __('Annuler') }}
                        </button>
                        <button type="submit"
                                :disabled="isSubmitting"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600">
                            <x-icon name="check" class="h-3.5 w-3.5" />
                            <span>{{ __('Enregistrer') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
@endsection
