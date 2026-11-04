<!DOCTYPE html>
{{-- ══════════════════════════════════════════════════════════════════════════
     LAYOUT STUDENT DOSSIER — Page indépendante en pleine largeur,
     SANS sidebar et SANS header administratif dashboard.
     Conserve le toggle Clair/Sombre, la langue FR/AR, et les styles Tailwind.
════════════════════════════════════════════════════════════════════════════ --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ current_dir() }}" x-data x-init="$store.ui.init()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Détails de l\'étudiant') . ' — ' . config('app.name', 'Education Centers'))</title>
    <meta name="description" content="@yield('description', __('Détails du dossier étudiant'))">
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
<body id="student-dossier-layout" class="ec-type min-h-screen bg-slate-100 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-100">

    {{-- BARRE SUPÉRIEURE ÉPURÉE --}}
    <div class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/90 sm:px-8">
        {{-- Marque discrète --}}
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white shadow-2xs">
                <x-icon name="academic" class="h-4 w-4" />
            </div>
            <span class="text-sm font-extrabold tracking-tight text-slate-900 dark:text-white">Education Centers</span>
            <span class="hidden text-xs text-slate-400 sm:inline">· {{ __('Dossier étudiant') }}</span>
        </div>

        {{-- Outils : Thème & Langue --}}
        <div class="flex items-center gap-2">
            @include('layouts.partials.language-switcher')

            <button type="button"
                    class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                    x-on:click="$store.ui.toggleTheme()"
                    aria-label="{{ __('Basculer le thème') }}"
                    title="{{ __('Basculer le thème') }}">
                <x-icon name="moon" class="h-4 w-4" x-show="!$store.ui.dark" />
                <x-icon name="sun" class="h-4 w-4" x-show="$store.ui.dark" x-cloak />
            </button>
        </div>
    </div>

    {{-- CONTENU PRINCIPAL EN PLEINE LARGEUR SANS SIDEBAR --}}
    <main class="w-full px-4 py-6 sm:px-8 sm:py-8">
        @yield('content')
    </main>

    @include('layouts.partials.toasts')
    @stack('scripts')
</body>
</html>

