@extends('layouts.app')

@section('title', $class->perimeter_label)

@php
    $centerName = $class->center?->name ?? auth()->user()->center?->name;
@endphp

@section('content')
    <x-ui.page-header
        :title="$class->perimeter_label"
        :description="($class->formation?->general_formation_name ?? __('Formation')) . ' · ' . ($domainLabel ?? '—') . ' · ' . ($centerName ?? '')"
    >
        <a href="{{ route('teacher.classes') }}" class="ec-btn-secondary">
            <x-icon name="arrow-uturn-left" class="h-4 w-4 rtl:rotate-180" /> {{ __('Retour') }}
        </a>
        <a href="{{ route('teacher.schedule', ['class_id' => $class->id]) }}" class="ec-btn-primary">
            <x-icon name="calendar" class="h-4 w-4" /> {{ __('Emploi du temps') }}
        </a>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Étudiants inscrits')" :value="format_number($students->count())" icon="user" tone="primary" :hint="$class->capacity ? __('Capacité') . ' : ' . format_number($class->capacity) : null" />
        <x-ui.stat :label="__('Séances programmées')" :value="format_number($schedules->count())" icon="calendar" tone="info" :hint="__('votre classe')" />
        <x-ui.stat :label="__('Évaluations')" :value="format_number($evaluations->count())" icon="chart" tone="warning" :hint="__('10 dernières')" />
        <x-ui.stat :label="__('Formation générale')" :value="$domainLabel ?? '—'" icon="academic" tone="success" :hint="__('Formation')" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- ── Étudiants de la classe ─────────────────────────────── --}}
        <div class="ec-panel xl:col-span-2">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="user" class="h-5 w-5" />
                    </span>
                    {{ __('Liste des étudiants') }}
                </div>
                <a href="{{ route('attendances.create') }}" class="ec-btn-secondary ec-btn-sm">
                    <x-icon name="clock" class="h-3.5 w-3.5" /> {{ __('Saisir les présences') }}
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="ec-table">
                    <thead>
                        <tr>
                            <th>{{ __('Étudiant') }}</th>
                            <th>{{ __('Matricule') }}</th>
                            <th>{{ __('Statut') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $student)
                            <tr>
                                <td>
                                    <span class="font-medium text-slate-800 dark:text-slate-100">{{ $student->last_name }} {{ $student->first_name }}</span>
                                </td>
                                <td class="font-mono text-xs text-slate-500">{{ $student->student_number }}</td>
                                <td><x-status-badge type="generic" :status="$student->status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <x-ui.empty
                                        :title="__('Aucun étudiant')"
                                        :description="__('Aucun étudiant accepté dans cette classe pour le moment.')"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            {{-- ── Informations ───────────────────────────────────── --}}
            <div class="ec-card p-5">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Informations') }}</h3>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('Code') }}</dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-100">{{ $class->code ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('Formation') }}</dt>
                        <dd class="truncate font-medium text-slate-800 dark:text-slate-100">{{ $class->formation?->general_formation_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('Centre') }}</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $centerName ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('Période') }}</dt>
                        <dd class="text-slate-800 dark:text-slate-100">
                            {{ $class->start_date ? format_date($class->start_date, 'd/m/Y') : '—' }}
                            <span class="text-slate-400">→</span>
                            {{ $class->end_date ? format_date($class->end_date, 'd/m/Y') : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('Professeur(s)') }}</dt>
                        <dd class="text-end font-medium text-slate-800 dark:text-slate-100">
                            {{ $class->teachers?->pluck('full_name')->filter()->implode(', ') ?: '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- ── Séances de la classe ────────────────────────────── --}}
            <div class="ec-panel">
                <div class="ec-panel__head">
                    <div class="ec-panel__title">
                        <span class="ec-icon-chip bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                            <x-icon name="calendar" class="h-5 w-5" />
                        </span>
                        {{ __('Séances') }}
                    </div>
                    <span class="text-xs font-semibold text-slate-500">{{ $schedules->count() }}</span>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($schedules->take(8) as $schedule)
                        <div class="flex items-center gap-3 px-5 py-3 text-sm">
                            <div class="flex w-16 shrink-0 flex-col items-center rounded-lg bg-brand-50 py-1.5 text-center dark:bg-brand-500/15">
                                <span class="text-xs font-bold text-brand-700 dark:text-brand-300" dir="ltr">{{ substr((string) $schedule->start_time, 0, 5) }}</span>
                                <span class="text-2xs text-slate-400" dir="ltr">{{ substr((string) $schedule->end_time, 0, 5) }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-slate-800 dark:text-white">{{ $schedule->title ?: $class->perimeter_label }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ format_date($schedule->date, 'd/m/Y') }}
                                    @if($schedule->room) · {{ $schedule->room->name }} @endif
                                    · {{ $schedule->type_label }}
                                </p>
                            </div>
                            <x-status-badge type="schedule" :status="$schedule->status" />
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucune séance programmée pour cette classe.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ── Évaluations de la classe ───────────────────────────────── --}}
    <div class="ec-panel mt-6">
        <div class="ec-panel__head">
            <div class="ec-panel__title">
                <span class="ec-icon-chip bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                    <x-icon name="chart" class="h-5 w-5" />
                </span>
                {{ __('Évaluations & notes') }}
            </div>
            <a href="{{ route('evaluations.create', ['class_id' => $class->id]) }}" class="ec-btn-primary ec-btn-sm">
                <x-icon name="check" class="h-3.5 w-3.5" /> {{ __('Créer une évaluation') }}
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="ec-table">
                <thead>
                    <tr>
                        <th>{{ __('Titre') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Note max') }}</th>
                        <th>{{ __('Moyenne') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($evaluations as $evaluation)
                        <tr>
                            <td class="font-medium text-slate-800 dark:text-slate-100">{{ $evaluation->title }}</td>
                            <td>{{ $evaluation->type_label }}</td>
                            <td>{{ format_date($evaluation->evaluation_date, 'd/m/Y') }}</td>
                            <td>{{ format_number($evaluation->max_grade) }}</td>
                            <td>{{ $evaluation->average_grade !== null ? format_number(round($evaluation->average_grade, 2)) : '—' }}</td>
                            <td><x-status-badge type="evaluation" :status="$evaluation->status" /></td>
                            <td class="text-end">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('evaluations.show', $evaluation) }}" class="ec-btn-ghost ec-btn-sm">{{ __('Voir') }}</a>
                                    <a href="{{ route('evaluations.edit', $evaluation) }}" class="ec-btn-secondary ec-btn-sm">{{ __('Saisir les notes') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-ui.empty
                                    :title="__('Aucune évaluation')"
                                    :description="__('Vous n’avez pas encore créé d’évaluation pour cette classe.')"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
