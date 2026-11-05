@extends('layouts.app')
@section('title', __('Paiements'))

@section('content')
<x-ui.page-header :title="__('Paiements')" :description="__('Suivi financier des élèves.')">
    @can('create', \App\Models\Payment::class)
        <a href="{{ route('payments.create') }}" class="ec-btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('Enregistrer') }}</a>
    @endcan
</x-ui.page-header>
<div class="mb-6 grid gap-4 sm:grid-cols-2">
    <x-ui.stat :label="__('Total payé (filtre actuel)')" :value="format_price((float) $totalPaid)" icon="banknotes" tone="success" />
    <x-ui.stat :label="__('Paiements')" :value="$payments->total()" icon="document" />
</div>
<x-ui.card class="mb-4">
    <form method="GET" class="grid gap-3 md:grid-cols-4">
        <select name="student_id" class="ec-select">
            <option value="">{{ __('Tous les élèves') }}</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected(request('student_id')==$student->id)>{{ $student->full_name }}</option>
            @endforeach
        </select>
        <select name="status" class="ec-select">
            <option value="">{{ __('Tous les statuts') }}</option>
            @foreach(['pending'=>'En attente','paid'=>'Payé','cancelled'=>'Annulé','refunded'=>'Remboursé'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('status')===$k)>{{ __($v) }}</option>
            @endforeach
        </select>
        <select name="method" class="ec-select">
            <option value="">{{ __('Toutes les méthodes') }}</option>
            @foreach(['cash'=>'Espèces','card'=>'Carte','bank_transfer'=>'Virement','other'=>'Autre'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('method')===$k)>{{ __($v) }}</option>
            @endforeach
        </select>
        <button class="ec-btn-secondary">{{ __('Filtrer') }}</button>
    </form>
</x-ui.card>
<div class="ec-table-wrap">
    <table class="ec-table">
        <thead><tr><th>{{ __('Référence') }}</th><th>{{ __('Étudiant') }}</th><th>{{ __('Montant') }}</th><th>{{ __('Date') }}</th><th>{{ __('Méthode') }}</th><th>{{ __('Statut') }}</th><th></th></tr></thead>
        <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td class="font-mono text-xs">{{ $payment->reference }}</td>
                    <td>{{ $payment->student?->full_name }}</td>
                    <td>{{ format_price((float) $payment->amount, $payment->currency) }}</td>
                    <td>{{ format_date($payment->payment_date, 'd/m/Y') }}</td>
                    <td>{{ $payment->method_label }}</td>
                    <td><x-status-badge type="payment" :status="$payment->status" /></td>
                    <td class="text-end"><a href="{{ route('payments.show', $payment) }}" class="ec-btn-ghost ec-btn-sm">{{ __('Voir') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty :title="__('Aucun paiement')" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($payments->hasPages())<div class="mt-4">{{ $payments->links() }}</div>@endif
@endsection
