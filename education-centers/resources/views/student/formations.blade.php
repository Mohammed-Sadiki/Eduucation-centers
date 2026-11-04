@extends('layouts.app')

@section('title', __('Mes formations'))

@php
    $user = auth()->user();
    $centerName = $student?->center?->name ?? $user->center?->name;
    $acceptedCount = $enrollments->where('status', \App\Models\Enrollment::STATUS_ACCEPTED)->count();
    $pendingCount = $enrollments->whereIn('status', [\App\Models\Enrollment::STATUS_PENDING, \App\Models\Enrollment::STATUS_UNDER_REVIEW])->count();
    $domainCount = $enrollments
        ->pluck('formation.domain')
        ->filter()
        ->unique()
        ->count();
@endphp

@section('content')
    <x-ui.page-header
        :title="__('Mes formations')"
        :description="__('Vos inscriptions, classes, professeurs et périodes de formation.')"
    >
        <a href="{{ route('student.schedule') }}" class="ec-btn-secondary">
            <x-icon name="calendar" class="h-4 w-4" />
            {{ __('Mon emploi du temps') }}
        </a>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Formations')" :value="format_number($enrollments->count())" icon="book" tone="primary" :hint="$centerName" />
        <x-ui.stat :label="__('Inscriptions acceptées')" :value="format_number($acceptedCount)" icon="check" tone="success" :hint="__('accès aux cours et notes')" />
        <x-ui.stat :label="__('En attente')" :value="format_number($pendingCount)" icon="clock" tone="warning" :hint="__('traitement administratif')" />
        <x-ui.stat :label="__('Formations générales suivies')" :value="format_number($domainCount)" icon="academic" tone="info" :hint="__('parcours personnels')" />
    </div>

    @if($enrollments->isEmpty())
        <x-ui.card>
            <x-ui.empty
                :title="__('Aucune formation')"
                :description="__('Aucune inscription n’est encore associée à votre compte. Votre centre vous informera dès qu’une formation sera disponible.')"
            />
        </x-ui.card>
    @else
        <div class="grid gap-5 xl:grid-cols-2">
            @foreach($enrollments as $enrollment)
                @php
                    $formation = $enrollment->formation;
                    $classRoom = $enrollment->classRoom;
                    $domainLabel = $formation
                        ? ($domainLabels[$formation->domain] ?? ucfirst((string) $formation->domain))
                        : '—';
                    $teachers = $classRoom?->teachers?->pluck('full_name')->filter()->unique() ?? collect();
                @endphp

                <article class="ec-panel overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-brand-600 dark:text-brand-300">
                                    {{ $domainLabel }}
                                </p>
                                <h2 class="mt-1 text-lg font-bold text-slate-900 dark:text-white">
                                    {{ $formation?->general_formation_name ?? __('Formation') }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $enrollment->center?->name ?? $centerName }}
                                    @if($formation?->code)
                                        <span class="mx-1.5 text-slate-300 dark:text-slate-600">•</span>
                                        <span class="font-mono text-xs">{{ $formation->code }}</span>
                                    @endif
                                </p>
                            </div>
                            <x-status-badge type="enrollment" :status="$enrollment->status" />
                        </div>
                    </div>

                    <dl class="grid gap-x-6 gap-y-4 px-5 py-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Classe') }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $classRoom?->perimeter_label ?? __('Non affectée') }}
                                @if($classRoom?->code)
                                    <span class="block text-xs font-normal text-slate-500">{{ $classRoom->code }}</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Période') }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                @if($classRoom?->start_date || $classRoom?->end_date)
                                    {{ $classRoom->start_date ? format_date($classRoom->start_date, 'd/m/Y') : '—' }}
                                    <span class="text-slate-400">→</span>
                                    {{ $classRoom->end_date ? format_date($classRoom->end_date, 'd/m/Y') : '—' }}
                                @elseif($formation?->duration_weeks || $formation?->duration_hours)
                                    {{ $formation->formatted_duration }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Professeur(s)') }}</dt>
                            <dd class="mt-1 flex flex-wrap gap-1.5">
                                @forelse($teachers as $teacherName)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        <x-icon name="user" class="h-3 w-3" />
                                        {{ $teacherName }}
                                    </span>
                                @empty
                                    <span class="text-sm text-slate-500">{{ __('Aucun professeur affecté') }}</span>
                                @endforelse
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Numéro d’inscription') }}</dt>
                            <dd class="mt-0.5 font-mono text-sm text-slate-800 dark:text-slate-100">{{ $enrollment->registration_number ?? '—' }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Année scolaire') }}</dt>
                            <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $enrollment->school_year ?? '—' }}</dd>
                        </div>

                        @if($enrollment->decision_date)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Date de décision') }}</dt>
                                <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ format_date($enrollment->decision_date, 'd/m/Y') }}</dd>
                            </div>
                        @endif

                        @if($enrollment->notes)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Informations') }}</dt>
                                <dd class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ $enrollment->notes }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($enrollment->isAccepted() && $classRoom)
                        <div class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30">
                            <a href="{{ route('student.schedule', ['class_id' => $classRoom->id]) }}" class="ec-btn-secondary ec-btn-sm">
                                <x-icon name="calendar" class="h-3.5 w-3.5" /> {{ __('Emploi du temps') }}
                            </a>
                            <a href="{{ route('student.evaluations', ['class_id' => $classRoom->id]) }}" class="ec-btn-ghost ec-btn-sm">
                                <x-icon name="chart" class="h-3.5 w-3.5" /> {{ __('Mes notes') }}
                            </a>
                            <a href="{{ route('student.attendances', ['class_id' => $classRoom->id]) }}" class="ec-btn-ghost ec-btn-sm">
                                <x-icon name="clock" class="h-3.5 w-3.5" /> {{ __('Mes présences') }}
                            </a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
