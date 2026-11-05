@extends('layouts.portal')

@section('title', ($domain?->name ?? $formation->name) . ' — ' . config('app.name'))
@section('description', __('Espace de la formation générale officielle.'))

@php
    $classesCount = $classes->count();
    $atLimit = $classesCount >= $maxClasses;
@endphp

@section('content')

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5 [direction:ltr]" dir="ltr">
            <x-ui.back-button fallback="{{ route('formations-generales.index', ['center_id' => $formation->center_id]) }}" />
            <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Formation générale officielle') }}</p>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    {{ __($domain->name ?? $formation->name) }}
                    <span class="ms-2 text-base font-normal text-slate-500 dark:text-slate-400">· {{ $formation->center?->name }}</span>
                </h1>
            </div>
        </div>
    </div>

    {{-- KPIs du tableau de bord : Étudiants, Certificats, Présences du jour, Revenus (payés) --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $showFinancialKpi ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Étudiants') }}</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <x-icon name="users" class="h-5 w-5" stroke-width="1.8" />
                </span>
            </div>
            <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">{{ format_number($studentsCount ?? 0) }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('inscrits dans cette formation') }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Certificats') }}</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <x-icon name="certificate" class="h-5 w-5" stroke-width="1.8" />
                </span>
            </div>
            <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">{{ format_number($certificatesIssued ?? 0) }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('délivrés') }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Présences du jour') }}</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                    <x-icon name="clock" class="h-5 w-5" stroke-width="1.8" />
                </span>
            </div>
            <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">{{ format_number($attendancesToday ?? 0) }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('aujourd\'hui') }}</p>
        </div>

        @if($showFinancialKpi)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Revenus (payés)') }}</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                        <x-icon name="banknotes" class="h-5 w-5" stroke-width="1.8" />
                    </span>
                </div>
                <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">{{ format_price($paymentsTotal ?? 0) }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('encaissés') }}</p>
            </div>
        @endif
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Responsable (1 maximum) --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Professeur responsable') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Un seul professeur responsable par formation. L\'assignation reste manuelle.') }}</p>

            @if ($formation->teacher)
                <div class="mt-4 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60">
                    <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $formation->teacher->full_name }}</span>
                    <form method="POST" action="{{ route('formations-generales.teacher.detach', $formation) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">{{ __('Retirer') }}</button>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('formations-generales.teacher.assign', $formation) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Enseignant du centre') }}</label>
                    <select name="teacher_id" required
                            class="mt-1 w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="" disabled selected>{{ __('Sélectionner un professeur…') }}</option>
                        @foreach ($centerTeachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($formation->teacher_id === $teacher->id)>{{ $teacher->full_name }} ({{ $teacher->teacher_number }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    {{ $formation->teacher ? __('Remplacer') : __('Assigner') }}
                </button>
            </form>
            @if ($centerTeachers->isEmpty())
                <p class="mt-3 text-xs text-slate-400">{{ __('Aucun professeur disponible dans ce centre.') }}</p>
            @endif
        </div>

        {{-- Classes (max 2) --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Classes') }}</h2>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $atLimit ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                    {{ $classesCount }}/{{ $maxClasses }}
                </span>
            </div>

            <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($classes as $class)
                    <li class="flex items-center justify-between py-2.5">
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $class->perimeter_label }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $class->code }} · {{ $class->formatted_period }}</p>
                        </div>
                        <span class="text-xs text-slate-400">{{ $class->enrollments_count ?? $class->enrollments()->count() }} {{ __('inscrit(s)') }}</span>
                    </li>
                @empty
                    <li class="py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('Aucune classe pour le moment.') }}</li>
                @endforelse
            </ul>

            @if ($atLimit)
                <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                    {{ __('Limite atteinte : une formation générale accepte au maximum :max classes.', ['max' => $maxClasses]) }}
                </p>
            @else
                <form method="POST" action="{{ route('formations-generales.classes.store', $formation) }}" class="mt-4 grid grid-cols-2 gap-3">
                    @csrf
                    <div class="col-span-2">
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Nom de la classe') }}</label>
                        <input type="text" name="name" required class="mt-1 w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Code') }}</label>
                        <input type="text" name="code" required class="mt-1 w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Capacité') }}</label>
                        <input type="number" name="capacity" min="1" class="mt-1 w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" />
                    </div>
                    <button type="submit" class="col-span-2 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                        {{ __('Ajouter une classe') }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Accès historique (lecture seule, données non migrées) --}}
    @if ($legacyFormations->isNotEmpty())
        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50/60 p-6 dark:border-slate-800 dark:bg-slate-900/40">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Données historiques (non migrées)') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Les spécialités et inscriptions existantes de ce programme restent accessibles et inchangées. Leur regroupement sous cette formation générale nécessite une validation explicite.') }}
            </p>
            <ul class="mt-4 divide-y divide-slate-200 dark:divide-slate-800">
                @foreach ($legacyFormations as $legacy)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $legacy->name }} <span class="text-slate-400">({{ $legacy->code }})</span></span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $legacy->classes_count }} {{ __('classe(s)') }} · {{ $legacy->enrollments_count }} {{ __('inscription(s)') }}
                        </span>
                    </li>
                @endforeach
            </ul>
            @if ($domainDashboardRoute)
            <a href="{{ $domainDashboardRoute }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-400">
                {{ __('Accéder à l\'espace historique de la formation générale') }}
                <x-icon name="arrow-right" class="h-4 w-4 rtl:rotate-180" stroke-width="2" />
            </a>
            @endif
        </div>
    @endif

@endsection
