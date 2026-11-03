@extends('layouts.standalone')

@section('title', __('Ajouter un utilisateur') . ' — ' . config('app.name'))
@section('description', __('Création d\'un compte utilisateur professeur pour le système.'))
@section('standalone_title', __('Ajouter un utilisateur'))

@php
    $authUser = auth()->user();
    $isCenterAdmin = $authUser->isCenterAdmin();
    $forcedCenter = $isCenterAdmin ? $authUser->center : null;
    $backUrl = route('users.index');
@endphp

@section('standalone_back_url', $backUrl)
@section('standalone_back_label', __('Retour aux utilisateurs'))

@section('content')
<div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6"
     x-data="userTeacherFormApp({
         formations: @js($formationsData),
         isCenterAdmin: @js($isCenterAdmin),
         forcedCenterId: @js($isCenterAdmin ? (int) $authUser->center_id : null),
         initialCenterId: @js(old('center_id', $isCenterAdmin ? $authUser->center_id : '')),
         initialFormationId: @js(old('formation_id', '')),
     })">

    {{-- Titre de page --}}
    <x-ui.page-header
        :title="__('Ajouter un utilisateur')"
        :description="__('Création d\'un compte professeur permettant l\'accès à l\'application. Les élèves sont gérés en dossiers administratifs.')" />

    {{-- Bandeau centre automatique (Admin Centre) --}}
    @if($isCenterAdmin)
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 text-base text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
            <x-icon name="building" class="h-6 w-6 shrink-0" />
            <div>
                {{ __('Centre') }} : <strong>{{ $forcedCenter?->translated('name') ?? $forcedCenter?->name }}</strong>
                — {{ __('Le compte professeur sera automatiquement rattaché à votre centre.') }}
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('users.store') }}" id="form-create-user" >
        <x-ui.form-grid>
        @csrf

        {{-- Rôle imposé automatiquement : Professeur (vérifié et sécurisé serveur) --}}
        <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_TEACHER }}">

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 1 — IDENTITÉ & INFORMATIONS DU COMPTE
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">1</span>
                    <x-icon name="user" class="h-6 w-6" />
                    {{ __('Identité & coordonnées') }}
                </h2>
            </header>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                {{-- Nom complet --}}
                <div class="sm:col-span-1">
                    <label for="name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nom complet') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" class="ec-input text-[1.0625rem] @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="{{ __('Ex : Jean Dupont') }}" required autocomplete="name" autofocus>
                    @error('name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- Adresse e-mail --}}
                <div class="sm:col-span-1">
                    <label for="email" class="ec-label text-[1.0625rem] font-semibold">{{ __('Adresse e-mail') }} <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" class="ec-input text-[1.0625rem] @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" placeholder="{{ __('professeur@education-centers.de') }}" required autocomplete="email">
                    @error('email') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- Téléphone --}}
                <div class="sm:col-span-1">
                    <label for="phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone') }}</label>
                    <input type="tel" id="phone" name="phone" class="ec-input text-[1.0625rem] @error('phone') is-invalid @enderror"
                           value="{{ old('phone') }}" placeholder="+212 XXXXXXXXX" autocomplete="tel">
                    @error('phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- Type de compte (Fixé à Professeur) --}}
                <div class="sm:col-span-1">
                    <label class="ec-label text-[1.0625rem] font-semibold">{{ __('Type de compte') }}</label>
                    <div class="ec-input flex items-center justify-between bg-slate-50 text-[1.0625rem] text-slate-700 dark:bg-slate-800/60 dark:text-slate-200 font-bold">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                            {{ __('Professeur') }}
                        </span>
                        <span class="ec-badge bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ __('Automatique') }}</span>
                    </div>
                    <p class="ec-help text-[0.95rem]">{{ __('Ce formulaire permet de créer uniquement des comptes professeurs.') }}</p>
                </div>
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 2 — RATTACHEMENT AU CENTRE & STATUT
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">2</span>
                    <x-icon name="building" class="h-6 w-6" />
                    {{ __('Centre & statut') }}
                </h2>
            </header>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                {{-- LIGNE 1 : Centre de rattachement & Statut du compte --}}
                @if($isCenterAdmin)
                    <div class="sm:col-span-1">
                        <label class="ec-label text-[1.0625rem] font-semibold">{{ __('Centre de rattachement') }}</label>
                        <div class="ec-input flex items-center justify-between bg-slate-50 text-[1.0625rem] text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                            <span>{{ $forcedCenter?->translated('name') ?? $forcedCenter?->name }}</span>
                            <span class="ec-badge bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">{{ __('Automatique') }}</span>
                        </div>
                    </div>
                @else
                    <div class="sm:col-span-1">
                        <label for="center_id" class="ec-label text-[1.0625rem] font-semibold">{{ __('Centre de rattachement') }} <span class="text-red-500">*</span></label>
                        <select id="center_id" name="center_id" class="ec-select text-[1.0625rem] @error('center_id') is-invalid @enderror"
                                x-model="centerId" x-on:change="applyCascade()" required>
                            <option value="">{{ __('— Sélectionner le centre —') }}</option>
                            @foreach($centers as $center)
                                <option value="{{ $center->id }}" @selected(old('center_id') == $center->id)>
                                    {{ method_exists($center, 'translated') ? ($center->translated('name') ?? $center->name) : $center->name }} ({{ $center->city }})
                                </option>
                            @endforeach
                        </select>
                        @error('center_id') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                    </div>
                @endif

                {{-- Statut du compte --}}
                <div class="sm:col-span-1">
                    <label for="status" class="ec-label text-[1.0625rem] font-semibold">{{ __('Statut du compte') }} <span class="text-red-500">*</span></label>
                    <select id="status" name="status" class="ec-select text-[1.0625rem] @error('status') is-invalid @enderror" required>
                        <option value="active" @selected(old('status', 'active') === 'active')>{{ __('Actif') }}</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>{{ __('Inactif') }}</option>
                    </select>
                    @error('status') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- LIGNE 2 : Formation enseignée (sélection directe, sans domaine) --}}
                <div class="sm:col-span-2">
                    <label for="formation_id" class="ec-label text-[1.0625rem] font-semibold">{{ __('Formation') }} <span class="text-red-500">*</span></label>
                    <select id="formation_id" name="formation_id" class="ec-select text-[1.0625rem] @error('formation_id') is-invalid @enderror"
                            x-model="formationId" required>
                        <option value="">{{ __('— Sélectionner la formation —') }}</option>
                        @foreach($formationsData as $formation)
                            <option value="{{ $formation['id'] }}"
                                    data-center-id="{{ $formation['center_id'] }}"
                                    @selected(old('formation_id') == $formation['id'])>
                                {{ $formation['name'] }} ({{ $formation['code'] }})
                            </option>
                        @endforeach
                    </select>
                    @error('formation_id') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror

                    <p class="ec-help mt-1 text-[0.95rem]">
                        {{ __('Le professeur est rattaché à une des six formations générales officielles du centre. Les sous-formations legacy ne sont plus proposées.') }}
                    </p>
                </div>
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 3 — SÉCURITÉ DU MOT DE PASSE
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">3</span>
                    <x-icon name="lock" class="h-6 w-6" />
                    {{ __('Sécurité du mot de passe') }}
                </h2>
            </header>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                <div>
                    <label for="password" class="ec-label text-[1.0625rem] font-semibold">{{ __('Mot de passe') }} <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" class="ec-input text-[1.0625rem] @error('password') is-invalid @enderror"
                           placeholder="{{ __('Min. 8 caractères') }}" required autocomplete="new-password">
                    @error('password') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="ec-label text-[1.0625rem] font-semibold">{{ __('Confirmer le mot de passe') }} <span class="text-red-500">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="ec-input text-[1.0625rem]"
                           placeholder="{{ __('Répéter le mot de passe') }}" required autocomplete="new-password">
                </div>
            </div>
        </section>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center justify-end gap-3 pb-6">
            <a href="{{ route('users.index') }}" class="ec-btn-secondary text-[1.0625rem]">{{ __('Annuler') }}</a>
            <button type="submit" class="ec-btn-primary px-7 text-[1.0625rem]" id="btn-submit-user">
                <x-icon name="check" class="h-5 w-5" />
                {{ __('Créer le compte professeur') }}
            </button>
        </div>
        </x-ui.form-grid>
    </form>
</div>

<script>
function userTeacherFormApp(config) {
    return {
        formations: config.formations,
        isCenterAdmin: config.isCenterAdmin,
        forcedCenterId: config.forcedCenterId,
        centerId: config.initialCenterId ? String(config.initialCenterId) : '',
        formationId: config.initialFormationId ? String(config.initialFormationId) : '',
        visibleCount: 0,

        get effectiveCenterId() {
            return this.isCenterAdmin ? String(this.forcedCenterId) : String(this.centerId || '');
        },

        init() {
            this.applyCascade(true);
        },

        // Filtre par centre (Super Admin multi-centres). Aucune étape Domaine :
        // le catalogue ne contient que des formations générales officielles.
        applyCascade(isInitial = false) {
            const center = this.effectiveCenterId;
            const formationSelect = document.getElementById('formation_id');
            if (!formationSelect) return;

            let count = 0;
            let currentStillValid = false;

            formationSelect.querySelectorAll('option[data-center-id]').forEach((option) => {
                const match = center === '' || option.dataset.centerId === center;

                option.hidden = !match;
                option.disabled = !match;

                if (match) {
                    count++;
                    if (String(option.value) === String(this.formationId)) {
                        currentStillValid = true;
                    }
                }
            });

            this.visibleCount = count;

            if (!isInitial && !currentStillValid) {
                this.formationId = '';
                formationSelect.value = '';
            }
        }
    };
}
</script>
@endsection
