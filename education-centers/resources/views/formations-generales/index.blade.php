@extends('layouts.portal')

@section('title', __('Formations générales') . ' — ' . config('app.name'))
@section('description', __('Les six formations générales officielles de votre centre.'))

@php
    $user = auth()->user();
    $centerName = $user->isSuperAdmin()
        ? ($centers->firstWhere('id', $centerId)?->name ?? '')
        : ($user->center?->name ?? '');
@endphp

@section('content')

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-600/30">
                <x-icon name="book" class="h-6 w-6 text-white" stroke-width="1.8" />
            </span>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    {{ __('Formations générales') }}
                    @if($centerName)
                        <span class="ms-2 text-base font-normal text-slate-500 dark:text-slate-400">· {{ $centerName }}</span>
                    @endif
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Chaque centre dispose de ses six formations générales officielles, complétées le cas échéant par ses formations personnalisées.') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($user->isSuperAdmin() && $centers->isNotEmpty())
                <form method="GET" action="{{ route('formations-generales.index') }}" class="flex items-center gap-2">
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
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-brand-400 transition"
               title="{{ __('Retour à l\'accueil') }}">
                <x-icon name="arrow-left" class="h-4 w-4 rtl:rotate-180" stroke-width="2" />
                {{ __('Retour à l\'accueil') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($domains as $domain)
            @php
                // Clic sur la carte = accès DIRECT au tableau de bord pédagogique
                // existant du domaine (dashboard historique avec sidebar, KPI et
                // graphiques). Le contexte actif (session active_domain) est posé
                // par la route du dashboard ; le périmètre centre est forcé côté
                // serveur (Admin Centre = son centre ; Super Admin = filtre
                // ?center_id secondaire). La configuration reste accessible
                // séparément via « Ajouter une formation » (picker).
                $routeUrl = $user->isSuperAdmin()
                    ? route('super-admin.domain.dashboard', array_filter(['domain' => $domain->slug, 'center_id' => $centerId ?: null], fn ($v) => $v !== null))
                    : route('center-admin.domain.dashboard', $domain->slug);
            @endphp
            <x-domain-card :domain="$domain" :route-url="$routeUrl" />
        @endforeach

        {{-- Formations générales personnalisées du centre : même style de carte.
             Pas de dashboard domaine → le clic ouvre l’espace de la formation
             (formations-generales.show). Métadonnées visuelles portées par la
             formation elle-même (image téléversée, sinon icône générique), sans
             dépendre de DomainRegistry. --}}
        @foreach ($customFormations as $custom)
            @php
                $pseudoDomain = new \App\Models\Domain([
                    'slug'        => 'perso-' . $custom->id,
                    'name'        => $custom->translated('name') ?? $custom->name,
                    'description' => $custom->translated('description') ?? ($custom->description ?: __('Formation générale personnalisée du centre.')),
                    'icon'        => 'book',
                ]);
            @endphp
            <x-domain-card
                :domain="$pseudoDomain"
                :route-url="route('formations-generales.show', $custom)"
                :image-url="$custom->image_path ? asset('storage/' . $custom->image_path) : null"
                icon-name="book"
                :badge-label="$custom->status === 'active' ? __('Personnalisée') : __('Inactive')"
            />
        @endforeach
    </div>

@endsection
