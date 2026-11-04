<!DOCTYPE html>
{{-- ══════════════════════════════════════════════════════════════════════════
     LAYOUT STANDALONE — Page indépendante, AUCUNE sidebar pédagogique,
     AUCUN sélecteur de domaine (même principe que layouts/communication).
     Utilisé par « Ajouter un élève » et, à terme, toute page d'action
     plein écran accessible depuis le portail Domaines de formation.

     Sections personnalisées :
       - standalone_title       : contexte affiché dans la topbar
       - standalone_back_url    : cible du bouton « Retour aux domaines »
       - standalone_back_label  : libellé du bouton de retour
════════════════════════════════════════════════════════════════════════════ --}}
@php
    $user = auth()->user();
    $roleLabel = match ($user?->role) {
        'super_admin' => __('Super Admin'),
        'center_admin' => __('Admin Centre'),
        'teacher' => __('Professeur'),
        default => $user?->role ?? __('Utilisateur'),
    };
    $centerLabel = $user?->isSuperAdmin() ? __('Tous les centres') : ($user?->center?->name ?? __('Direction Générale'));
    // Retour aux domaines : cible par rôle (jamais un URL fourni par le navigateur).
    $defaultBackUrl = $user?->isSuperAdmin() ? route('super-admin.domaines') : route('center-admin.domaines');
    $homeUrl = ($user && !$user->isTeacher()) ? route('admin.home') : route('dashboard');
    // Le contenu de la page peut surcharger la cible / le libellé via @section.
    $backUrlSection = trim($__env->yieldContent('standalone_back_url'));
    $backLabelSection = trim($__env->yieldContent('standalone_back_label'));
    $backUrl = $backUrlSection !== '' ? $backUrlSection : $defaultBackUrl;
    $backLabel = $backLabelSection !== '' ? $backLabelSection : __('Retour aux formations générales');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ current_dir() }}" x-data x-init="$store.ui.init()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Ajouter un élève') . ' — ' . config('app.name', 'Education Centers'))</title>
    <meta name="description" content="@yield('description', __('Education Centers'))">
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        if (localStorage.getItem('ec-theme') === 'dark' || (!localStorage.getItem('ec-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @stack('styles')
</head>
<body id="standalone-layout" class="ec-type flex min-h-screen flex-col bg-slate-100 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-100">

    {{-- ════════════════════════════════════════════════════════════════════
         TOPBAR PROPRE (indépendante du dashboard pédagogique)
    ════════════════════════════════════════════════════════════════════════ --}}
    <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 sm:px-6"
          x-data="{ profileOpen: false }">

        {{-- ── Gauche : logo + contexte ─────────────────────────────────── --}}
        <div class="flex min-w-0 items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-sm">
                <x-icon name="academic" class="h-6 w-6" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-base font-bold leading-tight tracking-tight text-slate-900 dark:text-white">
                    {{ __('Education Centers') }}
                </p>
                <p class="truncate text-xs font-medium leading-tight text-brand-600 dark:text-brand-400">@yield('standalone_title', __('Ajouter un élève'))</p>
            </div>
        </div>

        {{-- ── Droite : actions ─────────────────────────────────────────── --}}
        <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">

            {{-- Retour aux domaines --}}
            <a href="{{ $backUrl }}"
               id="btn-back-domaines"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-800 dark:hover:bg-brand-950/40 dark:hover:text-brand-400"
               title="{{ $backLabel }}">
                <svg class="h-3.5 w-3.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span class="hidden sm:inline">{{ $backLabel }}</span>
                <span class="sm:hidden">{{ __('Formations générales') }}</span>
            </a>

            {{-- Sélecteur de langue FR / AR --}}
            @include('layouts.partials.language-switcher')

            {{-- Toggle Light / Dark --}}
            <button type="button"
                    class="rounded-xl p-2 text-slate-500 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                    x-on:click="$store.ui.toggleTheme()"
                    aria-label="{{ __('Basculer le thème') }}"
                    title="{{ __('Basculer le thème') }}">
                <x-icon name="moon" class="h-5 w-5" x-show="!$store.ui.dark" />
                <x-icon name="sun" class="h-5 w-5" x-show="$store.ui.dark" x-cloak />
            </button>

            <div class="hidden h-6 w-px bg-slate-200 dark:bg-slate-700 sm:block"></div>

            {{-- Profil utilisateur + rôle --}}
            <div class="relative" @click.outside="profileOpen = false">
                <button type="button"
                        class="flex items-center gap-2.5 rounded-xl p-1 pe-1.5 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                        x-on:click="profileOpen = !profileOpen"
                        aria-haspopup="true"
                        :aria-expanded="profileOpen">
                    <x-ui.avatar :name="$user?->name ?? 'U'" size="sm" />
                    <span class="hidden text-start md:block">
                        <span class="block max-w-[150px] truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $user?->name }}</span>
                        <span class="block max-w-[150px] truncate text-xs text-slate-500 dark:text-slate-400">{{ $roleLabel }} · {{ $centerLabel }}</span>
                    </span>
                    <x-icon name="chevron-down" class="hidden h-4 w-4 text-slate-400 md:block" />
                </button>
                <div x-show="profileOpen" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute end-0 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $user?->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user?->email }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Centre :') }} <strong class="text-slate-700 dark:text-slate-200">{{ $centerLabel }}</strong></p>
                    </div>
                    <a href="{{ $backUrl }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                        <x-icon name="book" class="h-4 w-4" /> {{ $backLabel }}
                    </a>
                    <a href="{{ $homeUrl }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                        <x-icon name="home" class="h-4 w-4" /> {{ __('Retour à l\'accueil') }}
                    </a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40">
                            <x-icon name="logout" class="h-4 w-4" /> {{ __('Déconnexion') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- ════════════════════════════════════════════════════════════════════
         CONTENU DE LA PAGE (défilement normal, aucune sidebar)
    ════════════════════════════════════════════════════════════════════════ --}}
    <main class="flex flex-1 flex-col">
        @yield('content')
    </main>

    @include('layouts.partials.toasts')
    @stack('scripts')
</body>
</html>
