@extends('layouts.app')
@section('title', __('Présence'))

@section('content')
<x-ui.page-header :title="__('Détail de présence')" :description="$attendance->student?->full_name">
    <a href="{{ route('attendances.index') }}" class="ec-btn-secondary">{{ __('Retour') }}</a>
    @can('update', $attendance)
        <a href="{{ route('attendances.edit', $attendance) }}" class="ec-btn-primary">{{ __('Modifier') }}</a>
    @endcan
</x-ui.page-header>
<x-ui.card>
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="text-slate-500">{{ __('Étudiant') }}</dt><dd class="font-medium">{{ $attendance->student?->full_name }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Classe') }}</dt><dd class="font-medium">{{ $attendance->classRoom?->perimeter_label }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Date') }}</dt><dd>{{ format_date($attendance->date, 'd/m/Y') }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Statut') }}</dt><dd><x-status-badge type="attendance" :status="$attendance->status" /></dd></div>
        <div><dt class="text-slate-500">{{ __('Arrivée') }}</dt><dd>{{ $attendance->arrival_time ?? '—' }}</dd></div>
        <div><dt class="text-slate-500">{{ __('Retard (min)') }}</dt><dd>{{ $attendance->late_minutes ?? '—' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('Commentaire') }}</dt><dd>{{ $attendance->comment ?: '—' }}</dd></div>
    </dl>
</x-ui.card>
@endsection
