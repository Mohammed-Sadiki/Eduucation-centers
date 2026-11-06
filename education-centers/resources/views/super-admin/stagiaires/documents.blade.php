@extends('layouts.app')

@section('title', __('Documents des Stagiaires') . ' — ' . config('app.name'))
@section('description', __('Consultation et téléchargement des documents, pièces d\'identité et diplômes des stagiaires.'))

@section('breadcrumb')
    <li class="flex items-center gap-2">
        <x-icon name="chevron-left" class="h-3 w-3 text-slate-400 rotate-180" />
        <a href="{{ route('super-admin.domaines') }}" class="hover:text-brand-700 dark:hover:text-brand-400 transition-colors">{{ __('Portail des formations générales') }}</a>
    </li>
    <li class="flex items-center gap-2">
        <x-icon name="chevron-left" class="h-3 w-3 text-slate-400 rotate-180" />
        <a href="{{ route('stagiaires.index') }}" class="hover:text-brand-700 dark:hover:text-brand-400 transition-colors">{{ __('Stagiaires') }}</a>
    </li>
    <li class="flex items-center gap-2">
        <x-icon name="chevron-left" class="h-3 w-3 text-slate-400 rotate-180" />
        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ __('Documents') }}</span>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    {{-- En-tête --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5 [direction:ltr]" dir="ltr">
            <x-ui.back-button :fallback="route('stagiaires.index')" />
            <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                    <span>{{ __('📁 Espace Documents — Stagiaires') }}</span>
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Répertoire centralisé de tous les justificatifs (CIN, diplômes scannés, conventions et attestations).') }}
                </p>
            </div>
        </div>
        @can('create', App\Models\Stagiaire::class)
            <div class="flex items-center gap-2">
                <a href="{{ route('stagiaires.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-purple-700 transition">
                    <x-icon name="plus" class="h-4 w-4" />
                    <span>{{ __('Nouveau stagiaire') }}</span>
                </a>
            </div>
        @endcan
    </div>

    {{-- Filtres par type de document --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <a href="{{ route('stagiaires.documents') }}"
           class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all {{ !request()->filled('type') ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
            <span>{{ __('Tous les documents') }}</span>
            <span class="rounded-full px-1.5 py-0.5 text-2xs {{ !request()->filled('type') ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' }}">
                {{ $totalDocsCount }}
            </span>
        </a>

        <a href="{{ route('stagiaires.documents', ['type' => 'cin']) }}"
           class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all {{ request('type') === 'cin' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
            <x-icon name="shield" class="h-3.5 w-3.5" />
            <span>{{ __('Cartes CIN') }}</span>
            <span class="rounded-full px-1.5 py-0.5 text-2xs {{ request('type') === 'cin' ? 'bg-white/25 text-white' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300' }}">
                {{ $cins->count() }}
            </span>
        </a>

        <a href="{{ route('stagiaires.documents', ['type' => 'diploma']) }}"
           class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all {{ request('type') === 'diploma' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
            <x-icon name="certificate" class="h-3.5 w-3.5" />
            <span>{{ __('Diplômes') }}</span>
            <span class="rounded-full px-1.5 py-0.5 text-2xs {{ request('type') === 'diploma' ? 'bg-white/25 text-white' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                {{ $diplomas->count() }}
            </span>
        </a>

        <a href="{{ route('stagiaires.documents', ['type' => 'convention']) }}"
           class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all {{ request('type') === 'convention' ? 'bg-teal-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
            <x-icon name="document" class="h-3.5 w-3.5" />
            <span>{{ __('Conventions') }}</span>
        </a>
    </div>

    {{-- Barre de recherche --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('stagiaires.documents') }}" class="flex flex-col sm:flex-row gap-3">
            @if(request()->filled('type'))
                <input type="hidden" name="type" value="{{ request('type') }}">
            @endif
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                    <x-icon name="search" class="h-3.5 w-3.5" />
                </div>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('Rechercher par titre de document ou nom du stagiaire...') }}"
                       class="block w-full rounded-xl border border-slate-300 bg-slate-50/50 ps-9 pe-3 py-2 text-xs text-slate-900 focus:border-purple-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
            </div>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-purple-600 px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-purple-700 transition">
                <x-icon name="search" class="h-3.5 w-3.5" />
                <span>{{ __('Rechercher') }}</span>
            </button>
            @if(request()->hasAny(['search', 'type']))
                <a href="{{ route('stagiaires.documents') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 transition"
                   title="{{ __('Réinitialiser') }}">
                    {{ __('✕ Réinitialiser') }}
                </a>
            @endif
        </form>
    </div>

    {{-- Section 1 : Cartes CIN --}}
    @if($cins->isNotEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                    <x-icon name="shield" class="h-4 w-4" />
                </span>
                {{ __('Pièces d\'identité (CIN) —') }} {{ $cins->count() }} {{ __('stagiaires') }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($cins as $stg)
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700/60 dark:bg-slate-800/40 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <a href="{{ route('stagiaires.show', $stg) }}" class="text-xs font-bold text-slate-900 hover:text-purple-600 hover:underline dark:text-white dark:hover:text-purple-400">
                                    {{ $stg->last_name }} {{ $stg->first_name }}
                                </a>
                                <p class="font-mono text-xs text-slate-500">{{ __('CIN :') }} {{ $stg->cin_number ?? 'N/A' }}</p>
                            </div>
                            <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-2xs font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">CIN</span>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                            @if($stg->cin_recto_path)
                                <a href="{{ route('stagiaires.cin-download', [$stg, 'recto']) }}"
                                   class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-700 transition">
                                    <x-icon name="download" class="h-3 w-3" />
                                    <span>{{ __('Recto') }}</span>
                                </a>
                            @endif
                            @if($stg->cin_verso_path)
                                <a href="{{ route('stagiaires.cin-download', [$stg, 'verso']) }}"
                                   class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-700 transition">
                                    <x-icon name="download" class="h-3 w-3" />
                                    <span>{{ __('Verso') }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Section 2 : Diplômes scannés --}}
    @if($diplomas->isNotEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                    <x-icon name="certificate" class="h-4 w-4" />
                </span>
                {{ __('Diplômes scannés —') }} {{ $diplomas->count() }} {{ __('pièces') }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($diplomas as $dip)
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700/60 dark:bg-slate-800/40 space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <h3 class="text-xs font-bold text-slate-900 truncate dark:text-white">{{ $dip->title }}</h3>
                                <p class="text-xs text-slate-500 truncate">
                                    {{ $dip->institution ?? __('Établissement non précisé') }}
                                    @if($dip->year) ({{ $dip->year }}) @endif
                                </p>
                                <a href="{{ route('stagiaires.show', $dip->stagiaire) }}" class="text-xs font-semibold text-purple-600 hover:underline dark:text-purple-400">
                                    {{ __('Stagiaire :') }} {{ $dip->stagiaire->full_name }}
                                </a>
                            </div>
                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-2xs font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300 shrink-0">{{ __('Diplôme') }}</span>
                        </div>

                        <div class="pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                            <a href="{{ route('stagiaires.diploma-download', [$dip->stagiaire, $dip]) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-amber-700 transition">
                                <x-icon name="download" class="h-3.5 w-3.5" />
                                <span>{{ __('Télécharger le justificatif') }}</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Section 3 : Autres documents administratifs --}}
    @if($documents->isNotEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                    <x-icon name="document" class="h-4 w-4" />
                </span>
                {{ __('Documents & conventions de stage —') }} {{ $documents->count() }} {{ __('pièces') }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($documents as $doc)
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700/60 dark:bg-slate-800/40 space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <h3 class="text-xs font-bold text-slate-900 truncate dark:text-white">{{ $doc->title }}</h3>
                                <p class="text-xs text-slate-500">{{ __('Ajouté le') }} {{ format_date($doc->created_at, 'd/m/Y') }}</p>
                                <a href="{{ route('stagiaires.show', $doc->stagiaire) }}" class="text-xs font-semibold text-purple-600 hover:underline dark:text-purple-400">
                                    {{ __('Stagiaire :') }} {{ $doc->stagiaire->full_name }}
                                </a>
                            </div>
                            <span class="rounded bg-teal-100 px-1.5 py-0.5 text-2xs font-bold text-teal-800 dark:bg-teal-950 dark:text-teal-300 uppercase shrink-0">
                                {{ $doc->type }}
                            </span>
                        </div>

                        <div class="pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                            <a href="{{ route('stagiaires.document-download', [$doc->stagiaire, $doc]) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-teal-700 transition">
                                <x-icon name="download" class="h-3.5 w-3.5" />
                                <span>{{ __('Télécharger le document') }}</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- État vide si aucun document --}}
    @if($cins->isEmpty() && $diplomas->isEmpty() && $documents->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <span class="text-3xl block mb-2">📁</span>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Aucun document trouvé') }}</h3>
            <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                {{ __('Aucune pièce justificative ne correspond aux critères de filtre sélectionnés.') }}
            </p>
            @can('create', App\Models\Stagiaire::class)
                <div class="mt-4">
                    <a href="{{ route('stagiaires.create') }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-purple-700 transition">
                        <x-icon name="plus" class="h-3.5 w-3.5" />
                        <span>{{ __('Créer un dossier avec documents') }}</span>
                    </a>
                </div>
            @endcan
        </div>
    @endif
</div>
@endsection
