@extends('layouts.app')
@section('title', $payment->reference)

@section('content')
<x-ui.page-header title="Paiement {{ $payment->reference }}" :description="$payment->student?->full_name">
    @can('update', $payment)
        <a href="{{ route('payments.edit', $payment) }}" class="ec-btn-primary">{{ __('Modifier') }}</a>
    @endcan
</x-ui.page-header>
<x-ui.card>
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="text-slate-500">{{ __('Montant') }}</dt><dd class="text-xl font-semibold">{{ format_price((float) $payment->amount, $payment->currency) }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Statut') }}</dt><dd><x-status-badge type="payment" :status="$payment->status" /></dd></div>
        <div><dt class="text-slate-500">{{ __('Date') }}</dt><dd>{{ format_date($payment->payment_date, 'd/m/Y') }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Méthode') }}</dt><dd>{{ $payment->method_label }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Reçu') }}</dt><dd class="font-mono text-xs">{{ $payment->reference }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Enregistré par') }}</dt><dd>{{ $payment->recorder?->name ?? '—' }}</dd></div>
    </dl>
</x-ui.card>
@endsection
