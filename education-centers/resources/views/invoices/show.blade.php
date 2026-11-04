@extends('layouts.app')
@section('title', $invoice->invoice_number)

@section('content')
@php
    $paid = (float) $invoice->payments->where('status','paid')->sum('amount');
    $remaining = max(0, (float) $invoice->total_amount - $paid);
@endphp
<x-ui.page-header title="Facture {{ $invoice->invoice_number }}" :description="$invoice->student?->full_name">
    <a href="{{ route('invoices.pdf', ['invoice' => $invoice, 'mode' => 'attachment']) }}" class="ec-btn-primary"><x-icon name="download" class="h-4 w-4" /> {{ __('Télécharger') }}</a>
    <a href="{{ route('invoices.pdf', ['invoice' => $invoice, 'mode' => 'inline']) }}" target="_blank" rel="noopener" class="ec-btn-secondary"><x-icon name="printer" class="h-4 w-4" /> {{ __('Imprimer') }}</a>
    <a href="{{ route('invoices.index') }}" class="ec-btn-ghost">{{ __('Retour') }}</a>
</x-ui.page-header>
<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <x-ui.stat :label="__('Total')" :value="format_price((float) $invoice->total_amount, $invoice->currency)" icon="document" />
    <x-ui.stat :label="__('Payé')" :value="format_number($paid, 2)" icon="banknotes" tone="success" />
    <x-ui.stat :label="__('Restant')" :value="format_number($remaining, 2)" icon="clock" tone="warning" />
</div>
<x-ui.card>
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="text-slate-500">{{ __('Étudiant') }}</dt><dd class="font-medium">{{ $invoice->student?->full_name }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Formation') }}</dt><dd>{{ $invoice->enrollment?->formation?->general_formation_name ?? $invoice->formation?->general_formation_name ?? '—' }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Date') }}</dt><dd>{{ format_date($invoice->invoice_date, 'd/m/Y') }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Statut') }}</dt><dd><x-status-badge type="invoice" :status="$invoice->status" /></dd></div>
    </dl>
</x-ui.card>
@endsection
