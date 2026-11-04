@extends('layouts.app')

@section('title', __('Tableau de bord') . ' — ' . config('app.name'))

@section('content')
    <x-ui.page-header :title="__('Tableau de bord')" :description="__('Vue d’ensemble de tous les centres — Super Admin.')" />

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Centres')" :value="$stats['centers']" icon="building" />
        <x-ui.stat :label="__('Étudiants')" :value="$stats['students']" icon="academic" tone="info" />
        <x-ui.stat :label="__('Professeurs')" :value="$stats['teachers']" icon="user" tone="success" />
        <x-ui.stat :label="__('Formations')" :value="$stats['formations']" icon="book" />
        <x-ui.stat :label="__('Classes')" :value="$stats['classes']" icon="classes" tone="info" />
        <x-ui.stat :label="__('Inscriptions')" :value="$stats['enrollments']" icon="clipboard" />
        <x-ui.stat :label="__('En attente')" :value="$stats['pending_enrollments']" icon="clock" tone="warning" />
        <x-ui.stat :label="__('Revenus')" :value="format_price($stats['payments_total'])" icon="banknotes" tone="success" />
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card :padding="false">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="font-semibold">{{ __('Dernières inscriptions') }}</h2>
            </div>
            <div class="ec-table-wrap border-0">
                <table class="ec-table">
                    <thead>
                        <tr>
                            <th>{{ __('Étudiant') }}</th>
                            <th>{{ __('Formation') }}</th>
                            <th>{{ __('Centre') }}</th>
                            <th>{{ __('Statut') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEnrollments as $e)
                            <tr>
                                <td>{{ $e->student?->full_name ?? '—' }}</td>
                                <td>{{ $e->formation?->general_formation_name ?? '—' }}</td>
                                <td>{{ $e->center?->name ?? '—' }}</td>
                                <td><x-status-badge type="enrollment" :status="$e->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-500">{{ __('Aucune inscription récente.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <x-ui.card :padding="false">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="font-semibold">{{ __('Activité par centre') }}</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($centers as $center)
                    <div class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ $center->translated('name') }}</p>
                            <p class="text-xs text-slate-500">{{ $center->students_count }} {{ __('étudiants ·') }} {{ $center->formations_count }} {{ __('formations') }}</p>
                        </div>
                        <span class="text-slate-500">{{ $center->enrollments_count }} {{ __('inscriptions') }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('Aucun centre.') }}</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
@endsection
