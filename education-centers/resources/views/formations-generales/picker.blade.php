@extends('layouts.portal')

@section('title', __('Configurer une formation officielle') . ' — ' . config('app.name'))
@section('description', __('Sélectionnez l’une des six formations générales officielles à configurer.'))

@php
    $user = auth()->user();
    $centerName = $user->isSuperAdmin()
        ? ($centers->firstWhere('id', $centerId)?->name ?? '')
        : ($user->center?->name ?? '');
@endphp

@section('content')

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white shadow-lg shadow-violet-600/30">
                <x-icon name="plus" class="h-6 w-6 text-white" stroke-width="2" />
            </span>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    {{ __('Ajouter une formation officielle') }}
                    @if($centerName)
                        <span class="ms-2 text-base font-normal text-slate-500 dark:text-slate-400">· {{ $centerName }}</span>
                    @endif
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Sélectionnez l’une des six formations générales pour la configurer (prix, durée, description, statut, professeur responsable). Aucune sous-formation ni septième formation ne peut être créée.') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($user->isSuperAdmin() && $centers->isNotEmpty())
                <form method="GET" action="{{ route('formations-generales.picker') }}" class="flex items-center gap-2">
                    <label for="center-select" class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Centre') }}</label>
                    <select id="center-select" name="center_id" onchange="this.form.submit()"
                            class="rounded-xl border-slate-200 bg-white text-xs font-semibold text-slate-700 shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                        @foreach ($centers as $center)
                            <option value="{{ $center->id }}" @selected((int) $center->id === (int) $centerId)>{{ $center->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a href="{{ route('admin.home') }}"
               id="picker-back-home"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-brand-400 transition"
               title="{{ __('Retour à l\'accueil') }}">
                <x-icon name="arrow-left" class="h-4 w-4 rtl:rotate-180" stroke-width="2" />
                {{ __('Retour à l\'accueil') }}
            </a>
        </div>
    </div>

    {{-- Les six emplacements officiels autorisés (source : DomainRegistry::visible()). --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3" role="list" id="picker-formations-slots">
        @foreach ($domains as $domain)
            @php
                $official = $officialByCode->get($domain->slug);
                $configured = $official !== null;
            @endphp
            <div role="listitem"
                 class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:border-violet-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-violet-500/60"
                 id="picker-slot-{{ $domain->slug }}">
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ __($domain->name) }}
                        </h2>
                        @if ($configured)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('Configurée') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                {{ __('À configurer') }}
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @if ($configured)
                            {{ __('Prix officiel, durée, statut et responsable peuvent être ajustés.') }}
                        @else
                            {{ __('Cette formation officielle n’a pas encore été activée pour ce centre.') }}
                        @endif
                    </p>
                </div>

                <div class="mt-4">
                    @if ($configured)
                        {{-- Déjà configurée : on ouvre son édition (aucun doublon). --}}
                        <a href="{{ route('formations-generales.edit', $official) }}"
                           id="picker-edit-{{ $domain->slug }}"
                           class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:ring-offset-2 dark:bg-violet-500 dark:hover:bg-violet-600">
                            <x-icon name="pencil" class="h-4 w-4" stroke-width="2" />
                            {{ __('Configurer') }}
                        </a>
                    @else
                        {{-- Slot vierge : activation (création de la formation officielle manquante) puis édition. --}}
                        <form method="POST" action="{{ route('formations-generales.activate') }}" class="w-full">
                            @csrf
                            <input type="hidden" name="official_code" value="{{ $domain->slug }}">
                            @if ($user->isSuperAdmin())
                                <input type="hidden" name="center_id" value="{{ $centerId }}">
                            @endif
                            <button type="submit"
                                    id="picker-activate-{{ $domain->slug }}"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:ring-offset-2 dark:bg-violet-500 dark:hover:bg-violet-600">
                                <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                                {{ __('Activer et configurer') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4 text-xs leading-relaxed text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
        <strong class="font-semibold">{{ __('Rappel') }}</strong> —
        {{ __('Les six formations générales officielles constituent le catalogue unique de chaque centre. Leur nombre, leurs codes et leur libellé ne peuvent pas être modifiés depuis cette interface.') }}
    </div>

@endsection
