@extends('layouts.app')
@section('title', __('Élèves') . ' — ' . config('app.name'))

@section('content')
<x-ui.page-header :title="__('Élèves')" :description="auth()->user()->isSuperAdmin() ? __('Tous les centres') : __('Centre :') . ' ' . auth()->user()->center?->name" />

<x-ui.card class="mb-4">
    <form method="GET" action="{{ route('students.index') }}" id="form-filter-students" class="grid gap-3 md:grid-cols-6">
        <input type="text" id="student_number" name="student_number" class="ec-input" placeholder="{{ __('N° étudiant') }}" value="{{ request('student_number') }}">
        <input type="text" id="search" name="search" class="ec-input" placeholder="{{ __('Nom ou prénom') }}" value="{{ request('search') }}">
        <input type="text" id="email" name="email" class="ec-input" placeholder="{{ __('Email') }}" value="{{ request('email') }}">
        <select id="status" name="status" class="ec-select">
            <option value="">{{ __('Tous les statuts') }}</option>
            <option value="active" @selected(request('status')==='active')>{{ __('Actif') }}</option>
            <option value="inactive" @selected(request('status')==='inactive')>{{ __('Inactif') }}</option>
        </select>
        @if(auth()->user()->isSuperAdmin())
            <select id="center_id" name="center_id" class="ec-select">
                <option value="">{{ __('Tous les centres') }}</option>
                @foreach($centers as $center)
                    <option value="{{ $center->id }}" @selected(request('center_id')==$center->id)>{{ $center->translated('name') }}</option>
                @endforeach
            </select>
        @endif
        <button class="ec-btn-secondary">{{ __('Filtrer') }}</button>
    </form>
</x-ui.card>

<div class="ec-table-wrap">
    <table class="ec-table">
        <thead>
            <tr>
                <th>{{ __('Élève') }}</th>
                <th>N°</th>
                <th>{{ __('Email') }}</th>
                <th>{{ __('Centre') }}</th>
                <th>{{ __('Statut') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($students as $student)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$student->full_name" size="sm" />
                            <div>
                                <div class="font-medium">{{ $student->full_name }}</div>
                                <div class="text-xs text-slate-500"><bdi dir="ltr">{{ $student->phone ?? '—' }}</bdi></div>
                            </div>
                        </div>
                    </td>
                    <td class="font-mono text-xs">{{ $student->student_number }}</td>
                    <td>{{ $student->user?->email ?? '—' }}</td>
                    <td>{{ $student->center?->name ?? '—' }}</td>
                    <td><x-status-badge :status="$student->status" /></td>
                    <td class="text-end">
                        @can('view', $student)
                            <a href="{{ route('students.show', $student) }}" class="ec-btn-ghost ec-btn-sm" id="btn-show-student-{{ $student->id }}">{{ __('Voir') }}</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty :title="__('Aucun élève trouvé.')" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($students->hasPages())
    <div class="mt-4">{{ $students->links() }}</div>
@endif
@endsection
