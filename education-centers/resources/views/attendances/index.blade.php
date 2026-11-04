@extends('layouts.app')
@section('title', __('Présences'))

@section('content')
<x-domain-back />
<x-ui.page-header :title="__('Présences')" :description="__('Suivi des présences par séance et par classe.')">
    @can('create', \App\Models\Attendance::class)
        <a href="{{ route('attendances.create') }}" class="ec-btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('Saisir une séance') }}</a>
    @endcan
</x-ui.page-header>

<x-ui.card class="mb-4">
    <form method="GET" class="grid gap-3 md:grid-cols-4">
        {{-- Filtre de classe masqué quand une seule classe est attribuable. --}}
        @if($classes->count() > 1)
            <select name="class_id" class="ec-select">
                <option value="">{{ auth()->user()->isTeacher() ? __('Toutes mes classes') : __('Toutes les classes') }}</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->perimeter_label }}</option>
                @endforeach
            </select>
        @endif
        <select name="status" class="ec-select">
            <option value="">{{ __('Tous les statuts') }}</option>
            <option value="present" @selected(request('status')==='present')>{{ __('Présent') }}</option>
            <option value="absent" @selected(request('status')==='absent')>{{ __('Absent') }}</option>
            <option value="late" @selected(request('status')==='late')>{{ __('Retard') }}</option>
        </select>
        <input type="date" name="date" value="{{ request('date') }}" class="ec-input">
        <button class="ec-btn-secondary">{{ __('Filtrer') }}</button>
    </form>
</x-ui.card>

<div class="ec-table-wrap">
    <table class="ec-table">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Étudiant') }}</th>
                <th>{{ __('Classe') }}</th>
                <th>{{ __('Statut') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $attendance)
                <tr>
                    <td>{{ format_date($attendance->date, 'd/m/Y') }}</td>
                    <td class="font-medium">{{ $attendance->student?->full_name }}</td>
                    <td>{{ $attendance->classRoom?->perimeter_label }}</td>
                    <td><x-status-badge type="attendance" :status="$attendance->status" /></td>
                    <td class="text-end"><a href="{{ route('attendances.show', $attendance) }}" class="ec-btn-ghost ec-btn-sm">{{ __('Voir') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-ui.empty :title="__('Aucune présence')" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($attendances->hasPages())
    <div class="mt-4">{{ $attendances->links() }}</div>
@endif
@endsection
