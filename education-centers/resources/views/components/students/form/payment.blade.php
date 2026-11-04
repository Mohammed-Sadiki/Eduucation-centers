@props(['f' => []])

@php
    $mode = $f['mode'] ?? 'create';
    $enrollment = $f['enrollment'] ?? null;
    $currencySymbol = $f['currencySymbol'] ?? config('currency.symbol', 'DH');

    // Déjà encaissé sur l'inscription en cours (modification). Aucun paiement
    // existant n'est modifié, supprimé ou réinitialisé par la mise à jour.
    $isEdit = $mode === 'edit';
    $paidCount = $f['payments_count'] ?? 0;
    $paidAmount = (float) ($f['paid'] ?? 0);
    $remainingAmount = (float) ($f['remaining'] ?? 0);
@endphp

<x-students.form.card num="5" icon="banknotes" :title="__('Paiement')" span="full">
    <div class="space-y-5 p-6">

        {{-- Modification : état financier déjà enregistré, en lecture seule --}}
        @if($isEdit && $enrollment)
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-700 dark:bg-slate-800/40">
                <p class="text-base font-bold text-slate-700 dark:text-slate-200">{{ __('Situation déjà enregistrée') }}</p>
                <dl class="mt-3 grid gap-2 text-base sm:grid-cols-3">
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('Prix final de l\'inscription') }}</dt>
                        <dd class="font-bold text-slate-800 dark:text-slate-100">{{ number_format($enrollment->getPayableAmount(), 2, ',', ' ') }} {{ $currencySymbol }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('Déjà encaissé') }}</dt>
                        <dd class="font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($paidAmount, 2, ',', ' ') }} {{ $currencySymbol }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('Reste à payer') }}</dt>
                        <dd class="{{ $remainingAmount > 0 ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-bold text-emerald-600 dark:text-emerald-400' }}">
                            {{ number_format($remainingAmount, 2, ',', ' ') }} {{ $currencySymbol }}
                        </dd>
                    </div>
                </dl>
                <p class="ec-help mt-3 text-sm">
                    {{ __('Les versements enregistrés ( :count ) ne sont ni modifiés ni supprimés. Choisissez une option de paiement uniquement pour ajouter un nouveau versement.', [ 'count' => $paidCount ]) }}
                </p>
            </div>
        @endif

        {{-- Choix Avance / Payé --}}
        <div role="radiogroup" aria-label="{{ __('Type de paiement') }}" class="grid gap-3 sm:grid-cols-2">
            <label class="cursor-pointer rounded-2xl border p-5 text-center transition"
                   :class="paymentOption === 'paye' ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200 dark:border-emerald-500/50 dark:bg-emerald-500/10 dark:ring-emerald-500/20' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'">
                <input type="radio" name="payment_option" value="paye" class="sr-only" x-model="paymentOption" @error('payment_option') is-invalid @enderror>
                <span class="block text-lg font-extrabold text-emerald-700 dark:text-emerald-300">{{ __('Payé') }}</span>
                <span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">{{ __('La totalité du tarif est réglée, reste à payer : 0.') }}</span>
            </label>
            <label class="cursor-pointer rounded-2xl border p-5 text-center transition"
                   :class="paymentOption === 'avance' ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200 dark:border-amber-500/50 dark:bg-amber-500/10 dark:ring-amber-500/20' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'">
                <input type="radio" name="payment_option" value="avance" class="sr-only" x-model="paymentOption">
                <span class="block text-lg font-extrabold text-amber-700 dark:text-amber-300">{{ __('Avance') }}</span>
                <span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">{{ __('Un paiement partiel ; le solde reste à recouvrer.') }}</span>
            </label>
        </div>
        @error('payment_option') <p class="ec-error text-sm">{{ $message }}</p> @enderror

        {{-- Récapitulatif de calcul --}}
        <div x-show="paymentOption" x-cloak class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-5 dark:border-slate-700 dark:bg-slate-800/40">
            <div class="flex items-center justify-between text-base">
                <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Prix final de la formation') }}</span>
                <span class="font-bold text-slate-800 dark:text-slate-100" x-text="money(finalPayable)"></span>
            </div>

            {{-- Champ avance uniquement pour « Avance » --}}
            <div x-show="paymentOption === 'avance'" x-cloak>
                <label for="avance_amount" class="ec-label text-base font-semibold">{{ __('Montant de l\'avance') }} <span class="text-red-500">*</span></label>
                <input type="number" id="avance_amount" name="avance_amount" step="0.01" min="0"
                       inputmode="decimal" x-model.number="avanceAmount"
                       class="ec-input text-base @error('avance_amount') is-invalid @enderror"
                       value="{{ old('avance_amount') }}">
                @error('avance_amount') <p class="ec-error text-sm">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between border-t border-slate-200 pt-4 text-lg dark:border-slate-700">
                <span class="font-extrabold text-slate-700 dark:text-slate-200">{{ __('Reste à payer') }}</span>
                <span class="font-extrabold" :class="remaining > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'" x-text="money(remaining)"></span>
            </div>
            <p class="ec-help text-sm">{{ __('Le calcul et l\'enregistrement du solde sont également effectués côté serveur.') }}</p>
        </div>
    </div>
</x-students.form.card>
