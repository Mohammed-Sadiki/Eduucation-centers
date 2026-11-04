@extends('layouts.app')

@section('title', __('Compte bancaire & Trésorerie') . ' — ' . config('app.name'))

@section('content')
<div class="space-y-6" x-data="{
    bankModalOpen: false,
    transactionType: 'deposit'
}">

    {{-- En-tête de la page --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-[#ECFDF5] px-2.5 py-0.5 text-xs font-semibold text-[#0DAD98] border border-[#0DAD98]/20 dark:bg-[#0DAD98]/15 dark:text-[#0DAD98] mb-1.5 shadow-2xs">
                <span>🏦</span>
                <span>{{ __('Gestion financière · Journal de trésorerie & Conciliation') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Compte bancaire & Trésorerie') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Suivi des dépôts, retraits, virements inter-comptes et solde consolidé en temps réel.') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button" x-on:click="bankModalOpen = true; transactionType = 'deposit'" class="finance-btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                <span>{{ __('+ Nouvelle écriture') }}</span>
            </button>
            <a href="{{ route('super-admin.finance.dashboard') }}" class="finance-btn-secondary">
                <x-icon name="arrow-left" class="h-4 w-4 text-slate-500" />
                <span>{{ __('Dashboard') }}</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-semibold text-emerald-800 shadow-2xs">
            <x-icon name="check" class="h-4 w-4 text-[#0DAD98]" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- Trois grandes cartes pastel (Capture 7 Cad Syndic) --}}
    <div class="grid gap-4 sm:grid-cols-3">
        {{-- Total Déposé --}}
        <div class="finance-card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Total Déposé') }}</span>
                    <div class="mt-2 text-2xl lg:text-3xl font-extrabold text-[#0DAD98] whitespace-nowrap">
                        {{ format_price($totalDeposits, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#0DAD98]">
                    <x-icon name="arrow-down-tray" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Dépôts bancaires & encaissements') }}
            </div>
        </div>

        {{-- Total Retiré --}}
        <div class="finance-card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Total Retiré') }}</span>
                    <div class="mt-2 text-2xl lg:text-3xl font-extrabold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                        {{ format_price($totalWithdrawals, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <x-icon name="arrow-up-tray" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Décaissements, virements & retraits') }}
            </div>
        </div>

        {{-- Solde Banque --}}
        <div class="finance-card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Solde Banque') }}</span>
                    <div class="mt-2 text-2xl lg:text-3xl font-extrabold {{ $bankBalance >= 0 ? 'text-slate-900 dark:text-white' : 'text-rose-600' }} whitespace-nowrap">
                        {{ format_price($bankBalance, 'MAD') }}
                    </div>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <x-icon name="building" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800/80 pt-2">
                {{ __('Position nette de trésorerie bancaire') }}
            </div>
        </div>
    </div>

    {{-- Filtres compacts --}}
    <form method="GET" action="{{ route('super-admin.finance.bank') }}"
          class="rounded-xl border border-slate-200/90 bg-white p-3.5 shadow-[0_1px_3px_rgba(0,0,0,0.02)] dark:border-slate-800 dark:bg-slate-900">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 items-center">
            <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Recherche') }}</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('Libellé, référence...') }}"
                       class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
            </div>
            <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Centre') }}</label>
                <select name="center_id" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
                    <option value="" {{ empty($centerId) ? 'selected' : '' }}>{{ __('Tous centres') }}</option>
                    @foreach($centers as $c)
                        <option value="{{ $c->id }}" {{ $centerId === $c->id ? 'selected' : '' }}>{{ $c->translated('name') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('Type d\'opération') }}</label>
                <select name="type" onchange="this.form.submit()"
                        class="w-full rounded-lg border-slate-200 bg-slate-50/50 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-[#0DAD98] focus:bg-white focus:ring-1 focus:ring-[#0DAD98]">
                    <option value="all" {{ $type === 'all' ? 'selected' : '' }}>{{ __('Toutes écritures') }}</option>
                    <option value="deposit" {{ $type === 'deposit' ? 'selected' : '' }}>{{ __('Dépôts uniquement') }}</option>
                    <option value="withdrawal" {{ $type === 'withdrawal' ? 'selected' : '' }}>{{ __('Retraits uniquement') }}</option>
                </select>
            </div>
        </div>
    </form>

    {{-- Journal bancaire (Capture 7 Cad Syndic) --}}
    <div class="finance-card overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Journal des opérations bancaires') }}</h2>
            <span class="text-xs text-slate-400">{{ $transactions->count() }} {{ __('écriture(s)') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs">
                <thead class="bg-slate-50/80 text-2xs uppercase font-bold text-slate-400 border-b border-slate-100 dark:bg-slate-800/50 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4">{{ __('Date') }}</th>
                        <th class="py-3 px-4">{{ __('Libellé') }}</th>
                        <th class="py-3 px-4">{{ __('Origine') }}</th>
                        <th class="py-3 px-4">{{ __('Mode') }}</th>
                        <th class="py-3 px-4 text-end">{{ __('Dépôt') }}</th>
                        <th class="py-3 px-4 text-end">{{ __('Retrait') }}</th>
                        <th class="py-3 px-4 text-end">{{ __('Solde cumulé') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($transactions as $t)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                {{ $t->transaction_date ? format_date($t->transaction_date, 'd/m/Y') : '—' }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                {{ translate_label($t->label) }}
                                @if($t->reference)
                                    <span class="block text-2xs text-slate-400 font-mono">{{ __('Réf:') }} {{ $t->reference }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                {{ translate_label($t->origin) }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-slate-600">
                                {{ ucfirst(str_replace('_', ' ', $t->method)) }}
                            </td>
                            <td class="py-3 px-4 text-end font-bold text-[#0DAD98] whitespace-nowrap">
                                {{ $t->type === 'deposit' ? format_price($t->amount, 'MAD') : '—' }}
                            </td>
                            <td class="py-3 px-4 text-end font-bold text-rose-600 whitespace-nowrap">
                                {{ $t->type === 'withdrawal' ? format_price($t->amount, 'MAD') : '—' }}
                            </td>
                            <td class="py-3 px-4 text-end font-extrabold text-slate-900 dark:text-white whitespace-nowrap">
                                {{ format_price($t->running_balance, 'MAD') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-xs text-slate-400">
                                {{ __('Aucune écriture bancaire enregistrée pour le moment.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modale d'ajout d'écriture bancaire --}}
    <div x-show="bankModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs">
        <div x-on:click.outside="bankModalOpen = false"
             class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900 dark:border dark:border-slate-800">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Nouvelle écriture bancaire') }}</h3>
                <button type="button" x-on:click="bankModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form method="POST" action="{{ route('super-admin.finance.bank.store') }}" class="mt-4 space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Type d\'opération') }} <span class="text-rose-500">*</span></label>
                        <select name="type" x-model="transactionType" required class="w-full rounded-lg border-slate-200 text-xs">
                            <option value="deposit">{{ __('Dépôt (Crédit)') }}</option>
                            <option value="withdrawal">{{ __('Retrait (Débit)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Centre') }} <span class="text-rose-500">*</span></label>
                        <select name="center_id" required class="w-full rounded-lg border-slate-200 text-xs">
                            @foreach($centers as $c)
                                <option value="{{ $c->id }}">{{ $c->translated('name') }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Libellé') }} <span class="text-rose-500">*</span></label>
                    <input type="text" name="label" required placeholder="{{ __('Ex: Remise de chèques scolarité, Virement loyer...') }}"
                           class="w-full rounded-lg border-slate-200 text-xs">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Origine') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="origin" required placeholder="{{ __('Ex: Caisse Centre, Banque Centrale...') }}"
                               class="w-full rounded-lg border-slate-200 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Mode de règlement') }} <span class="text-rose-500">*</span></label>
                        <select name="method" required class="w-full rounded-lg border-slate-200 text-xs">
                            <option value="bank_transfer">{{ __('Virement bancaire') }}</option>
                            <option value="check">{{ __('Chèque') }}</option>
                            <option value="cash">{{ __('Espèces') }}</option>
                            <option value="credit_card">{{ __('Carte bancaire') }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Montant (DH)') }} <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" required placeholder="{{ __('Ex: 10000') }}"
                               class="w-full rounded-lg border-slate-200 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Date') }} <span class="text-rose-500">*</span></label>
                        <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required
                               class="w-full rounded-lg border-slate-200 text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Référence / Numéro d\'avis') }}</label>
                    <input type="text" name="reference" placeholder="{{ __('Ex: AVIS-DEBIT-908') }}"
                           class="w-full rounded-lg border-slate-200 text-xs">
                </div>

                <div class="flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                    <button type="button" x-on:click="bankModalOpen = false" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">{{ __('Annuler') }}</button>
                    <button type="submit" class="finance-btn-primary">{{ __('Valider l\'écriture') }}</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
