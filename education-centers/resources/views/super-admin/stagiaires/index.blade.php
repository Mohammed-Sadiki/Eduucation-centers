@extends('layouts.app')

@section('title', __('Tous les stagiaires') . ' — ' . config('app.name'))
@section('description', __('Gestion administrative des stagiaires et suivi des dossiers de stage.'))

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
        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ __('Tous les stagiaires') }}</span>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    {{-- En-tête de la page : titre + description --}}
    <div class="flex items-center gap-3.5 [direction:ltr]" dir="ltr">
        <x-ui.back-button :fallback="route('admin.home')" />
        <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ __('Tous les stagiaires') }}
            </h1>
            <p class="mt-1 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                @if(auth()->user()->isSuperAdmin())
                    {{ __('Consultez et gérez tous les stagiaires enregistrés dans les centres de formation.') }}
                @else
                    {{ __('Consultez et gérez les stagiaires enregistrés dans votre centre de formation.') }}
                @endif
            </p>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────
         CARTE STATISTIQUE UNIQUE : TOTAL STAGIAIRES (périmètre autorisé)
    ──────────────────────────────────────────────────────────── --}}
    <div id="card-total-stagiaires"
         class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 sm:max-w-xs">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-purple-600 text-white shadow-2xs">
            <x-icon name="users" class="h-6 w-6" />
        </span>
        <div class="min-w-0">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Total stagiaires') }}</span>
            <p class="mt-1 text-3xl font-black leading-none text-purple-700 dark:text-purple-300">{{ $totalStagiaires }}</p>
            <span class="mt-1 block text-xs text-slate-400 dark:text-slate-500">{{ __('Stagiaires enregistrés') }}</span>
        </div>
    </div>

    {{-- Filtres et recherche --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('stagiaires.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="search" class="sr-only">{{ __('Recherche') }}</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                        <x-icon name="search" class="h-3.5 w-3.5" />
                    </div>
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Nom, CIN, email, tél...') }}"
                           class="block w-full rounded-xl border border-slate-300 bg-slate-50/50 ps-9 pe-3 py-2 text-xs text-slate-900 focus:border-purple-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                </div>
            </div>

            <div>
                <label for="status" class="sr-only">{{ __('Statut') }}</label>
                <select id="status" name="status"
                        class="block w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3 py-2 text-xs text-slate-900 focus:border-purple-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    <option value="">{{ __('-- Tous les statuts --') }}</option>
                    <option value="nouveau"  {{ request('status') === 'nouveau'  ? 'selected' : '' }}>{{ __('Nouveau') }}</option>
                    <option value="en_cours" {{ request('status') === 'en_cours' ? 'selected' : '' }}>{{ __('Stage en cours') }}</option>
                    <option value="termine"  {{ request('status') === 'termine'  ? 'selected' : '' }}>{{ __('Terminé') }}</option>
                    <option value="annule"   {{ request('status') === 'annule'   ? 'selected' : '' }}>{{ __('Annulé') }}</option>
                </select>
            </div>

            @if(auth()->user()->isSuperAdmin())
                <div>
                    <label for="center_id" class="sr-only">{{ __('Centre') }}</label>
                    <select id="center_id" name="center_id"
                            class="block w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3 py-2 text-xs text-slate-900 focus:border-purple-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <option value="">{{ __('-- Tous les centres --') }}</option>
                        @foreach($centers as $c)
                            <option value="{{ $c->id }}" {{ request('center_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->translated('name') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-purple-700 transition">
                    <x-icon name="filter" class="h-3.5 w-3.5" />
                    <span>{{ __('Filtrer') }}</span>
                </button>
                @if(request()->hasAny(['search', 'status', 'center_id']))
                    <a href="{{ route('stagiaires.index') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 transition"
                       title="{{ __('Réinitialiser tous les filtres') }}">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tableau des stagiaires : sept colonnes (Stagiaire, Centre, Type de stage,
         Période du stage, Prix convenu, Statut, Actions). Le « N° CIN » n'est plus
         affiché ici — la donnée reste en base, modifiable au formulaire et visible
         sur la fiche du stagiaire. Les largeurs se répartissent automatiquement
         (.ec-table : layout auto + min-w-full, conteneur overflow-x-auto). --}}
    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <div class="ec-table-wrap">
            <table class="ec-table">
                <thead>
                    <tr>
                        <th>{{ __('Stagiaire') }}</th>
                        <th>{{ __('Centre') }}</th>
                        <th>{{ __('Type de stage') }}</th>
                        <th>{{ __('Période du stage') }}</th>
                        <th>{{ __('Prix convenu') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stagiaires as $stagiaire)
                        <tr>
                            <td>
                                <div class="font-bold text-slate-900 dark:text-white">
                                    <a href="{{ route('stagiaires.show', $stagiaire) }}" class="hover:text-purple-600 hover:underline dark:hover:text-purple-400">
                                        {{ $stagiaire->last_name }} {{ $stagiaire->first_name }}
                                    </a>
                                </div>
                                <div class="text-xs text-slate-500">
                                    <bdi dir="ltr">{{ $stagiaire->email ?? $stagiaire->phone ?? __('Aucun contact') }}</bdi>
                                </div>
                            </td>
                            <td>
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ $stagiaire->center?->name ?? __('Tous centres') }}
                                </span>
                            </td>
                            <td>
                                <span class="text-xs text-slate-700 dark:text-slate-300">
                                    {{ $stagiaire->stage_type ?? __('Non spécifié') }}
                                </span>
                            </td>
                            <td>
                                @if($stagiaire->stage_start_date && $stagiaire->stage_end_date)
                                    <div class="text-xs font-medium text-slate-800 dark:text-slate-200">
                                        {{ format_date($stagiaire->stage_start_date, 'd/m/Y') }} → {{ format_date($stagiaire->stage_end_date, 'd/m/Y') }}
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400 italic">{{ __('Non définie') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="inline-flex items-center rounded-lg bg-purple-50 px-2 py-0.5 text-xs font-bold text-purple-900 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200/60 dark:border-purple-800/60">
                                    {{ $stagiaire->formatted_stage_price }}
                                </span>
                            </td>
                            <td>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $stagiaire->status_badge_class }}">
                                    {{ $stagiaire->status_label }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('stagiaires.show', $stagiaire) }}"
                                       class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-purple-600 dark:hover:bg-slate-800 dark:hover:text-purple-400 transition"
                                       title="{{ __('Consulter le stagiaire') }}">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </a>
                                    <a href="{{ route('stagiaires.edit', $stagiaire) }}"
                                       class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-purple-600 dark:hover:bg-slate-800 dark:hover:text-purple-400 transition"
                                       title="{{ __('Modifier le stagiaire') }}">
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </a>
                                    <form action="{{ route('stagiaires.destroy', $stagiaire) }}" method="POST"
                                          onsubmit="return confirm('{{ __('Confirmez-vous la suppression de ce stagiaire ?') }}');"
                                          class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/50 dark:hover:text-rose-400 transition"
                                                title="{{ __('Supprimer le stagiaire') }}">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-sm text-slate-500">
                                <span class="text-2xl block mb-2">🔍</span>
                                {{ __('Aucun stagiaire correspondant aux critères sélectionnés.') }}
                                <div class="mt-3">
                                    <a href="{{ route('stagiaires.create') }}"
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-purple-700 transition">
                                        <x-icon name="plus" class="h-3.5 w-3.5" />
                                        <span>{{ __('Ajouter un stagiaire') }}</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($stagiaires->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                {{ $stagiaires->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
