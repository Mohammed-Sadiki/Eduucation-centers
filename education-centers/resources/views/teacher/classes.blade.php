@extends('layouts.app')

@section('title', __('Mes classes'))

@php
    $centerName = $center?->name ?? auth()->user()->center?->name;
@endphp

@section('content')
    <x-ui.page-header
        :title="__('Mes classes')"
        :description="__('Les classes qui vous sont réellement affectées') . ' · ' . ($centerName ?? '')"
    >
        <a href="{{ route('teacher.schedule') }}" class="ec-btn-secondary">
            <x-icon name="calendar" class="h-4 w-4" /> {{ __('Mon emploi du temps') }}
        </a>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Classes affectées')" :value="format_number($classes->count())" icon="classes" tone="primary" :hint="$centerName" />
        <x-ui.stat :label="__('Étudiants')" :value="format_number($studentCount)" icon="user" tone="success" :hint="__('inscrits dans vos classes')" />
        <x-ui.stat :label="__('Formations générales enseignées')" :value="format_number(count($domainLabels))" icon="academic" tone="info" :hint="implode(' · ', $domainLabels)" />
        <x-ui.stat :label="__('Centre')" :value="$centerName ?? '—'" icon="building" tone="warning" :hint="__('Affectation principale')" />
    </div>

    @if($classes->isEmpty())
        <x-ui.card>
            <x-ui.empty
                :title="__('Aucune classe affectée')"
                :description="__('Aucune classe ne vous est attribuée pour le moment. Contactez l’administrateur de votre centre en cas de besoin.')"
            />
        </x-ui.card>
    @else
        <div class="grid gap-5 xl:grid-cols-2">
            @foreach($classes as $class)
                @php
                    $domainLabel = $class->formation
                        ? ($domainLabels[$class->formation->domain]
                            ?? ($class->formation->domainModel?->name
                                ?? ucfirst(str_replace('-', ' ', (string) $class->formation->domain))))
                        : '—';
                @endphp

                <article class="ec-panel overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-brand-600 dark:text-brand-300">
                                    {{ $domainLabel }}
                                </p>
                                <h2 class="mt-1 text-lg font-bold text-slate-900 dark:text-white">
                                    {{ $class->perimeter_label }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $class->formation?->general_formation_name ?? __('Formation') }}
                                    @if($class->code)
                                        <span class="mx-1.5 text-slate-300 dark:text-slate-600">•</span>
                                        <span class="font-mono text-xs">{{ $class->code }}</span>
                                    @endif
                                </p>
                            </div>
                            <x-status-badge type="generic" :status="$class->status" />
                        </div>
                    </div>

                    <dl class="grid gap-x-6 gap-y-4 px-5 py-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Centre') }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $class->center?->name ?? $centerName ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Étudiants inscrits') }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ format_number($class->accepted_enrollments_count ?? 0) }}
                                @if($class->capacity)
                                    <span class="text-xs font-normal text-slate-50">/ {{ format_number($class->capacity) }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Période') }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                @if($class->start_date || $class->end_date)
                                    {{ $class->start_date ? format_date($class->start_date, 'd/m/Y') : '—' }}
                                    <span class="text-slate-400">→</span>
                                    {{ $class->end_date ? format_date($class->end_date, 'd/m/Y') : '—' }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30">
                        <a href="{{ route('teacher.classes.show', $class) }}" class="ec-btn-primary ec-btn-sm">
                            <x-icon name="eye" class="h-3.5 w-3.5" /> {{ __('Voir la classe') }}
                        </a>
                        <a href="{{ route('teacher.schedule', ['class_id' => $class->id]) }}" class="ec-btn-secondary ec-btn-sm">
                            <x-icon name="calendar" class="h-3.5 w-3.5" /> {{ __('Emploi du temps') }}
                        </a>
                        <a href="{{ route('attendances.index', ['class_id' => $class->id]) }}" class="ec-btn-ghost ec-btn-sm">
                            <x-icon name="clock" class="h-3.5 w-3.5" /> {{ __('Présences') }}
                        </a>
                        <a href="{{ route('evaluations.index', ['class_id' => $class->id]) }}" class="ec-btn-ghost ec-btn-sm">
                            <x-icon name="chart" class="h-3.5 w-3.5" /> {{ __('Évaluations & notes') }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
