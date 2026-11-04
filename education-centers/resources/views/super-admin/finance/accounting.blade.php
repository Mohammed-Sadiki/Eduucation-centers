@extends('layouts.app')

@section('title', __('Comptabilité générale') . ' — ' . config('app.name'))

@section('content')
<div class="space-y-6">

    {{-- En-tête de la page --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200/90 pb-5 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-[#ECFDF5] px-2.5 py-0.5 text-xs font-semibold text-[#0DAD98] border border-[#0DAD98]/20 dark:bg-[#0DAD98]/15 dark:text-[#0DAD98] mb-1.5 shadow-2xs">
                <span>📑</span>
                <span>{{ __('Gestion financière · États financiers & Comptabilité') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Comptabilité') }}
            </h1>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Journal général, Compte de Produits et Charges (CPC), Bilan patrimonial et Grand Livre.') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('super-admin.finance.dashboard') }}" class="finance-btn-secondary">
                <x-icon name="arrow-left" class="h-4 w-4 text-slate-500" />
                <span>{{ __('Dashboard') }}</span>
            </a>
            <a href="{{ route('super-admin.finance.reports') }}" class="finance-btn-primary">
                <x-icon name="arrow-down-tray" class="h-4 w-4" />
                <span>{{ __('Exporter le journal') }}</span>
            </a>
        </div>
    </div>

    {{-- Quatre Onglets Cad Syndic (Journal | CPC | Bilan | Grand Livre) --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'journal']) }}"
               class="rounded-lg px-4 py-2 text-xs font-semibold transition-all {{ ($tab ?? 'journal') === 'journal' ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white' : 'text-slate-600 hover:text-slate-900' }}">
                <span>{{ __('Journal') }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'cpc']) }}"
               class="rounded-lg px-4 py-2 text-xs font-semibold transition-all {{ ($tab ?? '') === 'cpc' ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white' : 'text-slate-600 hover:text-slate-900' }}">
                <span>{{ __('CPC (Produits & Charges)') }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'bilan']) }}"
               class="rounded-lg px-4 py-2 text-xs font-semibold transition-all {{ ($tab ?? '') === 'bilan' ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white' : 'text-slate-600 hover:text-slate-900' }}">
                <span>{{ __('Bilan') }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'grand_livre']) }}"
               class="rounded-lg px-4 py-2 text-xs font-semibold transition-all {{ ($tab ?? '') === 'grand_livre' ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white' : 'text-slate-600 hover:text-slate-900' }}">
                <span>{{ __('Grand Livre') }}</span>
            </a>
        </div>

        <form method="GET" action="{{ route('super-admin.finance.accounting') }}" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="center_id" onchange="this.form.submit()" class="rounded-lg border-slate-200 py-1.5 px-2.5 text-xs">
                <option value="" {{ empty($centerId) ? 'selected' : '' }}>{{ __('Tous centres') }}</option>
                @foreach($centers as $c)
                    <option value="{{ $c->id }}" {{ $centerId === $c->id ? 'selected' : '' }}>{{ $c->translated('name') }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Contenu de l'onglet actif --}}
    @if(($tab ?? 'journal') === 'journal')
        {{-- KPI Journal --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="finance-card p-4.5">
                <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Total recettes (Crédit)') }}</span>
                <div class="mt-2 text-2xl font-extrabold text-[#0DAD98] whitespace-nowrap">
                    {{ format_price($totalProducts, 'MAD') }}
                </div>
            </div>
            <div class="finance-card p-4.5">
                <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Total dépenses (Débit)') }}</span>
                <div class="mt-2 text-2xl font-extrabold text-rose-600 whitespace-nowrap">
                    {{ format_price($totalCharges, 'MAD') }}
                </div>
            </div>
            <div class="finance-card p-4.5">
                <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Solde net') }}</span>
                <div class="mt-2 text-2xl font-extrabold {{ $netResult >= 0 ? 'text-[#0DAD98]' : 'text-rose-600' }} whitespace-nowrap">
                    {{ format_price($netResult, 'MAD') }}
                </div>
            </div>
            <div class="finance-card p-4.5">
                <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Opérations comptables') }}</span>
                <div class="mt-2 text-2xl font-extrabold text-slate-900 dark:text-white whitespace-nowrap">
                    {{ $entries->count() }}
                </div>
            </div>
        </div>

        {{-- Tableau du Journal --}}
        <div class="finance-card overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Livre-Journal général') }}</h2>
                <span class="text-xs text-slate-400">{{ __('Partie double') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-start text-xs">
                    <thead class="bg-slate-50/80 text-2xs uppercase font-bold text-slate-400 border-b border-slate-100">
                        <tr>
                            <th class="py-3 px-4">{{ __('N° Pièce') }}</th>
                            <th class="py-3 px-4">{{ __('Date') }}</th>
                            <th class="py-3 px-4">{{ __('Type') }}</th>
                            <th class="py-3 px-4">{{ __('Libellé') }}</th>
                            <th class="py-3 px-4">{{ __('Tiers') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Débit') }}</th>
                            <th class="py-3 px-4 text-end">{{ __('Crédit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($entries as $entry)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3 px-4 font-mono text-xs font-bold text-slate-800">{{ $entry->piece_number }}</td>
                                <td class="py-3 px-4 text-slate-600">{{ $entry->entry_date ? format_date($entry->entry_date, 'd/m/Y') : '—' }}</td>
                                <td class="py-3 px-4"><span class="rounded bg-slate-100 px-1.5 py-0.5 text-2xs font-semibold text-slate-700">{{ translate_label($entry->entry_type) }}</span></td>
                                <td class="py-3 px-4 font-semibold text-slate-900">{{ translate_label($entry->label) }}</td>
                                <td class="py-3 px-4 text-slate-600">{{ translate_label($entry->third_party ?? '—') }}</td>
                                <td class="py-3 px-4 text-end font-bold text-rose-600">{{ $entry->debit > 0 ? format_price($entry->debit, 'MAD') : '—' }}</td>
                                <td class="py-3 px-4 text-end font-bold text-[#0DAD98]">{{ $entry->credit > 0 ? format_price($entry->credit, 'MAD') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-xs text-slate-400">
                                    {{ __('Aucune écriture comptable enregistrée.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @elseif($tab === 'cpc')
        {{-- Compte de Produits et Charges --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="finance-card p-5">
                <h3 class="text-sm font-bold text-[#0DAD98] mb-3 flex items-center justify-between">
                    <span>{{ __('PRODUITS D\'EXPLOITATION') }}</span>
                    <span>{{ format_price($totalProducts, 'MAD') }}</span>
                </h3>
                <div class="space-y-2 text-xs border-t border-slate-100 pt-3">
                    <div class="flex justify-between py-1.5 border-b border-slate-50">
                        <span class="text-slate-600">{{ __('7124 · Scolarités & Formations perçues') }}</span>
                        <strong class="text-slate-900">{{ format_price($totalProducts, 'MAD') }}</strong>
                    </div>
                </div>
            </div>

            <div class="finance-card p-5">
                <h3 class="text-sm font-bold text-rose-600 mb-3 flex items-center justify-between">
                    <span>{{ __('CHARGES D\'EXPLOITATION') }}</span>
                    <span>{{ format_price($totalCharges, 'MAD') }}</span>
                </h3>
                <div class="space-y-2 text-xs border-t border-slate-100 pt-3">
                    <div class="flex justify-between py-1.5 border-b border-slate-50">
                        <span class="text-slate-600">{{ __('6171 · Rémunérations du corps enseignant') }}</span>
                        <strong class="text-slate-900">{{ format_price($totalChargesSalaries, 'MAD') }}</strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50">
                        <span class="text-slate-600">{{ __('6122 · Dépenses d\'exploitation & Prestations') }}</span>
                        <strong class="text-slate-900">{{ format_price($totalChargesExpenses, 'MAD') }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="finance-card p-5 flex items-center justify-between bg-slate-50/50">
            <div>
                <span class="text-2xs font-bold uppercase tracking-wider text-slate-400">{{ __('RÉSULTAT NET COMPTABLE') }}</span>
                <div class="text-xl font-extrabold {{ $netResult >= 0 ? 'text-[#0DAD98]' : 'text-rose-600' }}">
                    {{ format_price($netResult, 'MAD') }}
                </div>
            </div>
            <span class="rounded-xl px-3 py-1 text-xs font-bold {{ $netResult >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                {{ $netResult >= 0 ? __('Bénéfice net') : __('Déficit d\'exploitation') }}
            </span>
        </div>

    @elseif($tab === 'bilan')
        {{-- Bilan Patrimonial Simplifié --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="finance-card p-5">
                <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center justify-between">
                    <span>{{ __('ACTIF') }}</span>
                    <span>{{ format_price(max(0, $netResult) + $totalProducts, 'MAD') }}</span>
                </h3>
                <div class="space-y-2 text-xs border-t border-slate-100 pt-3">
                    <div class="flex justify-between py-1.5 border-b border-slate-50">
                        <span class="text-slate-600">{{ __('5141 · Banque & Comptes financiers') }}</span>
                        <strong class="text-slate-900">{{ format_price($totalProducts - $totalCharges, 'MAD') }}</strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50">
                        <span class="text-slate-600">{{ __('3421 · Créances clients / Scolarités dues') }}</span>
                        <strong class="text-slate-900">{{ __('En cours d\'exercice') }}</strong>
                    </div>
                </div>
            </div>

            <div class="finance-card p-5">
                <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center justify-between">
                    <span>{{ __('PASSIF & CAPITAUX PROPRES') }}</span>
                    <span>{{ format_price(max(0, $netResult) + $totalProducts, 'MAD') }}</span>
                </h3>
                <div class="space-y-2 text-xs border-t border-slate-100 pt-3">
                    <div class="flex justify-between py-1.5 border-b border-slate-50">
                        <span class="text-slate-600">{{ __('1191 · Résultat net de l\'exercice') }}</span>
                        <strong class="text-slate-900">{{ format_price($netResult, 'MAD') }}</strong>
                    </div>
                </div>
            </div>
        </div>

    @elseif($tab === 'grand_livre')
        {{-- Grand Livre --}}
        <div class="finance-card p-5">
            <h3 class="text-sm font-bold text-slate-900 mb-2">{{ __('Grand Livre des Comptes') }}</h3>
            <p class="text-xs text-slate-500 mb-4">{{ __('Ventilation par numéro de compte général du plan comptable.') }}</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-slate-100 p-4">
                    <div class="flex justify-between text-xs font-bold text-slate-800 border-b pb-2 mb-2">
                        <span>{{ __('Compte 5141 — Banques') }}</span>
                        <span class="text-[#0DAD98]">{{ __('Solde débiteur :') }} {{ format_price($netResult, 'MAD') }}</span>
                    </div>
                    <p class="text-xs text-slate-400">{{ __('Total des flux de trésorerie bancaire de la période.') }}</p>
                </div>
                <div class="rounded-xl border border-slate-100 p-4">
                    <div class="flex justify-between text-xs font-bold text-slate-800 border-b pb-2 mb-2">
                        <span>{{ __('Compte 7124 — Scolarités perçues') }}</span>
                        <span class="text-[#0DAD98]">{{ __('Solde créditeur :') }} {{ format_price($totalProducts, 'MAD') }}</span>
                    </div>
                    <p class="text-xs text-slate-400">{{ __('Total des encaissements d\'inscriptions et formations.') }}</p>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
