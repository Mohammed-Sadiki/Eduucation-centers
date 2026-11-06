@extends('layouts.standalone')

@section('title', __('Ajouter une formation') . ' — ' . config('app.name'))
@section('description', __('Création d\'un programme de formation pour un centre et une formation générale.'))
@section('standalone_title', __('Ajouter une formation'))

@php
    $authUser = auth()->user();
    $isCenterAdmin = $authUser->isCenterAdmin();
    $forcedCenter = $isCenterAdmin ? $authUser->center : null;
    $backUrl = $isCenterAdmin ? route('center-admin.domaines') : route('super-admin.domaines');
@endphp

@section('standalone_back_url', $backUrl)
@section('standalone_back_label', __('Retour aux formations générales'))

@section('content')
<div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6">

    {{-- Titre de la page (le retour aux domaines est dans la topbar) --}}
    <x-ui.page-header
        :title="__('Ajouter une formation')"
        :description="__('Rattachement au centre et à la formation générale, puis informations détaillées du programme.')" />

    {{-- Bandeau de clarification : nouveau parcours « Centre → Formation → Classe ».
         La création libre d'une sous-formation n'est plus le chemin recommandé.
         Cette page est conservée uniquement pour l'import de données legacy et
         l'administration technique. Le chemin utilisateur officiel passe par le
         catalogue des six formations générales. --}}
    <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
        <div class="flex items-start gap-3">
            <x-icon name="info" class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <strong>{{ __('Nouveau parcours') }} :</strong>
                {{ __('la création libre d\'une sous-formation n\'est plus conseillée. Les six formations générales officielles (Santé, Informatique, Hôtellerie, Maçonnerie, Design intérieur, Design graphique) constituent le catalogue de référence.') }}
                <a href="{{ route('formations-generales.index') }}" class="ml-1 font-semibold underline">{{ __('Gérer le catalogue officiel →') }}</a>
            </div>
        </div>
    </div>

    {{-- Bandeau centre automatique (Admin Centre) : le centre n'est pas modifiable --}}
    @if($isCenterAdmin)
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 text-base text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
            <x-icon name="building" class="h-6 w-6 shrink-0" />
            <div>
                {{ __('Centre') }} : <strong>{{ $forcedCenter?->translated('name') ?? $forcedCenter?->name }}</strong>
                — {{ __('Le centre est déterminé automatiquement à partir de votre compte et ne peut pas être modifié.') }}
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('formations.store') }}" id="form-create-formation" >
        <x-ui.form-grid>
        @csrf

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 1 — CENTRE & DOMAINE DE FORMATION
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">1</span>
                    <x-icon name="building" class="h-6 w-6" />
                    {{ __('Centre & formation générale') }}
                </h2>
            </header>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                @if($isCenterAdmin)
                    {{-- Aucun champ de sélection du centre : rattachement automatique --}}
                    <div class="sm:col-span-2">
                        <label class="ec-label text-base font-semibold">{{ __('Centre de formation') }}</label>
                        <div class="ec-input flex items-center justify-between bg-slate-50 text-base text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                            <span>{{ $forcedCenter?->translated('name') ?? $forcedCenter?->name }}</span>
                            <span class="ec-badge bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">{{ __('Automatique') }}</span>
                        </div>
                        <p class="ec-help text-sm">{{ __('La formation sera rattachée exclusivement à ce centre.') }}</p>
                    </div>
                @else
                    <div>
                        <label for="center_id" class="ec-label text-base font-semibold">{{ __('Centre concerné') }} <span class="text-red-500">*</span></label>
                        <select id="center_id" name="center_id" class="ec-select text-base @error('center_id') is-invalid @enderror" required>
                            <option value="" disabled @selected(old('center_id') === null || old('center_id') === '')>{{ __('— Sélectionner le centre —') }}</option>
                            <option value="all" @selected(old('center_id') === 'all' || old('center_id') === '0')>{{ __('Tous les centres') }}</option>
                            @foreach($centers as $center)
                                <option value="{{ $center->id }}" @selected(old('center_id') == $center->id)>
                                    {{ $center->translated('name') }} ({{ $center->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('center_id') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                        <p class="ec-help text-sm">{{ __('Choisissez un centre précis ou « Tous les centres ».') }}</p>
                    </div>
                @endif

                <div class="{{ $isCenterAdmin ? 'sm:col-span-2' : '' }}">
                    <label for="domain" class="ec-label text-base font-semibold">{{ __('Formation générale') }} <span class="text-red-500">*</span></label>
                    <select id="domain" name="domain" class="ec-select text-base @error('domain') is-invalid @enderror" required>
                        <option value="">{{ __('— Sélectionner la formation générale —') }}</option>
                        @foreach($domains as $domain)
                            <option value="{{ $domain->slug }}" @selected(old('domain') === $domain->slug)>{{ $domain->name }}</option>
                        @endforeach
                    </select>
                    @error('domain') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 2 — INFORMATIONS GÉNÉRALES
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">2</span>
                    <x-icon name="book" class="h-6 w-6" />
                    {{ __('Informations générales') }}
                </h2>
            </header>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                <div class="sm:col-span-1">
                    <label for="name" class="ec-label text-base font-semibold">{{ __('Intitulé de la formation') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" class="ec-input text-base @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="{{ __('Ex : Deutsch A1 — Débutant') }}" required>
                    @error('name') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-1">
                    <label for="code" class="ec-label text-base font-semibold">{{ __('Code formation') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="code" name="code" class="ec-input uppercase text-base @error('code') is-invalid @enderror"
                           value="{{ old('code') }}" placeholder="{{ __('Ex : DE-A1') }}" required>
                    @error('code') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                    <p class="ec-help text-sm">{{ __('Unique au sein du centre (ex : DE-A1).') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="description" class="ec-label text-base font-semibold">{{ __('Description du programme') }}</label>
                    <textarea id="description" name="description" rows="4" class="ec-textarea text-base @error('description') is-invalid @enderror"
                              placeholder="{{ __('Objectifs pédagogiques, public visé, prérequis…') }}">{{ old('description') }}</textarea>
                    @error('description') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 3 — DURÉE, TARIFICATION & STATUT
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">3</span>
                    <x-icon name="clock" class="h-6 w-6" />
                    {{ __('Durée, tarification & statut') }}
                </h2>
            </header>
            <div class="grid gap-5 p-6 sm:grid-cols-3">
                <div>
                    <label for="duration_hours" class="ec-label text-base font-semibold">{{ __('Volume horaire (heures)') }}</label>
                    <input type="number" id="duration_hours" name="duration_hours" min="0" step="1" class="ec-input text-base @error('duration_hours') is-invalid @enderror"
                           value="{{ old('duration_hours') }}" placeholder="{{ __('Ex : 120') }}">
                    @error('duration_hours') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="duration_weeks" class="ec-label text-base font-semibold">{{ __('Durée (semaines)') }}</label>
                    <input type="number" id="duration_weeks" name="duration_weeks" min="0" step="1" class="ec-input text-base @error('duration_weeks') is-invalid @enderror"
                           value="{{ old('duration_weeks') }}" placeholder="{{ __('Ex : 8') }}">
                    @error('duration_weeks') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="price" class="ec-label text-base font-semibold">{{ __('Prix / Tarif') }}</label>
                    <input type="number" id="price" name="price" min="0" step="0.01" class="ec-input text-base @error('price') is-invalid @enderror"
                           value="{{ old('price') }}" placeholder="{{ __('Ex : 450.00') }}">
                    @error('price') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="currency" class="ec-label text-base font-semibold">{{ __('Devise') }}</label>
                    <select id="currency" name="currency" class="ec-select text-base @error('currency') is-invalid @enderror">
                        <option value="MAD" @selected(old('currency', 'MAD') === 'MAD')>{{ __('MAD (DH)') }}</option>
                        <option value="EUR" @selected(old('currency') === 'EUR')>{{ __('EUR (€)') }}</option>
                        <option value="USD" @selected(old('currency') === 'USD')>USD ($)</option>
                        <option value="GBP" @selected(old('currency') === 'GBP')>{{ __('GBP (£)') }}</option>
                        <option value="CHF" @selected(old('currency') === 'CHF')>{{ __('CHF (CHF)') }}</option>
                    </select>
                    @error('currency') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="status" class="ec-label text-base font-semibold">{{ __('Statut de la formation') }} <span class="text-red-500">*</span></label>
                    <select id="status" name="status" class="ec-select text-base @error('status') is-invalid @enderror" required>
                        <option value="active" @selected(old('status', 'active') === 'active')>{{ __('Actif') }}</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>{{ __('Inactif') }}</option>
                    </select>
                    @error('status') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center justify-end gap-3 pb-6">
            <a href="{{ route('formations.index') }}" class="ec-btn-secondary text-base">{{ __('Annuler') }}</a>
            <button type="submit" class="ec-btn-primary px-6 text-base" id="btn-submit-formation">
                <x-icon name="check" class="h-5 w-5" />
                {{ __('Enregistrer la formation') }}
            </button>
        </div>
        </x-ui.form-grid>
    </form>
</div>
@endsection
