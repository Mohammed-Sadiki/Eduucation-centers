@extends('layouts.app')

@section('title', __('Mes paiements') . ' - ' . config('app.name'))

@php
    $toneFor = [
        'paid' => 'success',
        'partial' => 'warning',
        'pending' => 'info',
        'overdue' => 'danger',
        'none' => 'neutral',
    ];
    $stateMeta = [
        'settled' => ['tone' => 'success', 'label' => __('Paiement régularisé')],
        'upcoming' => ['tone' => 'info', 'label' => __('Échéance à venir')],
        'overdue' => ['tone' => 'danger', 'label' => __('Échéance dépassée')],
        'no_date' => ['tone' => 'neutral', 'label' => __('Sans date définie')],
    ];
@endphp

@section('content')
    <x-ui.page-header
        :title="__('Mes paiements')"
        :description="__('Votre situation financière, calculée à partir des données du module Finance de votre centre.')"
    />

    {{-- Alerte de retard (informatif et respectueux) --}}
    @if($situation['has_alert'])
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 sm:p-5 dark:border-rose-500/30 dark:bg-rose-500/10">
            <div class="flex items-start gap-3">
                <span class="ec-icon-chip shrink-0 bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300">
                    <x-icon name="warning" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-rose-800 dark:text-rose-200">{{ __('Paiement en retard') }}</h2>
                    <ul class="mt-1.5 space-y-1 text-sm text-rose-700 dark:text-rose-300">
                        @foreach($situation['overdue_invoices'] as $overdue)
                            <li>
                                {{ __('Votre échéance :formation du :date n’a pas encore été réglée. Montant exigible : :montant. Veuillez contacter l’administration de votre centre.', [
                                    'formation' => $overdue->formation,
                                    'date' => $overdue->due_date ? format_date($overdue->due_date, 'd/m/Y') : __('(sans date)'),
                                    'montant' => format_price($overdue->amount, $situation['currency']),
                                ]) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Récapitulatif global (sans double comptabilisation) --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Total à payer')" :value="format_price($situation['total_expected'], $situation['currency'])" icon="banknotes" tone="primary" />
        <x-ui.stat :label="__('Déjà payé')" :value="format_price($situation['total_paid'], $situation['currency'])" icon="check" tone="success" />
        <x-ui.stat :label="__('Montant restant')" :value="format_price($situation['total_remaining'], $situation['currency'])" icon="clock" tone="warning" />
        <div class="ec-kpi">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="ec-kpi__label truncate">{{ __('Statut global') }}</p>
                    <div class="mt-2">
                        <x-ui.badge :tone="$toneFor[$situation['status']] ?? 'neutral'">{{ $situation['status_label'] }}</x-ui.badge>
                    </div>
                    @if($situation['next_echeance'])
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Prochaine échéance') }} : {{ format_date($situation['next_echeance']->due_date, 'd/m/Y') }}
                        </p>
                    @else
                        <p class="mt-2 text-xs text-slate-400">{{ __('Aucune échéance à venir') }}</p>
                    @endif
                </div>
                <span class="ec-kpi__chip bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <x-icon name="info" class="h-6 w-6" />
                </span>
            </div>
        </div>
    </div>

    {{-- Détail par inscription --}}
    @if($situation['enrollments']->isEmpty())
        <x-ui.card>
            <x-ui.empty
                :title="__('Aucun paiement à afficher')"
                :description="__('Aucune inscription générant une situation financière n’est associée à votre compte.')"
            />
        </x-ui.card>
    @else
        <div class="space-y-6">
            @foreach($situation['enrollments'] as $row)
                <article class="ec-panel overflow-hidden">
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-5 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-brand-600 dark:text-brand-300">{{ __('Formation') }}</p>
                            <h2 class="mt-0.5 truncate text-lg font-bold text-slate-900 dark:text-white">{{ $row->formation->general_formation_name }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {{ $row->enrollment->registration_number }}
                                @if($row->center?->name)
                                    <span class="mx-1.5 text-slate-300 dark:text-slate-600">•</span>{{ $row->center->name }}
                                @endif
                            </p>
                        </div>
                        <x-ui.badge :tone="$toneFor[$row->status] ?? 'neutral'">{{ $row->status_label }}</x-ui.badge>
                    </div>

                    <div class="grid gap-4 border-b border-slate-100 p-5 dark:border-slate-800 sm:grid-cols-3">
                        <div class="rounded-lg bg-slate-50 px-3.5 py-3 dark:bg-slate-800/60">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Montant total') }}</p>
                            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ format_price($row->expected, $row->currency) }}</p>
                        </div>
                        <div class="rounded-lg bg-emerald-50 px-3.5 py-3 dark:bg-emerald-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">{{ __('Déjà payé') }}</p>
                            <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-200">{{ format_price($row->paid, $row->currency) }}</p>
                        </div>
                        <div class="rounded-lg bg-amber-50 px-3.5 py-3 dark:bg-amber-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300">{{ __('Restant') }}</p>
                            <p class="mt-1 text-lg font-bold text-amber-700 dark:text-amber-200">{{ format_price($row->remaining, $row->currency) }}</p>
                        </div>
                    </div>

                    {{-- Échéances --}}
                    @if($row->echeances->isNotEmpty())
                        <div class="border-b border-slate-100 p-5 dark:border-slate-800">
                            <p class="mb-3 text-sm font-bold text-slate-800 dark:text-white">{{ __('Échéances') }}</p>
                            <div class="space-y-2">
                                @foreach($row->echeances as $echeance)
                                    @php $meta = $stateMeta[$echeance->state] ?? $stateMeta['no_date']; @endphp
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3.5 py-2.5 text-sm dark:border-slate-800">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-800 dark:text-slate-100">
                                                {{ $echeance->invoice->invoice_number }}
                                            </p>
                                            <p class="truncate text-xs text-slate-500">
                                                @if($echeance->due_date)
                                                    {{ __('Échéance') }} : {{ format_date($echeance->due_date, 'd/m/Y') }} ·
                                                @endif
                                                {{ format_price($echeance->amount, $row->currency) }}
                                            </p>
                                        </div>
                                        <x-ui.badge :tone="$meta['tone']">{{ $meta['label'] }}</x-ui.badge>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Historique des versements --}}
                    <div class="p-5">
                        <p class="mb-3 text-sm font-bold text-slate-800 dark:text-white">{{ __('Historique des versements') }}</p>
                        @if($row->payments->isEmpty())
                            <p class="text-sm text-slate-500">{{ __('Aucun versement enregistré pour cette formation.') }}</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="ec-table min-w-full text-sm">
                                    <thead>
                                        <tr class="text-start text-xs uppercase tracking-wide text-slate-400">
                                            <th class="px-3 py-2 text-start">{{ __('Date') }}</th>
                                            <th class="px-3 py-2 text-start">{{ __('Montant') }}</th>
                                            <th class="px-3 py-2 text-start">{{ __('Moyen') }}</th>
                                            <th class="px-3 py-2 text-start">{{ __('Référence') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @foreach($row->payments as $payment)
                                            <tr>
                                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ format_date($payment->payment_date, 'd/m/Y') }}</td>
                                                <td class="px-3 py-2 font-semibold text-emerald-700 dark:text-emerald-300">{{ format_price($payment->amount, $payment->currency) }}</td>
                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $payment->method_label }}</td>
                                                <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ $payment->reference ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
