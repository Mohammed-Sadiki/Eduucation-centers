@extends('layouts.app')

@section('title', __('Tableau de bord') . ' - ' . config('app.name'))

@php
    $user = auth()->user();
    $centerName = $student?->center?->name ?? $user->center?->name;
    $attendanceRate = number_format((float) ($attendanceStats['rate'] ?? 0), 1, ',', ' ');
    $financeTone = [
        'paid' => 'success',
        'partial' => 'warning',
        'pending' => 'info',
        'overdue' => 'danger',
        'none' => 'neutral',
    ][$finance['status'] ?? 'none'] ?? 'neutral';
@endphp

@section('content')
    {{-- En-tête personnel --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                <x-icon name="academic" class="h-3.5 w-3.5" />
                {{ __('Espace élève') }}
            </div>
            <h1 class="ec-page-title text-slate-900 dark:text-white">
                {{ __('Bonjour,') }} {{ $user->name }}
            </h1>
            <p class="mt-1.5 text-base text-slate-500 dark:text-slate-400">
                {{ $centerName ?: __('Centre de formation') }}
                @if($student?->student_number)
                    <span class="mx-2 text-slate-300 dark:text-slate-600">•</span>
                    <span class="font-mono text-sm">{{ $student->student_number }}</span>
                @endif
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-2xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
            <x-icon name="calendar" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
            {{ now()->translatedFormat('d F Y') }}
        </div>
    </div>

    @unless($student)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            {{ __('Votre dossier étudiant n’est pas encore lié à ce compte. Contactez votre centre pour finaliser votre inscription.') }}
        </div>
    @endunless

    {{-- Indicateurs strictement personnels --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat
            :label="__('Formations suivies')"
            :value="format_number($formations->count())"
            icon="book"
            tone="primary"
            :hint="__('inscriptions acceptées')"
        />
        <x-ui.stat
            :label="__('Séances à venir')"
            :value="format_number($upcomingCount)"
            icon="calendar"
            tone="info"
            :hint="__('emploi du temps personnel')"
        />
        <x-ui.stat
            :label="__('Taux de présence')"
            :value="$attendanceRate.' %'"
            icon="check"
            tone="success"
            :hint="__('sur toutes mes séances')"
        />
        <x-ui.stat
            :label="__('Moyenne générale')"
            :value="$average !== null ? format_number($average, 2).' / 20' : '—'"
            icon="chart"
            tone="warning"
            :hint="__('notes publiées')"
        />
    </div>

   {{-- Situation financière personnelle (module Finance) --}}
    <section class="ec-panel mb-6">
        <div class="ec-panel__head">
            <div class="ec-panel__title">
                <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    <x-icon name="banknotes" class="h-5 w-5" />
                </span>
                {{ __('Situation financière') }}
            </div>
            <a href="{{ route('student.payments') }}" class="ec-btn-ghost ec-btn-sm">
                {{ __('Mes paiements') }}
                <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        </div>

        @if(($finance['status'] ?? 'none') === 'none')
            <x-ui.empty
                :title="__('Aucun paiement à afficher')"
                :description="__('Aucune inscription générant une situation financière n’est associée à votre compte.')"
            />
        @else
            @if($finance['has_alert'])
                <div class="mx-5 mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                    <span class="font-semibold">{{ __('Paiement en retard') }}</span>
                    @if($finance['overdue_invoices']->isNotEmpty())
                        @php $firstOverdue = $finance['overdue_invoices']->first(); @endphp
                        — {{ __('Votre échéance :formation du :date n’a pas encore été réglée. Montant exigible : :montant. Veuillez contacter l’administration de votre centre.', [
                            'formation' => $firstOverdue->formation,
                            'date' => $firstOverdue->due_date ? format_date($firstOverdue->due_date, 'd/m/Y') : __('(sans date)'),
                            'montant' => format_price($firstOverdue->amount, $finance['currency']),
                        ]) }}
                    @endif
                </div>
            @endif

            <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg bg-slate-50 px-3.5 py-3 dark:bg-slate-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Total') }}</p>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ format_price($finance['total_expected'], $finance['currency']) }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 px-3.5 py-3 dark:bg-emerald-500/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">{{ __('Payé') }}</p>
                    <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-200">{{ format_price($finance['total_paid'], $finance['currency']) }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 px-3.5 py-3 dark:bg-amber-500/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300">{{ __('Restant') }}</p>
                    <p class="mt-1 text-lg font-bold text-amber-700 dark:text-amber-200">{{ format_price($finance['total_remaining'], $finance['currency']) }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 px-3.5 py-3 dark:bg-blue-500/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-300">{{ __('Prochaine échéance') }}</p>
                    <p class="mt-1 text-sm font-bold text-blue-800 dark:text-blue-200">
                        {{ $finance['next_echeance'] ? format_date($finance['next_echeance']->due_date, 'd/m/Y') : '—' }}
                    </p>
                    <div class="mt-1.5">
                        <x-ui.badge :tone="$financeTone">{{ $finance['status_label'] }}</x-ui.badge>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        {{-- Prochain cours --}}
        <section class="ec-panel xl:col-span-2">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="calendar" class="h-5 w-5" />
                    </span>
                    {{ __('Prochain cours') }}
                </div>
                <a href="{{ route('student.schedule') }}" class="ec-btn-ghost ec-btn-sm">
                    {{ __('Mon emploi du temps') }}
                    <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                </a>
            </div>

            @if($nextSession)
                <div class="p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-brand-600 dark:text-brand-300">
                                {{ format_date($nextSession->date, 'D d M Y') }} · {{ $nextSession->formatted_time }}
                            </p>
                            <h2 class="mt-1.5 truncate text-xl font-bold text-slate-900 dark:text-white">
                                {{ $nextSession->title ?: $nextSession->classRoom?->perimeter_label }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $nextSession->classRoom?->formation?->general_formation_name ?? __('Formation') }}
                                @if($nextSession->classRoom?->name)
                                    <span class="mx-1.5 text-slate-300 dark:text-slate-600">•</span>
                                    {{ $nextSession->classRoom->perimeter_label }}
                                @endif
                            </p>
                        </div>
                        <x-status-badge type="schedule_type" :status="$nextSession->type" />
                    </div>

                    <div class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Professeur') }}</p>
                            <p class="mt-0.5 truncate font-medium text-slate-800 dark:text-slate-100">{{ $nextSession->teacher?->full_name ?? '—' }}</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Salle') }}</p>
                            <p class="mt-0.5 truncate font-medium text-slate-800 dark:text-slate-100">{{ $nextSession->room?->name ?? '—' }}</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Type') }}</p>
                            <p class="mt-0.5 truncate font-medium text-slate-800 dark:text-slate-100">{{ $nextSession->type_label }}</p>
                        </div>
                    </div>
                </div>
            @else
                <x-ui.empty
                    :title="__('Aucun cours à venir')"
                    :description="__('Votre emploi du temps ne contient aucune séance planifiée pour le moment.')"
                />
            @endif
        </section>

        {{-- Formations et classes --}}
        <section class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-turquoise-50 text-turquoise-600 dark:bg-turquoise-500/15 dark:text-turquoise-400">
                        <x-icon name="book" class="h-5 w-5" />
                    </span>
                    {{ __('Mes formations') }}
                </div>
                <a href="{{ route('student.formations') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Voir') }}</a>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($formations as $formation)
                    @php
                        $formationEnrollments = $acceptedEnrollments->where('formation_id', $formation->id);
                    @endphp
                    <div class="px-5 py-3.5">
                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $formation->general_formation_name }}</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ $formationEnrollments->pluck('classRoom.name')->filter()->unique()->join(', ') ?: __('Classe non affectée') }}
                        </p>
                    </div>
                @empty
                    <x-ui.empty
                        :title="__('Aucune formation')"
                        :description="__('Aucune inscription acceptée n’est associée à votre compte.')"
                    />
                @endforelse
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Dernières notes --}}
        <section class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">
                        <x-icon name="chart" class="h-5 w-5" />
                    </span>
                    {{ __('Dernières notes publiées') }}
                </div>
                <a href="{{ route('student.evaluations') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Tout voir') }}</a>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($latestGrades as $grade)
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ $grade->evaluation?->title ?? '—' }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $grade->evaluation?->formation?->general_formation_name ?? '' }}
                                @if($grade->evaluation?->evaluation_date)
                                    · {{ format_date($grade->evaluation->evaluation_date, 'd/m/Y') }}
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-end">
                            @if($grade->is_absent)
                                <x-ui.badge tone="danger">{{ __('Absent') }}</x-ui.badge>
                            @elseif($grade->grade !== null)
                                <span class="font-bold text-slate-900 dark:text-white">{{ format_number($grade->grade, 2) }}</span>
                                <span class="text-xs text-slate-400">/ {{ format_number($grade->evaluation?->max_grade, 2) }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-ui.empty :title="__('Aucune note publiée')" />
                @endforelse
            </div>
        </section>

        {{-- Notifications --}}
        <section class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                        <x-icon name="bell" class="h-5 w-5" />
                    </span>
                    {{ __('Notifications récentes') }}
                    @if($unreadNotificationsCount > 0)
                        <x-ui.badge tone="danger">{{ $unreadNotificationsCount }}</x-ui.badge>
                    @endif
                </div>
                <a href="{{ route('notifications.index') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Tout voir') }}</a>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($notifications as $notification)
                    <div class="px-5 py-3">
                        <p class="truncate text-sm font-semibold {{ $notification->read_at ? 'text-slate-600 dark:text-slate-300' : 'text-slate-900 dark:text-white' }}">
                            {{ $notification->title }}
                        </p>
                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $notification->message }}</p>
                    </div>
                @empty
                    <x-ui.empty :title="__('Aucune notification')" />
                @endforelse
            </div>
        </section>

        {{-- Documents --}}
        <section class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                        <x-icon name="document" class="h-5 w-5" />
                    </span>
                    {{ __('Mes documents') }}
                </div>
                <a href="{{ route('student.documents') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Tout voir') }}</a>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($documents as $document)
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ $document->title }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $document->type_label }} · {{ format_date($document->created_at, 'd/m/Y') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @if($document->isPrintable())
                                <a href="{{ route('student.documents.preview', $document) }}" target="_blank" rel="noopener" class="ec-btn-ghost ec-btn-sm" title="{{ __('Imprimer') }}">
                                    <x-icon name="printer" class="h-4 w-4" />
                                </a>
                            @endif
                            <a href="{{ route('student.documents.download', $document) }}" class="ec-btn-secondary ec-btn-sm" title="{{ __('Télécharger') }}">
                                <x-icon name="download" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                @empty
                    <x-ui.empty :title="__('Aucun document')" />
                @endforelse
            </div>
        </section>
    </div>

    @if($certificates->isNotEmpty())
        <section class="ec-panel mt-6">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
                        <x-icon name="certificate" class="h-5 w-5" />
                    </span>
                    {{ __('Mes certificats') }}
                </div>
                <a href="{{ route('student.certificates') }}" class="ec-btn-ghost ec-btn-sm">{{ __('Tout voir') }}</a>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($certificates->take(3) as $certificate)
                    <div class="rounded-xl border border-slate-200 p-3.5 dark:border-slate-700">
                        <p class="font-mono text-xs font-semibold text-brand-600 dark:text-brand-300">{{ $certificate->certificate_number }}</p>
                        <p class="mt-1 truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $certificate->title ?: $certificate->type_label }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ format_date($certificate->issued_date, 'd/m/Y') }}</p>
                        <div class="mt-3 flex gap-2">
                            <a href="{{ route('student.certificates.print', $certificate) }}" target="_blank" rel="noopener" class="ec-btn-secondary ec-btn-sm">
                                <x-icon name="printer" class="h-3.5 w-3.5" /> {{ __('Imprimer') }}
                            </a>
                            <a href="{{ route('student.certificates.download', $certificate) }}" class="ec-btn-ghost ec-btn-sm">
                                <x-icon name="download" class="h-3.5 w-3.5" />
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
