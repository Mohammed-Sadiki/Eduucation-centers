@extends('layouts.app')
@section('title', $certificate->certificate_number)

@section('content')
<x-ui.page-header title="{{ $certificate->certificate_number }}" :description="$certificate->type_label">
    <a href="{{ route('certificates.download', $certificate) }}" class="ec-btn-primary"><x-icon name="download" class="h-4 w-4" /> {{ __('Télécharger') }}</a>
    <a href="{{ route('certificates.print', $certificate) }}" target="_blank" rel="noopener" class="ec-btn-secondary"><x-icon name="printer" class="h-4 w-4" /> {{ __('Imprimer') }}</a>
</x-ui.page-header>
<x-ui.card>
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="text-slate-500">{{ __('Étudiant') }}</dt><dd class="font-medium">{{ $certificate->student?->full_name }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Formation') }}</dt><dd>{{ $certificate->formation?->general_formation_name ?? '—' }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Date d’émission') }}</dt><dd>{{ format_date($certificate->issued_date, 'd/m/Y') }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Statut') }}</dt><dd><x-status-badge :status="$certificate->status" type="certificate" /></dd></div>
    </dl>
</x-ui.card>
@endsection
