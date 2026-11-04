@extends('layouts.app')

@section('title', __('Mes notes'))

@php
    $hasClassFilter = $classes->count() > 1;
    $gradedCount = $evaluations->getCollection()->filter(fn ($evaluation) => $evaluation->grades->isNotEmpty())->count();
    $publishedCount = $evaluations->total();
@endphp

@section('content')
    <x-ui.page-header
        :title="__('Mes notes')"
        :description="__('Vos notes personnelles, uniquement pour les évaluations publiées.').' '.($student?->center?->name ?? auth()->user()->center?->name)"
    >
        <a href="{{ route('student.formations') }}" class="ec-btn-secondary">
            <x-icon name="book" class="h-4 w-4" /> {{ __('Mes formations') }}
        </a>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat
            :label="__('Moyenne générale')"
            :value="$average !== null ? format_number($average, 2).' / 20' : '—'"
            icon="chart"
            tone="primary"
            :hint="__('notes publiées, ramenées sur 20')"
        />
        <x-ui.stat :label="__('Évaluations publiées')" :value="format_number($publishedCount)" icon="document" tone="info" :hint="__('sur toutes les pages')" />
        <x-ui.stat :label="__('Notes disponibles')" :value="format_number($gradedCount)" icon="check" tone="success" :hint="__('page courante')" />
        <x-ui.stat :label="__('Classes suivies')" :value="format_number($classes->count())" icon="academic" tone="neutral" :hint="__('inscriptions acceptées')" />
    </div>

    <form method="GET" action="{{ route('student.evaluations') }}" class="ec-card mb-5 p-4 sm:p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            {{-- Le filtre de classe n'apparaît que si l'élève a réellement plusieurs classes. --}}
            @if($hasClassFilter)
                <label class="block">
                    <span class="ec-label">{{ __('Classe') }}</span>
                    <select name="class_id" class="ec-select">
                        <option value="">{{ __('Toutes mes classes') }}</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" @selected((int) $classId === (int) $class->id)>
                                {{ $class->name }}{{ $class->code ? ' ('.$class->code.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="block">
                <span class="ec-label">{{ __('Type d’évaluation') }}</span>
                <select name="type" class="ec-select">
                    <option value="">{{ __('Tous les types') }}</option>
                    @foreach(['exam' => __('Examen'), 'control' => __('Contrôle'), 'homework' => __('Devoir'), 'oral' => __('Oral'), 'project' => __('Projet'), 'participation' => __('Participation')] as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="ec-btn-primary">
                    <x-icon name="filter" class="h-4 w-4" /> {{ __('Filtrer') }}
                </button>
                @if(request()->filled('class_id') || request()->filled('type'))
                    <a href="{{ route('student.evaluations') }}" class="ec-btn-ghost">{{ __('Réinitialiser') }}</a>
                @endif
            </div>
        </div>
    </form>

    <div class="ec-table-wrap">
        <table class="ec-table">
            <thead>
                <tr>
                    <th>{{ __('Évaluation') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th class="text-end">{{ __('Ma note') }}</th>
                    <th class="text-end">{{ __('Barème') }}</th>
                    <th class="text-end">{{ __('Moyenne classe') }}</th>
                    <th>{{ __('Statut') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($evaluations as $evaluation)
                    @php
                        $grade = $evaluation->grades->first();
                    @endphp
                    <tr>
                        <td>
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $evaluation->title }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $evaluation->formation?->name ?? __('Formation') }}
                                @if($evaluation->classRoom?->name)
                                    · {{ $evaluation->classRoom->name }}
                                @endif
                            </p>
                            @if($evaluation->teacher?->full_name)
                                <p class="mt-0.5 text-xs text-slate-400">{{ $evaluation->teacher->full_name }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">{{ $evaluation->type_label }}</td>
                        <td class="whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">
                            {{ $evaluation->evaluation_date ? format_date($evaluation->evaluation_date, 'd/m/Y') : '—' }}
                        </td>
                        <td class="whitespace-nowrap text-end">
                            @if($grade?->is_absent)
                                <x-ui.badge tone="danger">{{ __('Absent') }}</x-ui.badge>
                            @elseif($grade?->grade !== null)
                                <span class="text-base font-bold text-slate-900 dark:text-white">{{ format_number($grade->grade, 2) }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                                <span class="mt-0.5 block text-xs text-slate-400">{{ __('Non notée') }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-end text-sm text-slate-600 dark:text-slate-300">
                            {{ format_number($evaluation->max_grade, 2) }}
                            @if((float) $evaluation->coefficient !== 1.0)
                                <span class="mt-0.5 block text-xs text-slate-400">×{{ format_number($evaluation->coefficient, 2) }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-end text-sm text-slate-600 dark:text-slate-300">
                            @if($evaluation->grades_avg !== null)
                                {{ format_number($evaluation->grades_avg, 2) }} / {{ format_number($evaluation->max_grade, 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td><x-status-badge type="evaluation" :status="$evaluation->status" /></td>
                    </tr>
                    @if($grade?->comment || $evaluation->description)
                        <tr class="bg-slate-50/50 dark:bg-slate-800/20">
                            <td colspan="7" class="text-sm text-slate-500 dark:text-slate-400">
                                @if($grade?->comment)
                                    <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Commentaire :') }}</span>
                                    {{ $grade->comment }}
                                @endif
                                @if($grade?->comment && $evaluation->description)<span class="mx-2">·</span>@endif
                                @if($evaluation->description){{ $evaluation->description }}@endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7">
                            <x-ui.empty
                                :title="__('Aucune note publiée')"
                                :description="__('Les évaluations apparaissent ici dès que vos professeurs les publient.')"
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($evaluations->hasPages())
        <div class="mt-4">{{ $evaluations->links() }}</div>
    @endif
@endsection
