@extends('layouts.app')

@section('title', __('Tableau de bord') . ' — ' . config('app.name'))

@php
    $user = auth()->user();
    $centerName = $center?->name ?? $user->center?->name;
    $salaryTone = [
        'paid' => 'success',
        'partial' => 'warning',
        'pending' => 'info',
        'overdue' => 'danger',
        'none' => 'neutral',
    ][$finance['status'] ?? 'none'] ?? 'neutral';
    $currentSalary = $finance['current'] ?? null;
@endphp

@section('content')

    {{-- ── EN-TÊTE ────────────────────────────────────────────────────── --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="ec-page-title text-slate-900 dark:text-white">
                {{ __('Bonjour,') }} {{ $user->name }} 👋
            </h1>
            <p class="mt-1.5 text-base text-slate-500 dark:text-slate-400">
                {{ __('Espace professeur') }} · {{ $centerName ?? '' }}
            </p>
            @if(!empty($domainLabels))
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach($domainLabels as $slug => $label)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                            <x-icon name="academic" class="h-3 w-3" /> {{ $label }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="flex shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-2xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
            <x-icon name="calendar" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
            {{ now()->translatedFormat('d F Y') }}
        </div>
    </div>

    {{-- ── KPI (calculés à partir des affectations réelles) ───────────── --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat :label="__('Mes classes')" :value="format_number($classes->count())" icon="classes" tone="primary" :hint="__('assignées')" />
        <x-ui.stat :label="__('Étudiants')" :value="format_number($studentCount)" icon="user" tone="success" :hint="__('dans mes classes')" />
        <x-ui.stat :label="__('Séances du jour')" :value="format_number($todaySessions->count())" icon="clock" tone="info" :hint="__('aujourd\'hui')" />
        <x-ui.stat :label="__('Évaluations à corriger')" :value="format_number($evaluationsToCorrect->count())" icon="chart" tone="warning" :hint="__('notes à saisir')" />
        <x-ui.stat :label="__('Notifications')" :value="format_number($unreadNotificationsCount)" icon="bell" tone="danger" :hint="__('non lues')" />
    </div>

    {{-- ── MON SALAIRE (module Finance) ─────────────────────────────── --}}
    <section class="ec-panel mb-6">
        <div class="ec-panel__head">
            <div class="ec-panel__title">
                <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    <x-icon name="banknotes" class="h-5 w-5" />
                </span>
                {{ __('Mon salaire') }}
            </div>
            <a href="{{ route('teacher.salaries') }}" class="ec-btn-ghost ec-btn-sm">
                {{ __('Mes salaires') }}
                <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        </div>

        @if($currentSalary === null)
            <x-ui.empty
                :title="__('Aucun salaire enregistré')"
                :description="__('Aucune fiche de salaire n’est encore associée à votre compte.')"
            />
        @else
            @if($finance['has_alert'])
                <div class="mx-5 mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                    <span class="font-semibold">{{ __('Salaire en attente de versement') }}</span>
                    — {{ __('Votre salaire de :periode est en attente de versement.', ['periode' => $currentSalary->month_name.' '.$currentSalary->year]) }}
                </div>
            @endif

            <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg bg-slate-50 px-3.5 py-3 dark:bg-slate-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Période') }}</p>
                    <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ $currentSalary->month_name }} {{ $currentSalary->year }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 px-3.5 py-3 dark:bg-blue-500/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-300">{{ __('Montant dû') }}</p>
                    <p class="mt-1 text-lg font-bold text-blue-800 dark:text-blue-200">{{ format_price($currentSalary->expected_amount, $currentSalary->currency) }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 px-3.5 py-3 dark:bg-emerald-500/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">{{ __('Montant versé') }}</p>
                    <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-200">{{ format_price($currentSalary->paid_amount, $currentSalary->currency) }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 px-3.5 py-3 dark:bg-amber-500/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300">{{ __('Date prévue / réelle') }}</p>
                    <p class="mt-1 text-sm font-bold text-amber-800 dark:text-amber-200">
                        @if($currentSalary->payment_date)
                            {{ format_date($currentSalary->payment_date, 'd/m/Y') }}
                        @elseif($currentSalary->scheduled_payment_date)
                            {{ __('Prévue le') }} {{ format_date($currentSalary->scheduled_payment_date, 'd/m/Y') }}
                        @else
                            —
                        @endif
                    </p>
                    <div class="mt-1.5">
                        <x-ui.badge :tone="$salaryTone">{{ $currentSalary->personal_status_label }}</x-ui.badge>
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- ── CONTENU ────────────────────────────────────────────────────── --}}
    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Séances du jour --}}
        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300">
                        <x-icon name="clock" class="h-5 w-5" />
                    </span>
                    {{ __('Séances du jour') }}
                </div>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($todaySessions as $s)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="flex w-16 shrink-0 flex-col items-center rounded-lg bg-sky-50 py-1.5 text-center dark:bg-sky-500/15">
                            <span class="text-xs font-bold text-sky-700 dark:text-sky-300" dir="ltr">{{ substr((string) $s->start_time, 0, 5) }}</span>
                            <span class="text-2xs text-slate-400" dir="ltr">{{ substr((string) $s->end_time, 0, 5) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $s->title ?: $s->classRoom?->perimeter_label }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $s->classRoom?->perimeter_label ?? '—' }}
                                @if($s->room) · {{ $s->room->name }} @endif
                            </p>
                        </div>
                        <x-status-badge type="schedule" :status="$s->status" />
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucune séance aujourd\'hui.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Prochaines séances --}}
        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                        <x-icon name="calendar" class="h-5 w-5" />
                    </span>
                    {{ __('Prochaines séances') }}
                </div>
                <a href="{{ route('teacher.schedule') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Mon emploi du temps') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($upcoming as $s)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="flex w-16 shrink-0 flex-col items-center rounded-lg bg-brand-50 py-1.5 text-center dark:bg-brand-500/15">
                            <span class="text-xs font-bold text-brand-700 dark:text-brand-300" dir="ltr">{{ substr((string) $s->start_time, 0, 5) }}</span>
                            <span class="text-2xs text-slate-400">{{ format_date($s->date, 'd/m') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $s->classRoom?->perimeter_label ?? '—' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $s->classRoom?->formation?->general_formation_name ?? '—' }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-medium text-slate-400">{{ format_date($s->date, 'd/m/Y') }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucune séance planifiée.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Évaluations à corriger --}}
        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                        <x-icon name="chart" class="h-5 w-5" />
                    </span>
                    {{ __('Évaluations à corriger') }}
                </div>
                <a href="{{ route('evaluations.index') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Voir tout') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($evaluationsToCorrect as $evaluation)
                    <a href="{{ route('evaluations.show', $evaluation) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $evaluation->title }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $evaluation->classRoom?->perimeter_label ?? '—' }} · {{ format_date($evaluation->evaluation_date, 'd/m/Y') }}
                            </p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-amber-600 dark:text-amber-400">
                            {{ format_number($evaluation->graded_count ?? 0) }} / {{ format_number($evaluation->classRoom?->accepted_enrollments_count ?? 0) }}
                        </span>
                        <x-status-badge type="evaluation" :status="$evaluation->status" />
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucune évaluation à corriger.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Mes classes --}}
        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="classes" class="h-5 w-5" />
                    </span>
                    {{ __('Mes classes') }}
                </div>
                <a href="{{ route('teacher.classes') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Voir tout') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($classes as $class)
                    <a href="{{ route('teacher.classes.show', $class) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <span class="ec-icon-chip bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                            <x-icon name="book" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $class->perimeter_label }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $class->formation?->general_formation_name ?? '—' }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-medium text-slate-500">
                            {{ format_number($class->accepted_enrollments_count ?? 0) }} {{ __('étudiants') }}
                        </span>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucune classe affectée.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Dernières notifications --}}
        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300">
                        <x-icon name="bell" class="h-5 w-5" />
                    </span>
                    {{ __('Dernières notifications') }}
                </div>
                <a href="{{ route('notifications.index') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Voir tout') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($notifications as $notification)
                    <div class="flex items-start gap-3 px-5 py-3">
                        <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-slate-300 dark:bg-slate-600' : 'bg-brand-600' }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $notification->title }}</p>
                            <p class="line-clamp-2 text-xs text-slate-500">{{ $notification->message }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-medium text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucune notification.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

@endsection
