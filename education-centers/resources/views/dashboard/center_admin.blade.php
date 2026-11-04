@extends('layouts.app')

@section('title', __('Tableau de bord') . ' — ' . config('app.name'))

@php
    $user = auth()->user();
    $centerName = $user->center?->name ?? '';
@endphp

@section('content')

    {{-- ── EN-TÊTE ────────────────────────────────────────────────────── --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="ec-page-title text-slate-900 dark:text-white">
                {{ __('Bonjour,') }} {{ $user->name }} 👋
            </h1>
            <p class="mt-1.5 text-base text-slate-500 dark:text-slate-400">
                {{ __('Gestion du centre') }} · {{ $centerName }}
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-2xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
            <x-icon name="calendar" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
            {{ now()->translatedFormat('d F Y') }}
        </div>
    </div>

    {{-- ── KPI ────────────────────────────────────────────────────────── --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Étudiants')" :value="format_number($stats['students'])" icon="academic" tone="primary" :hint="__('inscrits')" />
        <x-ui.stat :label="__('Professeurs')" :value="format_number($stats['teachers'])" icon="user" tone="success" :hint="__('actifs')" />
        <x-ui.stat :label="__('Inscriptions acceptées')" :value="format_number($stats['enrollments_accepted'])" icon="clipboard" tone="info" :hint="__('validées')" />
        <x-ui.stat :label="__('Revenus')" :value="format_price($stats['payments_total'])" icon="banknotes" tone="warning" :hint="__('encaissés')" />
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Formations')" :value="format_number($stats['formations'])" icon="book" tone="primary" />
        <x-ui.stat :label="__('Classes')" :value="format_number($stats['classes'])" icon="classes" tone="info" />
        <x-ui.stat :label="__('En attente')" :value="format_number($stats['enrollments_pending'])" icon="clock" tone="warning" :hint="__('à traiter')" />
        <x-ui.stat :label="__('Factures en attente')" :value="format_price($stats['invoices_pending'])" icon="document" tone="danger" :hint="__('à recouvrer')" />
    </div>

    {{-- ── GRAPHIQUE + RÉSUMÉ ─────────────────────────────────────────── --}}
    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        <div class="ec-panel xl:col-span-2">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="chart" class="h-5 w-5" />
                    </span>
                    {{ __('Aperçu des ressources') }}
                </div>
                <span class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $centerName }}</span>
            </div>
            <div class="p-4 sm:p-5">
                <div class="h-72"><canvas id="centerResourceChart"></canvas></div>
            </div>
        </div>

        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-turquoise-50 text-turquoise-600 dark:bg-turquoise-500/15 dark:text-turquoise-400">
                        <x-icon name="bell" class="h-5 w-5" />
                    </span>
                    {{ __('Notifications') }}
                </div>
            </div>
            <div class="divide-y divide-slate-100 p-2 dark:divide-slate-800">
                @forelse($notifications as $n)
                    <div class="px-3 py-3">
                        <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ $n->title }}</p>
                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ $n->message }}</p>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-slate-500">{{ __('Aucune notification.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── SÉANCES + INSCRIPTIONS ─────────────────────────────────────── --}}
    <div class="grid gap-6 xl:grid-cols-2">
        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                        <x-icon name="calendar" class="h-5 w-5" />
                    </span>
                    {{ __('Prochaines séances') }}
                </div>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($upcoming as $s)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="flex w-14 shrink-0 flex-col items-center rounded-lg bg-brand-50 py-1.5 text-center dark:bg-brand-500/15">
                            <span class="text-xs font-bold text-brand-700 dark:text-brand-300">{{ substr($s->start_time, 0, 5) }}</span>
                            <span class="text-2xs text-slate-400">{{ substr($s->end_time, 0, 5) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $s->classRoom?->perimeter_label ?? '—' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $s->teacher?->user?->name ?? $s->teacher?->full_name ?? '—' }} · {{ $s->room?->code ?? '—' }}</p>
                        </div>
                        <span class="shrink-0 text-xs text-slate-400">{{ format_date($s->date, 'd/m') }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucune séance à venir.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="ec-panel">
            <div class="ec-panel__head">
                <div class="ec-panel__title">
                    <span class="ec-icon-chip bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">
                        <x-icon name="clipboard" class="h-5 w-5" />
                    </span>
                    {{ __('Dernières inscriptions') }}
                </div>
                <a href="{{ route('enrollments.index') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">{{ __('Voir tout') }}</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($recentEnrollments as $e)
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $e->student?->full_name ?? '—' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $e->formation?->general_formation_name ?? '—' }}</p>
                        </div>
                        <x-status-badge type="enrollment" :status="$e->status" />
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucune inscription récente.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const el = document.getElementById('centerResourceChart');
    if (!el) return;
    const isDark = document.documentElement.classList.contains('dark');
    const tickColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(148,163,184,0.15)' : 'rgba(148,163,184,0.2)';
    new Chart(el, {
        type: 'bar',
        data: {
            labels: @js([__('Étudiants'), __('Professeurs'), __('Formations'), __('Classes'), __('Salles')]),
            datasets: [{
                label: @js(__('Effectif')),
                data: @js([
                    (int) ($stats['students'] ?? 0),
                    (int) ($stats['teachers'] ?? 0),
                    (int) ($stats['formations'] ?? 0),
                    (int) ($stats['classes'] ?? 0),
                    (int) ($stats['rooms'] ?? 0),
                ]),
                backgroundColor: ['#4f46e5', '#0dad98', '#6366f1', '#f59e0b', '#ec4899'],
                borderRadius: 8,
                maxBarThickness: 46,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 700, easing: 'easeOutQuart' },
            plugins: { legend: { display: false }, tooltip: { animation: { duration: 150 } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: tickColor } },
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
            },
        },
    });
});
</script>
@endpush
