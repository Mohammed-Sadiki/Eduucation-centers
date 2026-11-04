@extends('layouts.portal')

@section('title', __('Gestion des utilisateurs') . ' — ' . config('app.name'))
@section('description', __('Gestion des comptes d\'accès : Super Admin, Admin Centre et Professeur.'))

@php
    $authUser = auth()->user();
    $isSuperAdmin = $authUser->isSuperAdmin();
    $centerLabel = $isSuperAdmin ? __('Tous les centres') : ($authUser->center?->translated('name') ?? $authUser->center?->name);
@endphp

@section('content')
<div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">

    {{-- ══════════════════════════════════════════════════════════════
         EN-TÊTE DE PAGE & BOUTON D'ACTION
    ══════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-600/30">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
            </span>
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                    {{ __('Gestion des utilisateurs') }}
                </h1>
                <p class="text-base text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Administration des comptes d\'accès — Super Admin, Admin Centre et Professeurs.') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @can('create', \App\Models\User::class)
                <a href="{{ route('users.create') }}"
                   id="btn-add-user"
                   class="inline-flex items-center gap-2 rounded-2xl bg-brand-600 px-6 py-3.5 text-base font-bold text-white shadow-md transition-all hover:bg-brand-700 hover:shadow-lg dark:bg-brand-500 dark:hover:bg-brand-600">
                    <x-icon name="plus" class="h-5 w-5" stroke-width="2.2" />
                    <span>{{ __('+ Ajouter un utilisateur') }}</span>
                </a>
            @endcan

            <a href="{{ route('admin.home') }}"
               id="btn-users-back-home"
               class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3.5 text-base font-semibold text-slate-700 shadow-xs transition hover:border-slate-300 hover:bg-slate-50 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-brand-400">
                <x-icon name="arrow-left" class="h-5 w-5 rtl:rotate-180" stroke-width="2" />
                <span>{{ __('Retour à l\'accueil') }}</span>
            </a>
        </div>
    </div>

    {{-- Messages flash --}}
    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-base font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            <x-icon name="check" class="h-6 w-6 shrink-0 text-emerald-600 dark:text-emerald-400" />
            <div>{{ session('success') }}</div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         CARTE STATISTIQUE — TOTAL UTILISATEURS
    ══════════════════════════════════════════════════════════════ --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <section class="ec-card overflow-hidden p-6 sm:p-7 flex items-center justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    {{ __('Total utilisateurs') }}
                </p>
                <p class="mt-2 text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white" id="stat-total-users">
                    {{ number_format($totalUsers ?? $users->total(), 0, ',', ' ') }}
                </p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $centerLabel }}
                </p>
            </div>
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
            </div>
        </section>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         BARRE DE RECHERCHE ET FILTRES
    ══════════════════════════════════════════════════════════════ --}}
    <section class="ec-card p-6">
        <form method="GET" action="{{ route('users.index') }}" class="grid gap-4 md:grid-cols-4">
            {{-- Recherche --}}
            <div class="{{ $isSuperAdmin ? 'md:col-span-1' : 'md:col-span-2' }}">
                <label for="search" class="ec-label text-base font-semibold">{{ __('Rechercher') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           class="ec-input text-base ps-10" placeholder="{{ __('Nom ou adresse e-mail…') }}">
                    <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Filtre Centre (Super Admin uniquement) --}}
            @if($isSuperAdmin)
                <div>
                    <label for="center_id" class="ec-label text-base font-semibold">{{ __('Centre') }}</label>
                    <select id="center_id" name="center_id" class="ec-select text-base">
                        <option value="">{{ __('Tous les centres') }}</option>
                        @foreach($centers as $center)
                            <option value="{{ $center->id }}" @selected(request('center_id') == $center->id)>
                                {{ $center->translated('name') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Filtre Rôle (Super Admin, Admin Centre, Professeur uniquement) --}}
            <div>
                <label for="role" class="ec-label text-base font-semibold">{{ __('Rôle') }}</label>
                <select id="role" name="role" class="ec-select text-base">
                    <option value="">{{ __('Tous les rôles') }}</option>
                    @if($isSuperAdmin)
                        <option value="super_admin" @selected(request('role') === 'super_admin')>{{ __('Super Admin') }}</option>
                    @endif
                    <option value="center_admin" @selected(request('role') === 'center_admin')>{{ __('Admin Centre') }}</option>
                    <option value="teacher" @selected(request('role') === 'teacher')>{{ __('Professeur') }}</option>
                </select>
            </div>

            {{-- Actions de filtre --}}
            <div class="flex items-end gap-2">
                <button type="submit" class="ec-btn-primary w-full text-base py-3">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <span>{{ __('Filtrer') }}</span>
                </button>
                @if(request()->hasAny(['search', 'role', 'status', 'center_id']))
                    <a href="{{ route('users.index') }}" class="ec-btn-secondary py-3 text-base" title="{{ __('Réinitialiser') }}">
                        <x-icon name="x" class="h-5 w-5" />
                    </a>
                @endif
            </div>
        </form>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         TABLEAU DES COMPTES UTILISATEURS
    ══════════════════════════════════════════════════════════════ --}}
    <section class="ec-card overflow-hidden">
        <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-800 dark:text-slate-100">
                {{ __('Liste des utilisateurs') }}
            </h2>
        </header>

        <div class="overflow-x-auto">
            <table class="w-full text-start border-collapse text-base" id="table-users">
                <thead>
                    <tr class="border-b border-slate-200/80 bg-slate-50/50 text-start text-sm font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-400">
                        <th class="px-6 py-4">{{ __('Nom') }}</th>
                        <th class="px-6 py-4">{{ __('Adresse e-mail') }}</th>
                        <th class="px-6 py-4">{{ __('Rôle') }}</th>
                        <th class="px-6 py-4">{{ __('Centre associé') }}</th>
                        <th class="px-6 py-4">{{ __('Statut') }}</th>
                        <th class="px-6 py-4 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($users as $u)
                        <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                            {{-- Nom --}}
                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$u->name" size="sm" />
                                    <span>{{ $u->name }}</span>
                                </div>
                            </td>

                            {{-- Email --}}
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                {{ $u->email }}
                            </td>

                            {{-- Rôle --}}
                            <td class="px-6 py-4">
                                @if($u->isSuperAdmin())
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-100 px-3 py-1 text-sm font-bold text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                        <span class="h-2 w-2 rounded-full bg-purple-500"></span>
                                        {{ __('Super Admin') }}
                                    </span>
                                @elseif($u->isCenterAdmin())
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1 text-sm font-bold text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                                        <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                                        {{ __('Admin Centre') }}
                                    </span>
                                @elseif($u->isTeacher())
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                        <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                        {{ __('Professeur') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-sm font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        {{ $u->role }}
                                    </span>
                                @endif
                            </td>

                            {{-- Centre associé --}}
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                @if($u->center)
                                    <span class="inline-flex items-center gap-1.5 font-medium">
                                        <x-icon name="building" class="h-4 w-4 text-slate-400" />
                                        {{ $u->center->translated('name') }}
                                    </span>
                                @else
                                    <span class="text-slate-400 italic">{{ __('Global (Tous les centres)') }}</span>
                                @endif
                            </td>

                            {{-- Statut --}}
                            <td class="px-6 py-4">
                                @if($u->isActive())
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                        {{ __('Actif') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-sm font-bold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                        {{ __('Inactif') }}
                                    </span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-end">
                                <a href="{{ route('users.show', $u) }}"
                                   class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                                    <x-icon name="eye" class="h-4 w-4" />
                                    <span>{{ __('Consulter') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                                    <x-icon name="user" class="h-7 w-7" />
                                </div>
                                <h3 class="mt-4 text-xl font-bold text-slate-800 dark:text-slate-200">{{ __('Aucun utilisateur trouvé') }}</h3>
                                <p class="mt-1 text-base text-slate-500 dark:text-slate-400">{{ __('Aucun compte ne correspond aux critères sélectionnés.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="border-t border-slate-100 p-6 dark:border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    </section>

</div>
@endsection
