@extends('layouts.app')
@section('title', __('Certificats'))

@section('content')
<x-ui.page-header :title="__('Certificats')" :description="__('Attestations et documents officiels.')">
    @can('create', \App\Models\Certificate::class)
        <a href="{{ route('certificates.create') }}" class="ec-btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('Générer') }}</a>
    @endcan
</x-ui.page-header>
<x-ui.card class="mb-4">
    <form method="GET" class="flex max-w-2xl gap-2">
        <select name="type" class="ec-select">
            <option value="">{{ __('Tous les types') }}</option>
            @foreach(['enrollment'=>'Inscription','completion'=>'Formation','attendance'=>'Présence','grades_report'=>'Notes','receipt'=>'Reçu','other'=>'Autre'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('type')===$k)>{{ __($v) }}</option>
            @endforeach
        </select>
        <select name="status" class="ec-select">
            <option value="">{{ __('Tous les statuts') }}</option>
            @foreach(['draft'=>'Brouillon','issued'=>'Délivré','revoked'=>'Révoqué'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('status')===$k)>{{ __($v) }}</option>
            @endforeach
        </select>
        <button class="ec-btn-secondary">{{ __('Filtrer') }}</button>
    </form>
</x-ui.card>
<div class="ec-table-wrap">
    <table class="ec-table">
        <thead><tr><th>{{ __('Numéro') }}</th><th>{{ __('Type') }}</th><th>{{ __('Étudiant') }}</th><th>{{ __('Formation') }}</th><th>{{ __('Date') }}</th><th></th></tr></thead>
        <tbody>
            @forelse($certificates as $certificate)
                <tr>
                    <td class="font-mono text-xs">{{ $certificate->certificate_number }}</td>
                    <td>{{ $certificate->type_label }}</td>
                    <td>{{ $certificate->student?->full_name }}</td>
                    <td>{{ $certificate->formation?->general_formation_name ?? '—' }}</td>
                    <td>{{ format_date($certificate->issued_date, 'd/m/Y') }}</td>
                    <td class="text-end">
                        @if(auth()->user()->isStudent())
                            <a href="{{ route('student.certificates.download', $certificate) }}" class="ec-btn-secondary ec-btn-sm">{{ __('Télécharger') }}</a>
                        @else
                            <a href="{{ route('certificates.show', $certificate) }}" class="ec-btn-ghost ec-btn-sm">{{ __('Voir') }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty :title="__('Aucun certificat')" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($certificates->hasPages())<div class="mt-4">{{ $certificates->links() }}</div>@endif
@endsection
