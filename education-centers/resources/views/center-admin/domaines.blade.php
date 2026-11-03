@extends('layouts.portal')

@section('title', __('Sélection du domaine') . ' — ' . config('app.name'))
@section('description', __('Choisissez le domaine d\'activité à administrer pour votre centre.'))

@php
    $user = auth()->user();
    $centerName = $user->center?->name ?? '';
@endphp

@section('content')

    {{-- En-tête de section avec retour accueil --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-600/30">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a7.723 7.723 0 0 1 0-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </span>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    {{ __('Domaines de formation') }}
                    @if($centerName)
                        <span class="ms-2 text-base font-normal text-slate-500 dark:text-slate-400">· {{ $centerName }}</span>
                    @endif
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Les six domaines de formation sont disponibles pour votre centre.') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Les actions « Ajouter un élève », « Gérer les formations
                 officielles » et « Plan de classe » ont été déplacées sur la
                 carte Formations de la page d'accueil (admin.home) pour éviter
                 toute duplication sur les pages Domaines. --}}
            <a href="{{ route('admin.home') }}"
               id="ca-domaines-back-home"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-brand-400 transition"
               title="{{ __('Retour à l\'accueil') }}"
            >
                <x-icon name="arrow-left" class="h-4 w-4 rtl:rotate-180" stroke-width="2" />
                {{ __('Retour à l\'accueil') }}
            </a>
        </div>
    </div>

    {{-- Grille des domaines : 3 par ligne desktop, 2 tablette, 1 mobile --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($domains as $domain)
            <x-domain-card :domain="$domain" :route-url="route('center-admin.domain.dashboard', $domain->slug)" />
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white/60 p-10 text-center dark:border-slate-700 dark:bg-slate-900/40">
                <x-icon name="book" class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" />
                <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">
                    {{ __('Aucun domaine disponible pour votre centre pour le moment.') }}
                </p>
                <a href="{{ route('admin.home') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    <x-icon name="home" class="h-4 w-4" />
                    {{ __('Retour à l\'accueil') }}
                </a>
            </div>
        @endforelse
    </div>

@endsection
