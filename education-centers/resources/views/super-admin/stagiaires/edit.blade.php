@extends('layouts.app')

@php
    // Le formulaire de modification reprend à l'identique la présentation du
    // formulaire « Ajouter un stagiaire » : deux cartes (Informations personnelles,
    // Entreprise de stage). Les pièces déjà enregistrées (scans CIN, photo de
    // profil, documents) sont conservées lorsqu'aucun nouveau fichier n'est envoyé.
    $authUser = auth()->user();
    $isCenterAdmin = $authUser->isCenterAdmin();
    $forcedCenter = $isCenterAdmin ? $authUser->center : null;
    $photoUrl = $stagiaire->profile_photo_path ? route('stagiaires.profile-photo', $stagiaire) : '';
@endphp

@section('title', __('Modifier le stagiaire') . ' — ' . $stagiaire->first_name . ' ' . $stagiaire->last_name)

@section('content')
<div class="max-w-7xl mx-auto py-8 space-y-6"
     x-data="stagiaireEditFormApp(@js($photoUrl))">

    {{-- Fil d'Ariane & En-tête --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <nav class="flex items-center gap-2 text-[0.95rem] text-slate-500 dark:text-slate-400 mb-2">
                <a href="{{ route('stagiaires.index') }}" class="hover:text-slate-800 dark:hover:text-slate-200">{{ __('Stagiaires') }}</a>
                <span>/</span>
                <a href="{{ route('stagiaires.show', $stagiaire) }}" class="hover:text-slate-800 dark:hover:text-slate-200">{{ $stagiaire->first_name }} {{ $stagiaire->last_name }}</a>
                <span>/</span>
                <span class="text-slate-700 font-semibold dark:text-slate-300">{{ __('Modifier') }}</span>
            </nav>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Modifier le dossier :') }} {{ $stagiaire->first_name }} {{ $stagiaire->last_name }}
            </h1>
            <p class="mt-2 text-[1.0625rem] leading-relaxed text-slate-600 dark:text-slate-300">
                {{ __('Mise à jour des informations personnelles et de l\'entreprise de stage.') }}
            </p>
            <p class="mt-2 text-[0.95rem] text-slate-500 dark:text-slate-400">
                <span class="text-rose-500 font-bold">*</span> {{ __('Les champs marqués d\'un astérisque sont obligatoires.') }}
                <span class="ms-2 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[0.85rem] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('Facultatif') }}</span>
                {{ __('désigne la section « Autres fichiers », qui peut rester vide.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('stagiaires.show', $stagiaire) }}"
               class="ec-btn-secondary text-[1.0625rem]">
                <x-icon name="chevron-left" class="h-4 w-4" />
                <span>{{ __('Voir le dossier') }}</span>
            </a>
        </div>
    </div>

    {{-- Bandeau centre automatique (Admin Centre) --}}
    @if($isCenterAdmin)
        <div class="flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 text-[1.0625rem] text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
            <x-icon name="building" class="h-6 w-6 shrink-0" />
            <div>
                {{ __('Centre') }} : <strong>{{ $forcedCenter?->translated('name') ?? $forcedCenter?->name }}</strong>
                — {{ __('Le centre est déterminé automatiquement à partir de votre compte et ne peut pas être modifié.') }}
            </div>
        </div>
    @endif

    {{-- Erreurs globales --}}
    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
            <div class="text-[1.0625rem] font-bold mb-1 flex items-center gap-1.5 text-rose-800 dark:text-rose-300">
                <x-icon name="warning" class="h-5 w-5" />
                <span>{{ __('Veuillez corriger les erreurs ci-dessous :') }}</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-[0.95rem] text-rose-700 dark:text-rose-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('stagiaires.update', $stagiaire) }}"
          method="POST"
          enctype="multipart/form-data"
          id="form-edit-stagiaire"
          data-stagiaire-form
          novalidate>
        @csrf
        @method('PUT')

        <x-ui.form-grid>

        {{-- ══════════════════════════════════════════════════════════════
             CARTE 1 — INFORMATIONS PERSONNELLES
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden lg:col-span-2">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-[1.0625rem] font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">1</span>
                    <x-icon name="user" class="h-6 w-6" />
                    {{ __('Informations personnelles') }}
                </h2>
            </header>

            <div class="grid gap-5 p-6 sm:grid-cols-2">
                <div>
                    <label for="last_name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nom') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $stagiaire->last_name) }}" required data-required data-label="{{ __('Nom') }}"
                           class="ec-input text-[1.0625rem] @error('last_name') is-invalid @enderror" autocomplete="family-name">
                    @error('last_name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="first_name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Prénom') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $stagiaire->first_name) }}" required data-required data-label="{{ __('Prénom') }}"
                           class="ec-input text-[1.0625rem] @error('first_name') is-invalid @enderror" autocomplete="given-name">
                    @error('first_name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="date_of_birth" class="ec-label text-[1.0625rem] font-semibold">{{ __('Date de naissance') }} <span class="text-rose-500">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $stagiaire->date_of_birth?->format('Y-m-d')) }}" required data-required data-label="{{ __('Date de naissance') }}"
                           class="ec-input text-[1.0625rem] @error('date_of_birth') is-invalid @enderror @error('birth_date') is-invalid @enderror">
                    @error('date_of_birth') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                    @error('birth_date') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="nationality" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nationalité') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="nationality" name="nationality" value="{{ old('nationality', $stagiaire->nationality) }}" required data-required data-label="{{ __('Nationalité') }}"
                           class="ec-input text-[1.0625rem] @error('nationality') is-invalid @enderror">
                    @error('nationality') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone') }} <span class="text-rose-500">*</span></label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $stagiaire->phone) }}" required data-required data-label="{{ __('Numéro de téléphone') }}" inputmode="tel"
                           class="ec-input text-[1.0625rem] @error('phone') is-invalid @enderror" placeholder="+212 XXXXXXXXX">
                    @error('phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- ── CIN — Télécharger les photos ── --}}
                <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50/50 p-5 dark:border-slate-700/80 dark:bg-slate-800/30">
                    <h3 class="text-[1.15rem] font-bold text-slate-800 dark:text-slate-100">{{ __('CIN — Télécharger les photos') }}</h3>
                    <p class="ec-help mt-1 text-[0.95rem]">{{ __('Formats autorisés : PDF, JPG, PNG — 5 Mo maximum par photo. Stockage sécurisé et privé.') }}</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="cin_recto" class="ec-label text-[1.0625rem] font-semibold">
                                {{ __('Photo CIN — Recto') }}
                                @if($stagiaire->cin_recto_path)
                                    <span class="ms-1 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[0.85rem] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('Facultatif') }}</span>
                                @else
                                    <span class="text-rose-500">*</span>
                                @endif
                            </label>

                            @if($stagiaire->cin_recto_path)
                                <div class="mb-2 flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-[0.95rem] text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    <span>{{ __('Fichier actuel sauvegardé') }}</span>
                                    <a href="{{ route('stagiaires.cin-download', [$stagiaire, 'recto']) }}" class="font-bold underline">{{ __('Télécharger') }}</a>
                                </div>
                            @endif

                            <input type="file" id="cin_recto" name="cin_recto" accept=".pdf,.jpg,.jpeg,.png" data-max-kb="5120" data-label="{{ __('Photo CIN — Recto') }}"
                                   @if(! $stagiaire->cin_recto_path) required data-required @endif
                                   x-on:change="cinRectoName = $event.target.files[0]?.name ?? ''"
                                   class="ec-input w-full text-[1rem] @error('cin_recto') is-invalid @enderror file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
                            <p x-show="cinRectoName" x-cloak class="mt-2 inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-[0.95rem] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <span class="max-w-[240px] truncate" x-text="cinRectoName"></span>
                                <button type="button" x-on:click="clearFile('cin_recto'); cinRectoName = ''" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                            </p>
                            <p class="ec-help mt-1 text-[0.95rem]">{{ __('Aucun nouveau fichier : la pièce enregistrée est conservée.') }}</p>
                            @error('cin_recto') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="cin_verso" class="ec-label text-[1.0625rem] font-semibold">
                                {{ __('Photo CIN — Verso') }}
                                @if($stagiaire->cin_verso_path)
                                    <span class="ms-1 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[0.85rem] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('Facultatif') }}</span>
                                @else
                                    <span class="text-rose-500">*</span>
                                @endif
                            </label>

                            @if($stagiaire->cin_verso_path)
                                <div class="mb-2 flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-[0.95rem] text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    <span>{{ __('Fichier actuel sauvegardé') }}</span>
                                    <a href="{{ route('stagiaires.cin-download', [$stagiaire, 'verso']) }}" class="font-bold underline">{{ __('Télécharger') }}</a>
                                </div>
                            @endif

                            <input type="file" id="cin_verso" name="cin_verso" accept=".pdf,.jpg,.jpeg,.png" data-max-kb="5120" data-label="{{ __('Photo CIN — Verso') }}"
                                   @if(! $stagiaire->cin_verso_path) required data-required @endif
                                   x-on:change="cinVersoName = $event.target.files[0]?.name ?? ''"
                                   class="ec-input w-full text-[1rem] @error('cin_verso') is-invalid @enderror file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
                            <p x-show="cinVersoName" x-cloak class="mt-2 inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-[0.95rem] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <span class="max-w-[240px] truncate" x-text="cinVersoName"></span>
                                <button type="button" x-on:click="clearFile('cin_verso'); cinVersoName = ''" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                            </p>
                            <p class="ec-help mt-1 text-[0.95rem]">{{ __('Aucun nouveau fichier : la pièce enregistrée est conservée.') }}</p>
                            @error('cin_verso') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- ── Photo de profil avec aperçu ── --}}
                <div class="sm:col-span-2">
                    <label class="ec-label text-[1.0625rem] font-semibold">
                        {{ __('Photo de profil') }}
                        @if($photoUrl)
                            <span class="ms-1 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[0.85rem] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('Facultatif') }}</span>
                        @else
                            <span class="text-rose-500">*</span>
                        @endif
                    </label>
                    <p class="ec-help text-[0.95rem]">{{ __('JPG ou PNG, 5 Mo maximum. Un aperçu s\'affiche avant l\'enregistrement ; sans nouveau fichier, la photo actuelle est conservée.') }}</p>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-slate-300 bg-slate-50 text-slate-400 dark:border-slate-600 dark:bg-slate-800">
                            <img :src="profilePreview" x-show="profilePreview" alt="{{ __('Photo actuelle') }}" class="h-full w-full object-cover">
                            <x-icon name="user" x-show="!profilePreview" class="h-9 w-9 text-slate-400" />
                        </div>
                        <div class="w-full sm:max-w-md">
                            <input type="file" id="profile_photo" name="profile_photo" x-ref="profileInput" accept="image/jpeg,image/png" data-max-kb="5120" data-label="{{ __('Photo de profil') }}"
                                   @if(! $photoUrl) required data-required @endif
                                   x-on:change="onProfileChange($event)"
                                   class="block w-full text-[1rem] @error('profile_photo') is-invalid @enderror file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-300">
                        </div>
                    </div>
                    <p x-show="profileName" x-cloak class="ec-help mt-1 text-[0.95rem]" x-text="profileName"></p>
                    @error('profile_photo') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="whatsapp" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro WhatsApp') }} <span class="text-rose-500">*</span></label>
                    <input type="tel" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $stagiaire->whatsapp) }}" required data-required data-label="{{ __('Numéro WhatsApp') }}" inputmode="tel"
                           class="ec-input text-[1.0625rem] @error('whatsapp') is-invalid @enderror" placeholder="+212 XXXXXXXXX">
                    @error('whatsapp') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="emergency_contact_phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone d\'urgence') }} <span class="text-rose-500">*</span></label>
                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $stagiaire->emergency_contact_phone) }}" required data-required data-label="{{ __('Numéro de téléphone d\'urgence') }}" inputmode="tel"
                           class="ec-input text-[1.0625rem] @error('emergency_contact_phone') is-invalid @enderror" placeholder="+212 XXXXXXXXX">
                    @error('emergency_contact_phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- ── Autres fichiers (section facultative) ── --}}
                <div class="sm:col-span-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 p-5 dark:border-slate-600 dark:bg-slate-800/30">
                    <h3 class="text-[1.15rem] font-bold text-slate-800 dark:text-slate-100">
                        {{ __('Autres fichiers') }}
                        <span class="ms-2 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[0.85rem] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('Facultatif') }}</span>
                    </h3>
                    <p class="ec-help mt-1 text-[0.95rem]">{{ __('PDF, JPG, PNG, DOC, DOCX — 8 Mo maximum par fichier. Les fichiers déjà enregistrés sont conservés.') }}</p>

                    @if($stagiaire->documents->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            <h4 class="text-[0.95rem] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Fichiers existants :') }}</h4>
                            @foreach($stagiaire->documents as $doc)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-[0.95rem] dark:border-slate-700/60 dark:bg-slate-800/40">
                                    <span class="min-w-0 truncate font-semibold text-slate-800 dark:text-slate-100">{{ $doc->title }}</span>
                                    <a href="{{ route('stagiaires.document-download', [$stagiaire, $doc]) }}"
                                       class="shrink-0 font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ __('Télécharger') }}</a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <input type="file" name="other_files[]" x-ref="otherInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple data-optional-files data-max-kb="8192"
                           x-on:change="addOtherFiles($event)" class="sr-only" tabindex="-1">
                    <ul x-show="otherFiles.length" x-cloak class="mt-3 flex flex-wrap gap-2">
                        <template x-for="(f, i) in otherFiles" :key="i">
                            <li class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-[0.95rem] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <span class="max-w-[220px] truncate" x-text="f.name"></span>
                                <button type="button" x-on:click="removeOtherFile(i)" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                            </li>
                        </template>
                    </ul>
                    <div class="mt-3">
                        <button type="button" id="btn-add-other-file" x-on:click="$refs.otherInput.click()"
                                class="inline-flex items-center gap-2 rounded-xl border border-dashed border-brand-300 px-5 py-2.5 text-[1.0625rem] font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-300 dark:hover:bg-brand-500/10 transition-colors">
                            <x-icon name="plus" class="h-5 w-5" /> {{ __('Ajouter un fichier') }}
                        </button>
                    </div>
                    @error('other_files.*') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- Rattachement : sélecteur conservé pour le Super Admin uniquement. --}}
                @unless ($isCenterAdmin)
                    <div class="sm:col-span-2">
                        <label for="center_id" class="ec-label text-[1.0625rem] font-semibold">{{ __('Centre de rattachement') }} <span class="text-rose-500">*</span></label>
                        <select id="center_id" name="center_id" required data-required data-label="{{ __('Centre de rattachement') }}"
                                class="ec-select text-[1.0625rem] @error('center_id') is-invalid @enderror">
                            <option value="">{{ __('— Sélectionner un centre —') }}</option>
                            @foreach($centers as $c)
                                <option value="{{ $c->id }}" @selected(old('center_id', $stagiaire->center_id) == $c->id)>
                                    {{ $c->translated('name') }} ({{ $c->city }})
                                </option>
                            @endforeach
                        </select>
                        @error('center_id') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                    </div>
                @endunless
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             CARTE 2 — ENTREPRISE OÙ LE STAGIAIRE EFFECTUERA SON STAGE
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-[1.0625rem] font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">2</span>
                    <x-icon name="building" class="h-6 w-6" />
                    {{ __('Entreprise où le stagiaire effectuera son stage') }}
                </h2>
            </header>

            <div class="grid gap-5 p-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="company_name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nom de l\'entreprise') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="company_name" name="company_name" value="{{ old('company_name', $stagiaire->company_name) }}" required data-required data-label="{{ __('Nom de l\'entreprise') }}"
                           class="ec-input text-[1.0625rem] @error('company_name') is-invalid @enderror">
                    @error('company_name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company_phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone de l\'entreprise') }} <span class="text-rose-500">*</span></label>
                    <input type="tel" id="company_phone" name="company_phone" value="{{ old('company_phone', $stagiaire->company_phone) }}" required data-required data-label="{{ __('Numéro de téléphone de l\'entreprise') }}" inputmode="tel"
                           class="ec-input text-[1.0625rem] @error('company_phone') is-invalid @enderror" placeholder="+212 XXXXXXXXX">
                    @error('company_phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company_location" class="ec-label text-[1.0625rem] font-semibold">{{ __('Lieu de l\'entreprise') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="company_location" name="company_location" value="{{ old('company_location', $stagiaire->company_location) }}" required data-required data-label="{{ __('Lieu de l\'entreprise') }}"
                           class="ec-input text-[1.0625rem] @error('company_location') is-invalid @enderror">
                    @error('company_location') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Barre d'actions --}}
        <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800 lg:col-span-2">
            <a href="{{ route('stagiaires.show', $stagiaire) }}" class="ec-btn-secondary text-[1.0625rem] px-6">{{ __('Annuler') }}</a>
            <button type="submit" id="btn-update-stagiaire" class="ec-btn-primary px-6 text-[1.0625rem]">
                <x-icon name="check" class="h-5 w-5" />
                <span>{{ __('Enregistrer les modifications') }}</span>
            </button>
        </div>
        </x-ui.form-grid>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function stagiaireEditFormApp(currentPhotoUrl) {
        return {
            profileName: '',
            profilePreview: currentPhotoUrl || '',
            cinRectoName: '',
            cinVersoName: '',
            otherFiles: [],

            onProfileChange(event) {
                const file = event.target.files[0];
                if (!file) {
                    this.profileName = '';
                    this.profilePreview = @js($photoUrl);
                    return;
                }
                this.profileName = file.name;
                const reader = new FileReader();
                reader.onload = (e) => { this.profilePreview = e.target.result; };
                reader.readAsDataURL(file);
            },

            clearFile(id) {
                const input = document.getElementById(id);
                if (input) input.value = '';
            },

            addOtherFiles(event) {
                const input = event.target;
                const dt = new DataTransfer();
                this.otherFiles.forEach(f => dt.items.add(f));
                Array.from(input.files).forEach(f => dt.items.add(f));
                this.otherFiles = Array.from(dt.files);
                input.files = dt.files;
            },

            removeOtherFile(index) {
                const dt = new DataTransfer();
                this.otherFiles.forEach((f, i) => { if (i !== index) dt.items.add(f); });
                this.otherFiles = Array.from(dt.files);
                if (this.$refs.otherInput) this.$refs.otherInput.files = dt.files;
            },
        };
    }
</script>

    {{-- Validation côté navigateur partagée avec le formulaire d'ajout. --}}
    @include('super-admin.stagiaires.partials.form-validation')
@endpush
