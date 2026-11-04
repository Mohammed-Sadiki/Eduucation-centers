@extends('layouts.app')

@section('title', __('Dépenses & Achats') . ' — ' . config('app.name'))

@section('content')
<div class="space-y-6" x-data="{
    expenseModalOpen: false,
    detailModalOpen: false,
    activeExpense: null,
    salaryCategoryId: {{ $salaryCategoryId }},
    categoryId: {{ $categoryId ?: ($categories->first()->id ?? 'null') }},
    get isSalary() { return parseInt(this.categoryId) === this.salaryCategoryId; },
    items: [
        { name: '', quantity: 1, unit_price: '' }
    ],

    addItem() {
        this.items.push({ name: '', quantity: 1, unit_price: '' });
    },

    removeItem(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        } else {
            this.items[0] = { name: '', quantity: 1, unit_price: '' };
        }
    },

    getTotal() {
        return this.items.reduce((sum, item) => {
            const q = parseFloat(item.quantity) || 0;
            const p = parseFloat(item.unit_price) || 0;
            return sum + (q * p);
        }, 0);
    },

    openDetail(exp) {
        this.activeExpense = exp;
        this.detailModalOpen = true;
    }
}">

    {{-- En-tête de la page --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-600 border border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 mb-1.5 shadow-2xs">
                <span>🧾</span>
                <span>{{ __('Gestion financière · Achats, Prestations & Charges d\'exploitation') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Dépenses & Charges') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Enregistrement réel des achats, déduction automatique sur le budget alloué et gestion des justificatifs.') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button" x-on:click="expenseModalOpen = true" class="finance-btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                <span>{{ __('+ Ajouter une dépense') }}</span>
            </button>
            <a href="{{ route('super-admin.finance.dashboard') }}" class="finance-btn-secondary">
                <x-icon name="arrow-left" class="h-4 w-4 text-slate-500" />
                <span>{{ __('Dashboard') }}</span>
            </a>
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

    {{-- Quatre KPI Cad Syndic avec déduction automatique --}}
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

    {{-- Filtres compacts --}}
    <form method="GET" action="{{ route('super-admin.finance.expenses') }}"
          class="rounded-xl border border-slate-200/90 bg-white p-3.5 shadow-[0_1px_3px_rgba(0,0,0,0.02)] dark:border-slate-800 dark:bg-slate-900">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 items-center">
            <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Recherche') }}</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('Libellé, fournisseur, réf...') }}"
                       class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
            </div>
            <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Centre') }}</label>
                @if(user_context()->canSwitchCenter())
                    <select name="center_id" onchange="this.form.submit()"
                            class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
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
            <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Catégorie') }}</label>
                <select name="category_id" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
                    <option value="" {{ empty($categoryId) ? 'selected' : '' }}>{{ __('Toutes catégories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $categoryId === $cat->id ? 'selected' : '' }}>{{ $cat->translated('name') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Année') }}</label>
                <select name="school_year" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
                    @foreach($schoolYears as $sy)
                        <option value="{{ $sy }}" {{ $schoolYear === $sy ? 'selected' : '' }}>{{ $sy }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    {{-- Accordéons Mensuels Cad Syndic (dépenses + salaires unifiés) --}}
    <div class="space-y-3.5">
        @forelse($groupedExpenses as $gKey => $group)
            <div x-data="{ open: true }" class="finance-card overflow-hidden transition-all">
                {{-- En-tête accordéon --}}
                <div x-on:click="open = !open"
                     class="flex cursor-pointer items-center justify-between p-4 bg-slate-50/50 hover:bg-slate-50 dark:bg-slate-800/40 dark:hover:bg-slate-800/60 border-b border-slate-100 dark:border-slate-800 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-12 items-center justify-center rounded-lg bg-[#ECFDF5] text-xs font-bold text-[#0DAD98] tracking-wider uppercase">
                            {{ $group->badge }}
                        </span>
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">
                                {{ $group->period_label }}
                            </h3>
                            <p class="text-xs text-slate-400">
                                {{ $group->expenses_count }} {{ __('charge(s) enregistrée(s)') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="text-end">
                            <span class="text-xs sm:text-sm font-extrabold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                                {{ format_price($group->total_amount, 'MAD') }}
                            </span>
                            <span class="block text-2xs text-slate-400">{{ __('Total mois') }}</span>
                        </div>
                        <span class="text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }">
                            ▼
                        </span>
                    </div>
                </div>

                {{-- Tableau à l'ouverture --}}
                <div x-show="open" class="overflow-x-auto">
                    <table class="w-full text-start text-xs">
                        <thead class="bg-slate-50/80 text-2xs uppercase font-bold text-slate-400 border-b border-slate-100 dark:bg-slate-800/50 dark:border-slate-800">
                            <tr>
                                <th class="py-2.5 px-4">{{ __('Date') }}</th>
                                <th class="py-2.5 px-4">{{ __('Nature') }}</th>
                                <th class="py-2.5 px-4">{{ __('Catégorie') }}</th>
                                <th class="py-2.5 px-4">{{ __('Mode') }}</th>
                                <th class="py-2.5 px-4 text-center">{{ __('Statut') }}</th>
                                <th class="py-2.5 px-4 text-end">{{ __('Montant') }}</th>
                                <th class="py-2.5 px-4 text-center">{{ __('Justificatif') }}</th>
                                <th class="py-2.5 px-4 text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($group->items as $row)
                                @php $isCancelled = $row->status === 'cancelled'; @endphp
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 {{ $isCancelled ? 'opacity-50 line-through' : '' }}">
                                    <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                        {{ $row->date ? format_date($row->date, 'd/m/Y') : '—' }}
                                        @if($row->is_salary && ! empty($row->period_label))
                                            <span class="block text-2xs text-slate-400">{{ $row->period_label }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5">
                                            @if($row->is_salary)
                                                <span class="text-[#8B5CF6]">👤</span>
                                            @endif
                                            {{ $row->nature }}
                                        </span>
                                        @if($row->party && ! $row->is_salary)
                                            <span class="text-[#0DAD98] text-2xs font-medium block">🏢 {{ $row->party }}</span>
                                        @endif
                                        @if($row->reference)
                                            <span class="text-slate-400 text-2xs font-normal block font-mono">{{ __('Réf:') }} {{ $row->reference }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full {{ $row->is_salary ? 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} px-2 py-0.5 text-2xs font-semibold">
                                            {{ $row->category_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-600">
                                        @if($row->method === 'cash') {{ __('💵 Espèces') }}
                                        @elseif($row->method === 'check') {{ __('📝 Chèque') }}
                                        @elseif($row->method === 'bank_transfer') {{ __('🏦 Virement') }}
                                        @elseif($row->method === 'credit_card') {{ __('💳 Carte bancaire') }}
                                        @elseif($row->method) {{ $row->method }}
                                        @else —
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        @php
                                            $badge = match ($row->status) {
                                                'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300',
                                                'partial' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
                                                'unpaid', 'pending' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300',
                                                'cancelled' => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                                                default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-bold {{ $badge }}">
                                            {{ $row->status_label }}
                                        </span>
                                        @if($row->is_salary && $row->due_amount > 0)
                                            <span class="block text-2xs text-slate-400 no-underline">{{ __('reste :') }} {{ format_price($row->due_amount, 'MAD') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-end font-bold {{ $isCancelled ? 'text-slate-400' : 'text-rose-600' }} whitespace-nowrap">
                                        {{ format_price($row->amount, 'MAD') }}
                                        @if($isCancelled)
                                            <span class="block text-2xs text-rose-500 font-bold uppercase no-underline">{{ __('Annulé') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        @if($row->type === 'expense')
                                            @if($row->has_receipt)
                                                <a href="{{ route('super-admin.finance.expenses.receipt', $row->id) }}" target="_blank"
                                                   class="inline-flex items-center gap-1 text-xs font-bold text-[#0DAD98] hover:underline">
                                                    <span>{{ __('📎 Reçu') }}</span>
                                                </a>
                                            @endif
                                            <a href="{{ route('super-admin.finance.expenses.receipt-pdf', $row->id) }}"
                                               class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:underline">
                                                <span>{{ __('🧾 Reçu PDF') }}</span>
                                            </a>
                                            <a href="{{ route('super-admin.finance.expenses.receipt-pdf', ['expense' => $row->id, 'mode' => 'inline']) }}"
                                               target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:underline">
                                                <span>{{ __('🖨️ Imprimer') }}</span>
                                            </a>
                                        @else
                                            <span class="text-slate-300 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-end whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if($row->type === 'expense')
                                                <button type="button" x-on:click="openDetail({{ Js::from($row->model) }})"
                                                        class="rounded-md bg-slate-100 hover:bg-slate-200 px-2 py-1 text-2xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                    {{ __('Détails') }}
                                                </button>
                                                @if($row->can_cancel)
                                                    <form method="POST" action="{{ route('super-admin.finance.expenses.cancel', $row->id) }}" onsubmit="return confirm('{{ __('Confirmer l\'annulation de cette dépense ? Son montant sera réintégré au budget.') }}');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="rounded-md bg-amber-50 hover:bg-amber-100 px-2 py-1 text-2xs font-semibold text-amber-700">
                                                            {{ __('Annuler') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <span class="text-2xs text-slate-400">{{ __('Géré via Dépenses') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    {{-- Pied de tableau avec totaux par mode (Cad Syndic) --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/40 p-3.5 border-t border-slate-100 dark:border-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300">
                        <div class="flex items-center gap-4">
                            <span>{{ __('Espèces :') }} <strong class="text-slate-900 dark:text-white">{{ format_price($group->cash_total, 'MAD') }}</strong></span>
                            <span>{{ __('Chèques :') }} <strong class="text-slate-900 dark:text-white">{{ format_price($group->check_total, 'MAD') }}</strong></span>
                            <span>{{ __('Virements :') }} <strong class="text-slate-900 dark:text-white">{{ format_price($group->transfer_total, 'MAD') }}</strong></span>
                        </div>
                        <div class="text-rose-600 font-extrabold text-xs">
                            {{ __('Total mois :') }} {{ format_price($group->total_amount, 'MAD') }}
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="finance-card p-12 text-center text-slate-400">
                <p class="text-xs font-semibold">{{ __('Aucune dépense enregistrée sur cette sélection.') }}</p>
            </div>
        @endforelse
    </div>

    {{-- MODALE COMPLÈTE AJOUTER UNE DÉPENSE / ACHAT MULTI-ARTICLES (STYLE CAD SYNDIC) --}}
    <div x-show="expenseModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs overflow-y-auto">
        <div x-on:click.outside="expenseModalOpen = false"
             class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800 my-8">

            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 text-lg font-bold">
                        🛒
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Enregistrer une dépense ou un achat') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('Ajout multi-articles, calcul automatique et déduction budgétaire') }}</p>
                    </div>
                </div>
                <button type="button" x-on:click="expenseModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form method="POST" action="{{ route('super-admin.finance.expenses.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="school_year" value="{{ $schoolYear }}">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {{-- Catégorie (détermine le mode dépense / salaire) --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Catégorie') }} <span class="text-rose-500">*</span>
                        </label>
                        <select name="category_id" x-model="categoryId" required class="w-full rounded-lg border-slate-200 text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->translated('name') }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Centre concerné --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Centre concerné') }} <span class="text-rose-500">*</span>
                        </label>
                        @if(user_context()->canSwitchCenter())
                            <select name="center_id" required class="w-full rounded-lg border-slate-200 text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                                @foreach($centers as $c)
                                    <option value="{{ $c->id }}" {{ $centerId === $c->id ? 'selected' : '' }}>{{ $c->translated('name') }}</option>
                                @endforeach
                            </select>
                        @else
                            {{-- Admin Centre : centre forcé sur le sien (champ caché + affichage). --}}
                            <input type="hidden" name="center_id" value="{{ auth()->user()->center_id }}">
                            <div class="w-full truncate rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ auth()->user()->center?->translated('name') ?? auth()->user()->center?->name }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ─────────── MODE DÉPENSE CLASSIQUE ─────────── --}}
                <template x-if="!isSalary">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Titre de la dépense') }} <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="title" required placeholder="{{ __('Ex: Achat de fournitures scolaires') }}"
                                       class="w-full rounded-lg border-slate-200 text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Fournisseur (facultatif)') }}
                                </label>
                                <input type="text" name="supplier" placeholder="{{ __('Ex: Librairie Al Maaref, HP Maroc...') }}"
                                       class="w-full rounded-lg border-slate-200 text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Date d\'achat') }} <span class="text-rose-500">*</span>
                                </label>
                                <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" required
                                       class="w-full rounded-lg border-slate-200 text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Mode de paiement') }} <span class="text-rose-500">*</span>
                                </label>
                                <select name="payment_method" required class="w-full rounded-lg border-slate-200 text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                                    <option value="bank_transfer">{{ __('Virement bancaire') }}</option>
                                    <option value="check">{{ __('Chèque') }}</option>
                                    <option value="cash">{{ __('Espèces') }}</option>
                                    <option value="credit_card">{{ __('Carte bancaire') }}</option>
                                </select>
                            </div>
                        </div>

                        {{-- SECTION ARTICLES ACHETÉS (MULTI-LIGNES AVEC CALCUL AUTO) --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">
                                    {{ __('Articles / Produits achetés') }}
                                </span>
                                <button type="button" x-on:click="addItem()" class="inline-flex items-center gap-1 rounded-md bg-[#ECFDF5] px-2.5 py-1 text-xs font-bold text-[#0DAD98] hover:bg-[#0DAD98]/20 transition-colors">
                                    <span>{{ __('+ Ajouter un article') }}</span>
                                </button>
                            </div>

                            <div class="space-y-2">
                                <template x-for="(item, index) in items" :key="index">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1">
                                            <input type="text" :name="'items['+index+'][name]'" x-model="item.name" required
                                                   placeholder="{{ __('Ex: Feutres, Papier A4...') }}"
                                                   class="w-full rounded-lg border-slate-200 bg-white py-1.5 px-2.5 text-xs text-slate-800">
                                        </div>
                                        <div class="w-24">
                                            <input type="number" min="1" step="1" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" required
                                                   placeholder="{{ __('Qté') }}"
                                                   class="w-full rounded-lg border-slate-200 bg-white py-1.5 px-2.5 text-xs text-slate-800 text-center">
                                        </div>
                                        <div class="w-32">
                                            <div class="relative">
                                                <input type="number" min="0" step="0.01" :name="'items['+index+'][unit_price]'" x-model.number="item.unit_price" required
                                                       placeholder="{{ __('Prix unit.') }}"
                                                       class="w-full rounded-lg border-slate-200 bg-white py-1.5 ps-2.5 pe-8 text-xs font-semibold text-slate-900">
                                                <span class="absolute inset-y-0 end-2 flex items-center text-2xs text-slate-400 font-bold">DH</span>
                                            </div>
                                        </div>
                                        <div class="w-28 text-end font-extrabold text-xs text-slate-900 dark:text-white">
                                            <span x-text="((item.quantity || 0) * (item.unit_price || 0)).toFixed(2)"></span> {{ currency_symbol('MAD') }}
                                        </div>
                                        <button type="button" x-on:click="removeItem(index)" class="text-slate-400 hover:text-rose-500 px-1 text-sm font-bold">
                                            ✕
                                        </button>
                                    </div>
                                </template>
                            </div>

                            {{-- Montant libre si pas d'articles + résumé calculé --}}
                            <div class="mt-4 pt-3 border-t border-slate-200 flex items-center justify-between text-xs font-bold text-slate-800 dark:text-white">
                                <span>{{ __('Montant total calculé :') }}</span>
                                <div class="text-base text-rose-600 font-black">
                                    <span x-text="getTotal().toFixed(2)"></span> MAD
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="block text-xs font-semibold text-slate-500 mb-1">{{ __('Ou montant global (sans articles)') }}</label>
                                <input type="number" min="0" step="0.01" name="amount" placeholder="{{ __('Ex: 1200') }}"
                                       class="w-full rounded-lg border-slate-200 bg-white py-1.5 px-2.5 text-xs text-slate-800">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Justificatif (PDF, Image)') }}
                                </label>
                                <input type="file" name="receipt" accept=".pdf,.png,.jpg,.jpeg"
                                       class="w-full text-xs text-slate-500 file:me-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#ECFDF5] file:text-[#0DAD98] hover:file:bg-[#0DAD98]/20">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('N° Référence / Facture') }}
                                </label>
                                <input type="text" name="reference" placeholder="{{ __('Ex: FACT-2026-902') }}"
                                       class="w-full rounded-lg border-slate-200 text-xs text-slate-800">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                {{ __('Description complémentaire') }}
                            </label>
                            <input type="text" name="description" placeholder="{{ __('Ex: Remplacement du stock épuisé') }}"
                                   class="w-full rounded-lg border-slate-200 text-xs text-slate-800">
                        </div>
                    </div>
                </template>

                {{-- ─────────── MODE SALAIRE PROFESSEUR (catégorie « Salaires des professeurs ») ─────────── --}}
                <template x-if="isSalary">
                    <div class="space-y-4 rounded-xl border border-violet-200 bg-violet-50/40 p-4 dark:border-violet-900/50 dark:bg-violet-950/20">
                        <p class="text-xs font-semibold text-violet-700 dark:text-violet-300">
                            {{ __('Vous enregistrez un salaire. Il sera géré dans la table de paie existante (aucune dépense dupliquée) et rattaché au centre du professeur.') }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Professeur concerné') }} <span class="text-rose-500">*</span>
                                </label>
                                <select name="teacher_id" required class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                                    <option value="">{{ __('-- Sélectionner un enseignant --') }}</option>
                                    @foreach($teachersList as $tch)
                                        <option value="{{ $tch->id }}">{{ $tch->full_name }} ({{ $tch->center?->name ?? '' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Période de rémunération') }} <span class="text-rose-500">*</span>
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <select name="month" required class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                                        @foreach($monthsNames as $m => $label)
                                            <option value="{{ $m }}" {{ (int) date('n') === $m ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="year" min="2020" max="2050" value="{{ date('Y') }}" required
                                           class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Salaire prévu (DH)') }}
                                </label>
                                <input type="number" step="0.01" min="0" name="expected_amount" placeholder="{{ __('Ex: 6000') }}"
                                       class="w-full rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-900 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Montant versé (DH)') }} <span class="text-rose-500">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" name="paid_amount" required placeholder="{{ __('Ex: 6000') }}"
                                       class="w-full rounded-lg border-slate-200 bg-white text-xs font-semibold text-slate-900 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Date de paiement') }}
                                </label>
                                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}"
                                       class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Mode de paiement') }}
                                </label>
                                <select name="payment_method" class="w-full rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:ring-1 focus:ring-[#0DAD98]">
                                    <option value="bank_transfer">{{ __('Virement bancaire') }}</option>
                                    <option value="check">{{ __('Chèque') }}</option>
                                    <option value="cash">{{ __('Espèces') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Référence / Justificatif') }}
                                </label>
                                <input type="text" name="reference" placeholder="{{ __('Ex: VIR-2026-001') }}"
                                       class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    {{ __('Note') }}
                                </label>
                                <input type="text" name="notes" placeholder="{{ __('Ex: Salaire octobre - virement émis') }}"
                                       class="w-full rounded-lg border-slate-200 bg-white text-xs text-slate-800">
                            </div>
                        </div>
                        <input type="hidden" name="currency" value="MAD">
                    </div>
                </template>

                <div class="flex justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" x-on:click="expenseModalOpen = false" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">
                        {{ __('Annuler') }}
                    </button>
                    <button type="submit" class="finance-btn-primary">
                        <span x-show="!isSalary">{{ __('Enregistrer la dépense') }}</span>
                        <span x-show="isSalary" x-cloak>{{ __('Enregistrer le salaire') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODALE DE CONSULTATION DU DÉTAIL D'UNE DÉPENSE --}}
    <div x-show="detailModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs">
        <div x-on:click.outside="detailModalOpen = false"
             class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800">
            <template x-if="activeExpense">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white" x-text="activeExpense.title"></h3>
                            <span class="text-xs text-slate-400" x-text="activeExpense.expense_date"></span>
                        </div>
                        <button type="button" x-on:click="detailModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
                    </div>

                    <div class="mt-4 space-y-3 text-xs">
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <span class="text-slate-400">{{ __('Montant total :') }}</span>
                            <span class="font-extrabold text-rose-600" x-text="activeExpense.amount + ' MAD'"></span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <span class="text-slate-400">{{ __('Mode de paiement :') }}</span>
                            <span class="font-semibold text-slate-800" x-text="activeExpense.payment_method"></span>
                        </div>
                        <template x-if="activeExpense.supplier">
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-400">{{ __('Fournisseur :') }}</span>
                                <span class="font-semibold text-[#0DAD98]" x-text="activeExpense.supplier"></span>
                            </div>
                        </template>
                        <template x-if="activeExpense.items && activeExpense.items.length > 0">
                            <div class="pt-2">
                                <span class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">{{ __('Articles inclus :') }}</span>
                                <div class="rounded-lg bg-slate-50 p-2.5 space-y-1">
                                    <template x-for="it in activeExpense.items" :key="it.id">
                                        <div class="flex justify-between text-xs">
                                            <span x-text="it.quantity + 'x ' + it.name"></span>
                                            <strong x-text="it.total_price + ' MAD'"></strong>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="button" x-on:click="detailModalOpen = false" class="finance-btn-secondary py-1.5 px-3 text-xs">{{ __('Fermer') }}</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

</div>
@endsection
