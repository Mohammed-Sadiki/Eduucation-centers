@extends('layouts.portal')

@section('title', __('Accueil Administration') . ' — ' . config('app.name'))
@section('description', __("Tableau de bord principal de l'administration Education Centers."))

@php
    $user = auth()->user();
    $isSuperAdmin  = $user->isSuperAdmin();
    $isCenterAdmin = $user->isCenterAdmin();
    $centerName    = $user->center?->name ?? '';

    $formationsRoute = route('formations-generales.index');

    $communicationRoute = route('messages.index');

    // La « Gestion des stagiaires » est ouverte au Super Admin (tous centres
    // autorisés) comme à l'Admin Centre (périmètre = son centre, imposé serveur).
    // Admin Centre : la carte mène directement au formulaire « Ajouter un
    // stagiaire » (création = acte principal de son quotidien). La page
    // « Tous les stagiaires » reste accessible depuis la sidebar du module.
    $stagiairesRoute = $isSuperAdmin
        ? route('stagiaires.index')
        : ($isCenterAdmin ? route('stagiaires.create') : route('dashboard'));

    // Le module financier est ouvert aux deux rôles : le Super Admin voit la vue
    // globale multi-centres, l'Admin Centre voit UNIQUEMENT son centre (périmètre
    // imposé côté serveur par FinanceDashboardController::resolveCenterScope()).
    $financeRoute = route('super-admin.finance.dashboard');
@endphp

@section('content')
{{-- HERO --}}
<section class="relative mb-10 overflow-hidden rounded-3xl border border-slate-200/70 bg-gradient-to-br from-brand-50 via-white to-indigo-50 px-6 py-10 sm:px-10 sm:py-12 dark:border-slate-800 dark:from-brand-950/30 dark:via-slate-900 dark:to-indigo-950/30" id="admin-home-hero">
    <div class="pointer-events-none absolute -top-24 -end-16 h-80 w-80 rounded-full bg-brand-300/25 blur-3xl dark:bg-brand-500/10" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-20 -start-12 h-72 w-72 rounded-full bg-indigo-300/20 blur-3xl dark:bg-indigo-500/10" aria-hidden="true"></div>

    <div class="relative flex flex-col items-center gap-6 text-center sm:flex-row sm:text-start">
        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-3xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-2xl shadow-brand-600/30">
            @if($isSuperAdmin)
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
            @else
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                </svg>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200/70 bg-white/80 px-3 py-1 text-xs font-semibold text-brand-700 shadow-2xs backdrop-blur dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
                @if($isSuperAdmin)
                    {{ __('Espace Super Administrateur') }}
                @else
                    {{ __('Espace Admin Centre') }}@if($centerName) &middot; {{ $centerName }}@endif
                @endif
            </span>
            <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                {{ __('Bienvenue,') }} <span class="text-brand-600 dark:text-brand-400">{{ $user->name }}</span>
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                @if($isSuperAdmin)
                    {{ __('Accédez aux modules de gestion de la plateforme Education Centers. Naviguez vers vos domaines de formation, la messagerie, la gestion des stagiaires ou les finances.') }}
                @else
                    {{ __('Accédez aux modules de gestion de votre centre. Les données affichées sont propres à votre centre et respectent vos permissions.') }}
                @endif
            </p>
        </div>

        @can('viewAny', \App\Models\User::class)
            <div class="shrink-0">
                <a href="{{ route('users.index') }}"
                   id="btn-admin-home-users"
                   class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-800 shadow-md transition-all hover:bg-slate-50 hover:shadow-lg dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700">
                    <svg class="h-5 w-5 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <span>{{ __('Utilisateurs') }}</span>
                </a>
            </div>
        @endcan
    </div>
</section>

{{-- GRILLE 4 CARTES
     Ligne 1 : carte « Formations » pleine largeur intégrant les trois actions
                 rapides (Ajouter un élève / Ajouter une formation / Plan de
                 classe) et le bouton d'accès « Accéder aux formations ».
     Ligne 2 : trois cartes de largeur égales (Gestion des stagiaires,
               Communication, Gestion financière). --}}
<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" id="admin-home-cards" role="list">

    {{-- CARTE 1 : FORMATIONS (pleine largeur, ligne 1) --}}
    <div class="sm:col-span-2 lg:col-span-3"
         id="card-home-formations"
         role="listitem"
         aria-label="{{ __('Accéder au module Formations') }}">
        <div class="group relative flex h-full flex-col overflow-hidden rounded-3xl border border-violet-200/70 bg-gradient-to-br from-violet-50/60 to-indigo-50/40 p-6 shadow-sm transition-all duration-200 ease-out hover:shadow-xl hover:border-violet-300 focus-within:ring-2 focus-within:ring-violet-500/40 focus-within:ring-offset-2 dark:border-violet-400/20 dark:bg-violet-400/[0.06] dark:hover:border-violet-400/40">
            <div class="pointer-events-none absolute -top-10 -end-10 h-40 w-40 rounded-full bg-violet-300/20 blur-2xl dark:bg-violet-500/10" aria-hidden="true"></div>

            {{-- 1. Icône + badge « 6 formations » --}}
            <div class="relative mb-5 flex items-start justify-between">
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 to-violet-600 text-white shadow-lg shadow-violet-500/30">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.905 59.905 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-violet-500"></span>
                    {{ __('6 formations') }}
                </span>
            </div>

            {{-- 2-3. Titre + description des six formations générales --}}
            <div class="relative">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('Formations') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ __('Gérez les six formations générales du centre (Santé, Informatique, Hôtellerie, Maçonnerie, Design intérieur, Design graphique), les étudiants, les professeurs et les activités pédagogiques.') }}</p>
            </div>

            {{-- 4. Ligne des trois actions rapides --}}
            <div class="relative mt-6 flex flex-wrap items-center gap-3">
                @can('create', \App\Models\Student::class)
                    <a href="{{ route('students.create') }}"
                       id="{{ $isSuperAdmin ? 'btn-add-student-domaines' : 'btn-add-student-ca-domaines' }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                       title="{{ __('Ajouter un élève') }}">
                        <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                        {{ __('Ajouter un élève') }}
                    </a>
                @endcan
                @can('create', \App\Models\Formation::class)
                    {{-- Le bouton ouvre désormais un sélecteur de configuration
                         dédié aux six formations générales officielles :
                         édition du prix / durée / description / statut /
                         professeur responsable. Il ne mène plus à la liste
                         des six cartes (celle-ci est ouverte par « Accéder
                         aux formations »). --}}
                    <a href="{{ route('formations-generales.picker') }}"
                       id="{{ $isSuperAdmin ? 'btn-add-formation-domaines' : 'btn-add-formation-ca-domaines' }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:ring-offset-2 dark:bg-violet-500 dark:hover:bg-violet-600"
                       title="{{ __('Ajouter une formation') }}">
                        <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                        {{ __('Ajouter une formation') }}
                    </a>
                @endcan
                <a href="{{ route('class-plan.index') }}"
                   id="{{ $isSuperAdmin ? 'btn-class-plan-domaines' : 'btn-class-plan-ca-domaines' }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:ring-offset-2 dark:bg-emerald-500 dark:hover:bg-emerald-600"
                   title="{{ __('Plan de classe') }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    {{ __('Plan de classe') }}
                </a>
            </div>

            {{-- 5. Bouton pleine largeur « Accéder aux formations » --}}
            <div class="relative mt-6 pt-1 flex-1 flex flex-col justify-end">
                <a href="{{ $formationsRoute }}"
                   class="group/link relative flex items-center justify-between rounded-xl bg-violet-100/70 px-4 py-3 text-sm font-semibold text-violet-700 transition-colors hover:bg-violet-200/60 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:ring-offset-2 dark:bg-violet-500/10 dark:text-violet-300 dark:hover:bg-violet-500/20">
                    <span>{{ __('Accéder aux formations') }}</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-violet-600 text-white shadow-sm transition-transform group-hover/link:translate-x-1 rtl:rotate-180">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    </span>
                </a>
            </div>
        </div>
    </div>

    {{-- CARTE 2 : STAGIAIRES --}}
    <a href="{{ $stagiairesRoute }}"
       id="card-home-stagiaires"
       role="listitem"
       class="group relative flex flex-col overflow-hidden rounded-3xl border border-teal-200/70 bg-gradient-to-br from-teal-50/60 to-emerald-50/40 p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:shadow-xl hover:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-500/40 focus:ring-offset-2 cursor-pointer dark:border-teal-400/20 dark:bg-teal-400/[0.06] dark:hover:border-teal-400/40"
       aria-label="{{ __('Accéder au module Gestion des stagiaires') }}">
        <div class="pointer-events-none absolute -top-10 -end-10 h-40 w-40 rounded-full bg-teal-300/20 blur-2xl dark:bg-teal-500/10" aria-hidden="true"></div>
        <div class="relative mb-5 flex items-start justify-between">
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-400 to-emerald-600 text-white shadow-lg shadow-teal-500/30">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                {{ __('Dossiers de stage') }}
            </span>
        </div>
        <div class="relative flex-1">
            <h2 class="text-xl font-bold text-slate-900 transition-colors group-hover:text-teal-700 dark:text-white dark:group-hover:text-teal-300">{{ __('Gestion des stagiaires') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ __('Consultez, organisez et suivez les stagiaires de votre établissement.') }}</p>
        </div>
        <div class="relative mt-6 flex items-center justify-between rounded-xl bg-teal-100/70 px-4 py-2.5 text-sm font-semibold text-teal-700 transition-colors group-hover:bg-teal-200/60 dark:bg-teal-500/10 dark:text-teal-300 dark:group-hover:bg-teal-500/20">
            <span>{{ $isCenterAdmin ? __('Ajouter un stagiaire') : __('Accéder aux stagiaires') }}</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-teal-600 text-white shadow-sm transition-transform group-hover:translate-x-1 rtl:rotate-180">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
            </span>
        </div>
    </a>

    {{-- CARTE : FINANCES (Super Admin = vue globale ; Admin Centre = vue centrée,
         périmètre forcé sur son centre côté serveur). --}}
    <a href="{{ $financeRoute }}"
       id="card-home-finance"
       role="listitem"
       class="group relative flex flex-col overflow-hidden rounded-3xl border border-amber-200/70 bg-gradient-to-br from-amber-50/60 to-orange-50/40 p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:shadow-xl hover:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:ring-offset-2 cursor-pointer dark:border-amber-400/20 dark:bg-amber-400/[0.06] dark:hover:border-amber-400/40{{ $isCenterAdmin ? ' order-4' : '' }}"
       aria-label="{{ __('Accéder au module Gestion financière') }}">
        <div class="pointer-events-none absolute -top-10 -end-10 h-40 w-40 rounded-full bg-amber-300/20 blur-2xl dark:bg-amber-500/10" aria-hidden="true"></div>
        <div class="relative mb-5 flex items-start justify-between">
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-600 text-white shadow-lg shadow-amber-500/30">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                </svg>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                @if($isSuperAdmin) {{ __('Vue globale') }} @else {{ __('Vue centre') }} @endif
            </span>
        </div>
        <div class="relative flex-1">
            <h2 class="text-xl font-bold text-slate-900 transition-colors group-hover:text-amber-700 dark:text-white dark:group-hover:text-amber-300">{{ __('Gestion financière') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ __('Suivez les factures, paiements, salaires, dépenses et statistiques financières.') }}</p>
        </div>
        <div class="relative mt-6 flex items-center justify-between rounded-xl bg-amber-100/70 px-4 py-2.5 text-sm font-semibold text-amber-800 transition-colors group-hover:bg-amber-200/60 dark:bg-amber-500/10 dark:text-amber-300 dark:group-hover:bg-amber-500/20">
            <span>{{ $isSuperAdmin ? __('Accéder aux finances') : __('Accéder à la gestion financière') }}</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-600 text-white shadow-sm transition-transform group-hover:translate-x-1 rtl:rotate-180">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
            </span>
        </div>
    </a>

    {{-- CARTE : COMMUNICATION --}}
    <a href="{{ $communicationRoute }}"
       id="card-home-communication"
       role="listitem"
       class="group relative flex flex-col overflow-hidden rounded-3xl border border-cyan-200/70 bg-gradient-to-br from-cyan-50/60 to-sky-50/40 p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:shadow-xl hover:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:ring-offset-2 cursor-pointer dark:border-cyan-400/20 dark:bg-cyan-400/[0.06] dark:hover:border-cyan-400/40{{ $isCenterAdmin ? ' order-3' : '' }}"
       aria-label="{{ __('Accéder au module Communication') }}">
        <div class="pointer-events-none absolute -top-10 -end-10 h-40 w-40 rounded-full bg-cyan-300/20 blur-2xl dark:bg-cyan-500/10" aria-hidden="true"></div>
        <div class="relative mb-5 flex items-start justify-between">
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-sky-600 text-white shadow-lg shadow-cyan-500/30">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                </svg>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300">
                <span class="h-1.5 w-1.5 rounded-full bg-cyan-500"></span>
                {{ __('Messagerie') }}
            </span>
        </div>
        <div class="relative flex-1">
            <h2 class="text-xl font-bold text-slate-900 transition-colors group-hover:text-cyan-700 dark:text-white dark:group-hover:text-cyan-300">{{ __('Communication') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ __('Centralisez les échanges et les communications entre les responsables des centres.') }}</p>
        </div>
        <div class="relative mt-6 flex items-center justify-between rounded-xl bg-cyan-100/70 px-4 py-2.5 text-sm font-semibold text-cyan-700 transition-colors group-hover:bg-cyan-200/60 dark:bg-cyan-500/10 dark:text-cyan-300 dark:group-hover:bg-cyan-500/20">
            <span>{{ __('Accéder à la communication') }}</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-cyan-600 text-white shadow-sm transition-transform group-hover:translate-x-1 rtl:rotate-180">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
            </span>
        </div>
    </a>

</div>

{{-- PIED --}}
<div class="mt-10 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white/60 px-5 py-4 text-xs text-slate-500 shadow-2xs backdrop-blur dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400" id="admin-home-footer">
    <span class="flex items-center gap-1.5">
        <svg class="h-3.5 w-3.5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.905 59.905 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
        {{ __('Education Centers') }} &mdash; {{ __('Plateforme de gestion des centres de formation') }}
    </span>
    <span>{{ __('Connecté en tant que') }} <strong class="text-slate-700 dark:text-slate-200">{{ $user->name }}</strong></span>
</div>
@endsection
