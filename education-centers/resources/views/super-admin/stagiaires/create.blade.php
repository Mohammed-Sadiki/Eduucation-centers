@extends('layouts.app')

@php
    // Disposition : des cartes côte à côte sur desktop (deux colonnes équilibrées),
    // une seule colonne sur tablette/mobile. « Informations personnelles » à côté
    // de « Documents et fichiers », puis « Entreprise où le stagiaire effectuera
    // son stage » en pleine largeur. Tous les champs affichés sont obligatoires,
    // à l'exception de la section « Autres fichiers ».
    $authUser = auth()->user();
    $isCenterAdmin = $authUser->isCenterAdmin();
    $forcedCenter = $isCenterAdmin ? $authUser->center : null;
@endphp

@section('title', __('Ajouter un stagiaire') . ' — ' . config('app.name'))
@section('description', __('Création d’un dossier administratif de stagiaire.'))

@section('breadcrumb')
    <li class="flex items-center gap-2">
        <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </svg>
        <a href="{{ route('stagiaires.index') }}" class="hover:text-brand-700 dark:hover:text-brand-400 transition-colors">{{ __('Stagiaires') }}</a>
    </li>
    <li class="flex items-center gap-2">
        <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </svg>
        <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('Ajouter un stagiaire') }}</span>
    </li>
@endsection

@section('content')
<div class="max-w-7xl mx-auto py-8"
     x-data="stagiaireFormApp()">

    {{-- En-tête de page --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-200/70 px-3 py-1 text-[0.95rem] font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:border-indigo-800/70 dark:text-indigo-300 mb-3">
                {{ __('🧑‍💼 Dossier administratif de stage') }}
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Ajouter un stagiaire') }}
            </h1>
            <p class="mt-2 text-[1.0625rem] leading-relaxed text-slate-600 dark:text-slate-300">
                {{ __('Renseignez les informations personnelles du stagiaire et l\'entreprise où il effectuera son stage.') }}
            </p>
            <p class="mt-2 text-[0.95rem] text-slate-500 dark:text-slate-400" id="legende-champs-obligatoires">
                <span class="text-rose-500 font-bold">*</span> {{ __('Les champs marqués d\'un astérisque sont obligatoires.') }}
                <span class="ms-2 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[0.85rem] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('Facultatif') }}</span>
                {{ __('désigne la section « Autres fichiers », qui peut rester vide.') }}
            </p>
        </div>
        <a href="{{ route('stagiaires.index') }}" class="ec-btn-secondary text-[1.0625rem] shrink-0">
            {{ __('← Annuler et retour') }}
        </a>
    </div>

    {{-- Bandeau centre automatique (Admin Centre) : le centre n'est pas modifiable --}}
    @if($isCenterAdmin)
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 text-[1.0625rem] text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
            <x-icon name="building" class="h-6 w-6 shrink-0" />
            <div>
                {{ __('Centre') }} : <strong>{{ $forcedCenter?->translated('name') ?? $forcedCenter?->name }}</strong>
                — {{ __('Le centre est déterminé automatiquement à partir de votre compte et ne peut pas être modifié.') }}
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/50 dark:bg-rose-950/20">
            <p class="text-[1.0625rem] font-bold text-rose-800 dark:text-rose-300 mb-1">{{ __('Veuillez corriger les erreurs suivantes :') }}</p>
            <ul class="list-disc list-inside text-[0.95rem] text-rose-700 dark:text-rose-400 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('stagiaires.store') }}" enctype="multipart/form-data" id="form-create-stagiaire" data-stagiaire-form novalidate>
        @csrf

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- ══════════════════════════════════════════════════════════════
             CARTE 1 — INFORMATIONS PERSONNELLES
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-[1.0625rem] font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">1</span>
                    <x-icon name="user" class="h-6 w-6" />
                    {{ __('Informations personnelles') }}
                </h2>
            </header>

            {{-- Une colonne par champ tant que la carte est étroite (lg), deux
                 colonnes dès que sa largeur le permet (xl) : jamais de débordement. --}}
            <div class="grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                <div>
                    <label for="last_name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nom') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required data-required data-label="{{ __('Nom') }}"
                           class="ec-input text-[1.0625rem] @error('last_name') is-invalid @enderror" placeholder="{{ __('Ex: El Amrani') }}" autocomplete="family-name">
                    @error('last_name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="first_name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Prénom') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required data-required data-label="{{ __('Prénom') }}"
                           class="ec-input text-[1.0625rem] @error('first_name') is-invalid @enderror" placeholder="{{ __('Ex: Karim') }}" autocomplete="given-name">
                    @error('first_name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="date_of_birth" class="ec-label text-[1.0625rem] font-semibold">{{ __('Date de naissance') }} <span class="text-rose-500">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" required data-required data-label="{{ __('Date de naissance') }}"
                           class="ec-input text-[1.0625rem] @error('date_of_birth') is-invalid @enderror">
                    @error('date_of_birth') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="nationality" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nationalité') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="nationality" name="nationality" value="{{ old('nationality', 'Marocaine') }}" required data-required data-label="{{ __('Nationalité') }}"
                           class="ec-input text-[1.0625rem] @error('nationality') is-invalid @enderror" placeholder="{{ __('Ex: Marocaine') }}">
                    @error('nationality') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone') }} <span class="text-rose-500">*</span></label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required data-required data-label="{{ __('Numéro de téléphone') }}" inputmode="tel"
                           class="ec-input text-[1.0625rem] @error('phone') is-invalid @enderror" placeholder="+212 XXXXXXXXX">
                    @error('phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="whatsapp" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro WhatsApp') }} <span class="text-rose-500">*</span></label>
                    <input type="tel" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}" required data-required data-label="{{ __('Numéro WhatsApp') }}" inputmode="tel"
                           class="ec-input text-[1.0625rem] @error('whatsapp') is-invalid @enderror" placeholder="+212 XXXXXXXXX">
                    @error('whatsapp') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="emergency_contact_phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone d\'urgence') }} <span class="text-rose-500">*</span></label>
                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" required data-required data-label="{{ __('Numéro de téléphone d\'urgence') }}" inputmode="tel"
                           class="ec-input text-[1.0625rem] @error('emergency_contact_phone') is-invalid @enderror" placeholder="+212 XXXXXXXXX">
                    @error('emergency_contact_phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- Rattachement : sélecteur conservé pour le Super Admin uniquement.
                     Admin Centre : centre imposé côté serveur (bandeau ci-dessus). --}}
                @unless ($isCenterAdmin)
                    <div class="sm:col-span-2">
                        <label for="center_id" class="ec-label text-[1.0625rem] font-semibold">{{ __('Centre de rattachement') }} <span class="text-rose-500">*</span></label>
                        <select id="center_id" name="center_id" required data-required data-label="{{ __('Centre de rattachement') }}"
                                class="ec-select text-[1.0625rem] @error('center_id') is-invalid @enderror">
                            <option value="">{{ __('— Sélectionner un centre —') }}</option>
                            @foreach($centers as $c)
                                <option value="{{ $c->id }}" @selected(old('center_id') == $c->id)>
                                    {{ $c->translated('name') }} ({{ $c->city }})
                                </option>
                            @endforeach
                        </select>
                        @error('center_id') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                    </div>
                @endunless
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════════
             CARTE 2 — DOCUMENTS ET FICHIERS (CIN, photo, autres fichiers)
             Cartes côte à côte avec « Informations personnelles » sur desktop.
        ════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-[1.0625rem] font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">2</span>
                    <x-icon name="document" class="h-6 w-6" />
                    {{ __('Documents et fichiers') }}
                </h2>
            </header>

            <div class="space-y-5 p-6">

                {{-- ── CIN — Télécharger les photos ── --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5 dark:border-slate-700/80 dark:bg-slate-800/30">
                    <h3 class="text-[1.15rem] font-bold text-slate-800 dark:text-slate-100">{{ __('CIN — Télécharger les photos') }} <span class="text-rose-500">*</span></h3>
                    <p class="ec-help mt-1 text-[0.95rem]">{{ __('Formats autorisés : PDF, JPG, PNG — 5 Mo maximum par photo. Stockage sécurisé et privé.') }}</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <div>
                            <label for="cin_recto" class="ec-label text-[1.0625rem] font-semibold">{{ __('Photo CIN — Recto') }} <span class="text-rose-500">*</span></label>
                            <input type="file" id="cin_recto" name="cin_recto" accept=".pdf,.jpg,.jpeg,.png" required data-required data-label="{{ __('Photo CIN — Recto') }}" data-max-kb="5120"
                                   x-on:change="cinRectoName = $event.target.files[0]?.name ?? ''"
                                   class="ec-input w-full text-[1rem] @error('cin_recto') is-invalid @enderror file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
                            <p x-show="cinRectoName" x-cloak class="mt-2 inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-[0.95rem] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <span class="max-w-[240px] truncate" x-text="cinRectoName"></span>
                                <button type="button" x-on:click="clearFile('cin_recto'); cinRectoName = ''" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                            </p>
                            @error('cin_recto') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="cin_verso" class="ec-label text-[1.0625rem] font-semibold">{{ __('Photo CIN — Verso') }} <span class="text-rose-500">*</span></label>
                            <input type="file" id="cin_verso" name="cin_verso" accept=".pdf,.jpg,.jpeg,.png" required data-required data-label="{{ __('Photo CIN — Verso') }}" data-max-kb="5120"
                                   x-on:change="cinVersoName = $event.target.files[0]?.name ?? ''"
                                   class="ec-input w-full text-[1rem] @error('cin_verso') is-invalid @enderror file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
                            <p x-show="cinVersoName" x-cloak class="mt-2 inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-[0.95rem] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <span class="max-w-[240px] truncate" x-text="cinVersoName"></span>
                                <button type="button" x-on:click="clearFile('cin_verso'); cinVersoName = ''" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                            </p>
                            @error('cin_verso') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- ── Photo de profil avec aperçu ── --}}
                <div>
                    <label class="ec-label text-[1.0625rem] font-semibold">{{ __('Photo de profil') }} <span class="text-rose-500">*</span></label>
                    <p class="ec-help text-[0.95rem]">{{ __('Téléchargez la photo du stagiaire (JPG ou PNG, 5 Mo maximum). Un aperçu s\'affiche avant l\'enregistrement.') }}</p>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-slate-300 bg-slate-50 text-slate-400 dark:border-slate-600 dark:bg-slate-800">
                            <img x-show="profilePreview" x-cloak :src="profilePreview" alt="{{ __('Aperçu de la photo') }}" class="h-full w-full object-cover">
                            <x-icon name="user" x-show="!profilePreview" x-cloak class="h-9 w-9 text-slate-400" />
                        </div>
                        <div class="w-full sm:max-w-md">
                            <input type="file" id="profile_photo" name="profile_photo" x-ref="profileInput" accept="image/jpeg,image/png" required data-required data-label="{{ __('Photo de profil') }}" data-max-kb="5120"
                                   x-on:change="onProfileChange($event)"
                                   class="block w-full text-[1rem] @error('profile_photo') is-invalid @enderror file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-300">
                        </div>
                    </div>
                    <p x-show="profileName" x-cloak class="ec-help mt-1 text-[0.95rem]" x-text="profileName"></p>
                    @error('profile_photo') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- ── Autres fichiers (section facultative) ── --}}
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 p-5 dark:border-slate-600 dark:bg-slate-800/30">
                    <h3 class="text-[1.15rem] font-bold text-slate-800 dark:text-slate-100">
                        {{ __('Autres fichiers') }}
                        <span class="ms-2 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[0.85rem] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('Facultatif') }}</span>
                    </h3>
                    <p class="ec-help mt-1 text-[0.95rem]">{{ __('PDF, JPG, PNG, DOC, DOCX — 8 Mo maximum par fichier. Chaque fichier peut être retiré avant l\'enregistrement.') }}</p>

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
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             CARTE 3 — ENTREPRISE OÙ LE STAGIAIRE EFFECTUERA SON STAGE
             Dernière section : pleine largeur sur desktop.
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden lg:col-span-2">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-[1.0625rem] font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">3</span>
                    <x-icon name="building" class="h-6 w-6" />
                    {{ __('Entreprise où le stagiaire effectuera son stage') }}
                </h2>
            </header>

            <div class="grid gap-5 p-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="company_name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nom de l\'entreprise') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}" required data-required data-label="{{ __('Nom de l\'entreprise') }}"
                           class="ec-input text-[1.0625rem] @error('company_name') is-invalid @enderror" placeholder="{{ __('Ex: Atlas Technologies') }}">
                    @error('company_name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company_phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone de l\'entreprise') }} <span class="text-rose-500">*</span></label>
                    <input type="tel" id="company_phone" name="company_phone" value="{{ old('company_phone') }}" required data-required data-label="{{ __('Numéro de téléphone de l\'entreprise') }}" inputmode="tel"
                           class="ec-input text-[1.0625rem] @error('company_phone') is-invalid @enderror" placeholder="+212 XXXXXXXXX">
                    @error('company_phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company_location" class="ec-label text-[1.0625rem] font-semibold">{{ __('Lieu de l\'entreprise') }} <span class="text-rose-500">*</span></label>
                    <input type="text" id="company_location" name="company_location" value="{{ old('company_location') }}" required data-required data-label="{{ __('Lieu de l\'entreprise') }}"
                           class="ec-input text-[1.0625rem] @error('company_location') is-invalid @enderror" placeholder="{{ __('Ex: Casablanca, Maroc') }}">
                    @error('company_location') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Boutons d'action --}}
        <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800 lg:col-span-2">
            <a href="{{ route('stagiaires.index') }}" class="ec-btn-secondary text-[1.0625rem] px-6">{{ __('Annuler') }}</a>
            <button type="submit" id="btn-submit-stagiaire" class="ec-btn-primary px-6 text-[1.0625rem]">
                <x-icon name="check" class="h-5 w-5" />
                {{ __('Enregistrer le stagiaire') }}
            </button>
        </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function stagiaireFormApp() {
        return {
            profileName: '',
            profilePreview: '',
            cinRectoName: '',
            cinVersoName: '',
            otherFiles: [],

            onProfileChange(event) {
                const file = event.target.files[0];
                if (!file) { this.profileName = ''; this.profilePreview = ''; return; }
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

    {{-- Validation côté navigateur partagée avec le formulaire de modification. --}}
    @include('super-admin.stagiaires.partials.form-validation')
@endpush
