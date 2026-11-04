@extends('layouts.app')

@section('title', __('Mes présences'))

@php
    $rate = number_format((float) ($stats['rate'] ?? 0), 1, ',', ' ');
    $hasClassFilter = $classes->count() > 1;
@endphp

@section('content')
    <x-ui.page-header
        :title="__('Mes présences')"
        :description="__('Vos présences personnelles, absences, retards et justificatifs.').' '.($student?->center?->name ?? auth()->user()->center?->name)"
    >
        <a href="{{ route('student.schedule') }}" class="ec-btn-secondary">
            <x-icon name="calendar" class="h-4 w-4" /> {{ __('Mon emploi du temps') }}
        </a>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Taux de présence')" :value="$rate.' %'" icon="check" tone="success" :hint="__('présences et retards')" />
        <x-ui.stat :label="__('Présences')" :value="format_number($stats['present'] ?? 0)" icon="check" tone="info" :hint="__('séances suivies')" />
        <x-ui.stat :label="__('Absences')" :value="format_number($stats['absent'] ?? 0)" icon="warning" tone="danger" :hint="__('séances manquées')" />
        <x-ui.stat :label="__('Retards')" :value="format_number($stats['late'] ?? 0)" icon="clock" tone="warning" :hint="__('arrivées tardives')" />
    </div>

    <form method="GET" action="{{ route('student.attendances') }}" class="ec-card mb-5 p-4 sm:p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            @if($hasClassFilter)
                <label class="block">
                    <span class="ec-label">{{ __('Classe') }}</span>
                    <select name="class_id" class="ec-select">
                        <option value="">{{ __('Toutes mes classes') }}</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" @selected((int) $classId === (int) $class->id)>
                                {{ $class->perimeter_label }}{{ $class->code ? ' ('.$class->code.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="block">
                <span class="ec-label">{{ __('Statut') }}</span>
                <select name="status" class="ec-select">
                    <option value="">{{ __('Tous les statuts') }}</option>
                    @foreach(['present' => __('Présent'), 'absent' => __('Absent'), 'late' => __('Retard')] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="ec-label">{{ __('Date') }}</span>
                <input type="date" name="date" value="{{ request('date') }}" class="ec-input">
            </label>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="ec-btn-primary">
                    <x-icon name="filter" class="h-4 w-4" /> {{ __('Filtrer') }}
                </button>
                @if(request()->filled('class_id') || request()->filled('status') || request()->filled('date'))
                    <a href="{{ route('student.attendances') }}" class="ec-btn-ghost">{{ __('Réinitialiser') }}</a>
                @endif
            </div>
        </div>
    </form>

    <div class="ec-table-wrap">
        <table class="ec-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Horaire') }}</th>
                    <th>{{ __('Classe') }}</th>
                    <th>{{ __('Statut') }}</th>
                    <th>{{ __('Retard') }}</th>
                    <th>{{ __('Justificatif') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $attendance)
                    <tr>
                        <td class="whitespace-nowrap font-medium text-slate-800 dark:text-slate-100">
                            {{ format_date($attendance->date, 'D d M Y') }}
                        </td>
                        <td class="whitespace-nowrap text-slate-600 dark:text-slate-300" dir="ltr">
                            {{ $attendance->schedule?->formatted_time ?? ($attendance->arrival_time ? substr((string) $attendance->arrival_time, 0, 5) : '—') }}
                        </td>
                        <td class="text-slate-600 dark:text-slate-300">
                            {{ $attendance->classRoom?->perimeter_label ?? '—' }}
                        </td>
                        <td><x-status-badge type="attendance" :status="$attendance->status" /></td>
                        <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">
                            @if($attendance->late_minutes)
                                {{ $attendance->late_minutes }} {{ __('min') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="max-w-xs text-sm text-slate-600 dark:text-slate-300">
                            @if($attendance->justification)
                                <span class="block">{{ $attendance->justification }}</span>
                                @if($attendance->justification_status)
                                    <span class="mt-1 inline-block text-xs font-semibold {{ $attendance->justification_status === 'approved' ? 'text-emerald-600' : ($attendance->justification_status === 'rejected' ? 'text-rose-600' : 'text-amber-600') }}">
                                        {{ match ($attendance->justification_status) {
                                            'approved' => __('Justificatif accepté'),
                                            'rejected' => __('Justificatif refusé'),
                                            default => __('Justificatif en attente'),
                                        } }}
                                    </span>
                                @endif
                            @endif
                            {{-- Le commentaire de présence est visible même sans justificatif. --}}
                            @if($attendance->comment)
                                <span class="mt-1 block text-xs text-slate-500">{{ $attendance->comment }}</span>
                            @endif
                            @if(! $attendance->justification && ! $attendance->comment)
                                <span class="text-slate-400">{{ __('Aucun justificatif') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-ui.empty
                                :title="__('Aucune présence enregistrée')"
                                :description="__('Aucune séance ne correspond à votre périmètre et aux filtres sélectionnés.')"
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($attendances->hasPages())
        <div class="mt-4">{{ $attendances->links() }}</div>
    @endif
@endsection
