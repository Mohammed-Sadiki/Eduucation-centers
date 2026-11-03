@extends('layouts.standalone')

@section('title', __('Ajouter un élève') . ' — ' . config('app.name'))
@section('description', __('Inscription d\'un élève à une formation générale avec paiement intégré.'))
@section('standalone_title', __('Ajouter un élève'))

@php
    $authUser = auth()->user();
    $isCenterAdmin = $authUser->isCenterAdmin();
    $forcedCenter = $isCenterAdmin ? $authUser->center : null;
    $backUrl = $isCenterAdmin ? route('formations-generales.index') : route('formations-generales.index');
    $currencySymbol = config('currency.symbol', 'DH');
    $preselected = $preselectedFormationId ?? null;
@endphp

@section('standalone_back_url', $backUrl)
@section('standalone_back_label', __('Retour aux formations'))

@section('content')
<div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6"
     x-data="enrollmentFormApp({
         formations: @js($formationsData),
         isCenterAdmin: @js($isCenterAdmin),
         forcedCenterId: @js($isCenterAdmin ? (int) $authUser->center_id : null),
         currencySymbol: @js($currencySymbol),
     })">

    {{-- Titre de page --}}
    <x-ui.page-header
        :title="__('Ajouter un élève')"
        :description="__('Renseignez l\'élève, choisissez le centre et la formation générale, puis enregistrez le paiement — tout sera transmis à Gestion financière.')" />

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

    <form method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data"
          id="form-create-student">
        @csrf

        <x-ui.form-grid>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 1 — INFORMATIONS PERSONNELLES
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">1</span>
                    <x-icon name="user" class="h-6 w-6" />
                    {{ __('Informations personnelles') }}
                </h2>
            </header>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                <div>
                    <label for="last_name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nom') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="ec-input text-[1.0625rem] @error('last_name') is-invalid @enderror"
                           value="{{ old('last_name') }}" required autocomplete="family-name">
                    @error('last_name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="first_name" class="ec-label text-[1.0625rem] font-semibold">{{ __('Prénom') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="ec-input text-[1.0625rem] @error('first_name') is-invalid @enderror"
                           value="{{ old('first_name') }}" required autocomplete="given-name">
                    @error('first_name') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="date_of_birth" class="ec-label text-[1.0625rem] font-semibold">{{ __('Date de naissance') }}</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="ec-input text-[1.0625rem] @error('date_of_birth') is-invalid @enderror"
                           value="{{ old('date_of_birth') }}">
                    @error('date_of_birth') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nationality" class="ec-label text-[1.0625rem] font-semibold">{{ __('Nationalité') }}</label>
                    <input type="text" id="nationality" name="nationality" class="ec-input text-[1.0625rem] @error('nationality') is-invalid @enderror"
                           value="{{ old('nationality') }}">
                    @error('nationality') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone') }}</label>
                    <input type="tel" id="phone" name="phone" class="ec-input text-[1.0625rem] @error('phone') is-invalid @enderror"
                           value="{{ old('phone') }}" placeholder="+212 XXXXXXXXX">
                    @error('phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="whatsapp" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone WhatsApp') }}</label>
                    <input type="tel" id="whatsapp" name="whatsapp" class="ec-input text-[1.0625rem] @error('whatsapp') is-invalid @enderror"
                           value="{{ old('whatsapp') }}" placeholder="+212 XXXXXXXXX">
                    @error('whatsapp') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="emergency_contact_phone" class="ec-label text-[1.0625rem] font-semibold">{{ __('Numéro de téléphone d\'urgence') }}</label>
                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="ec-input text-[1.0625rem] @error('emergency_contact_phone') is-invalid @enderror"
                           value="{{ old('emergency_contact_phone') }}" placeholder="+212 XXXXXXXXX">
                    @error('emergency_contact_phone') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 2 — PIÈCES ET FICHIERS
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">2</span>
                    <x-icon name="document" class="h-6 w-6" />
                    {{ __('Pièces et fichiers') }}
                </h2>
            </header>
            <div class="grid gap-6 p-6">

                {{-- Photo de profil + aperçu --}}
                <div>
                    <label class="ec-label text-[1.0625rem] font-semibold">{{ __('Photo de profil') }}</label>
                    <p class="ec-help text-[0.95rem]">{{ __('JPG ou PNG, 5 Mo maximum. Un aperçu s\'affiche avant l\'enregistrement.') }}</p>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-slate-300 bg-slate-50 text-slate-400 dark:border-slate-600 dark:bg-slate-800">
                            <img x-show="profilePreview" x-cloak :src="profilePreview" alt="{{ __('Aperçu de la photo') }}" class="h-full w-full object-cover">
                            <x-icon name="user" x-show="!profilePreview" x-cloak class="h-9 w-9 text-slate-400" />
                        </div>
                        <input type="file" name="profile_photo" x-ref="profileInput" accept="image/jpeg,image/png"
                               x-on:change="onProfileChange($event)"
                               class="block w-full text-[1rem] file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-300">
                    </div>
                    <p x-show="profileName" x-cloak class="ec-help mt-1 text-[0.95rem]" x-text="profileName"></p>
                    @error('profile_photo') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- CIN (photos multiples) --}}
                <div>
                    <label class="ec-label text-[1.0625rem] font-semibold">{{ __('CIN — Téléchargement des photos') }}</label>
                    <p class="ec-help text-[0.95rem]">{{ __('JPG ou PNG, 4 Mo maximum par photo (recto / verso).') }}</p>
                    <input type="file" name="cin_files[]" x-ref="cinInput" accept="image/jpeg,image/png" multiple
                           x-on:change="addFiles('cin', $event)"
                           class="block w-full text-[1rem] file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
                    <ul x-show="cinFiles.length" x-cloak class="mt-3 flex flex-wrap gap-2">
                        <template x-for="(f, i) in cinFiles" :key="i">
                            <li class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-[0.95rem] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <span class="max-w-[220px] truncate" x-text="f.name"></span>
                                <button type="button" x-on:click="removeFile('cin', i)" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                            </li>
                        </template>
                    </ul>
                    @error('cin_files.*') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>

                {{-- Autres fichiers --}}
                <div>
                    <label class="ec-label text-[1.0625rem] font-semibold">{{ __('Autres fichiers') }}</label>
                    <p class="ec-help text-[0.95rem]">{{ __('PDF, JPG, PNG, DOC, DOCX — 8 Mo maximum par fichier.') }}</p>
                    <input type="file" name="other_files[]" x-ref="otherInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple
                           x-on:change="addFiles('other', $event)"
                           class="block w-full text-[1rem] file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-[1rem] file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
                    <ul x-show="otherFiles.length" x-cloak class="mt-3 flex flex-wrap gap-2">
                        <template x-for="(f, i) in otherFiles" :key="i">
                            <li class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-[0.95rem] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <span class="max-w-[220px] truncate" x-text="f.name"></span>
                                <button type="button" x-on:click="removeFile('other', i)" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                            </li>
                        </template>
                    </ul>
                    <div class="mt-2">
                        <button type="button" x-on:click="$refs.otherInput.click()" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-brand-300 px-4 py-2 text-[1rem] font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <x-icon name="plus" class="h-5 w-5" /> {{ __('Ajouter un fichier') }}
                        </button>
                    </div>
                    @error('other_files.*') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 3 — CENTRE DE FORMATION
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">3</span>
                    <x-icon name="building" class="h-6 w-6" />
                    {{ __('Centre de formation') }}
                </h2>
            </header>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                @if($isCenterAdmin)
                    {{-- Aucun sélecteur de centre : rattachement automatique, imposé côté serveur --}}
                    <div class="sm:col-span-2">
                        <label class="ec-label text-[1.0625rem] font-semibold">{{ __('Centre de formation') }}</label>
                        <div class="ec-input flex items-center justify-between bg-slate-50 text-[1.0625rem] text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                            <span>{{ $forcedCenter?->translated('name') ?? $forcedCenter?->name }}</span>
                            <span class="ec-badge bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">{{ __('Automatique') }}</span>
                        </div>
                        <p class="ec-help text-[0.95rem]">{{ __('L\'élève sera rattaché exclusivement à ce centre.') }}</p>
                    </div>
                @else
                    <div class="sm:col-span-2">
                        <label for="center_id" class="ec-label text-[1.0625rem] font-semibold">{{ __('Centre de formation') }} <span class="text-red-500">*</span></label>
                        <select id="center_id" name="center_id" class="ec-select text-[1.0625rem] @error('center_id') is-invalid @enderror" required>
                            <option value="">{{ __('— Sélectionner le centre —') }}</option>
                            @foreach($centers as $center)
                                <option value="{{ $center->id }}" @selected(old('center_id') == $center->id)>
                                    {{ $center->translated('name') }} ({{ $center->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('center_id') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 4 — FORMATION
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">4</span>
                    <x-icon name="book" class="h-6 w-6" />
                    {{ __('Formation') }}
                </h2>
            </header>
            <div class="p-6">
                <label for="formation_id" class="ec-label text-[1.0625rem] font-semibold">{{ __('Formation') }} <span class="text-red-500">*</span></label>
                <select id="formation_id" name="formation_id" class="ec-select text-[1.0625rem] @error('formation_id') is-invalid @enderror"
                        x-model="formationId" required>
                    <option value="">{{ __('— Sélectionner une formation —') }}</option>
                    @foreach($formationsData as $formation)
                        <option value="{{ $formation['id'] }}"
                                data-center-id="{{ $formation['center_id'] }}"
                                @selected((string) old('formation_id', (string) ($preselected ?? '')) === (string) $formation['id'])>
                            {{ $formation['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('formation_id') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror

                <p class="ec-help mt-2 text-[0.95rem]">
                    {{ __('Choisissez la formation générale du centre. Les classes associées apparaissent automatiquement (maximum 2 par formation).') }}
                </p>

                {{-- Aperçu en lecture seule des classes de la formation sélectionnée (≤ 2) --}}
                <div x-show="selectedClasses.length > 0" x-cloak class="mt-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <p class="text-[0.95rem] font-semibold text-slate-600 dark:text-slate-300">
                        {{ __('Classes de cette formation') }}
                        <span class="ms-2 rounded-full bg-brand-100 px-2 py-0.5 text-[0.85rem] font-bold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                            <span x-text="selectedClasses.length"></span>/{{ \App\Models\Formation::MAX_CLASSES }}
                        </span>
                    </p>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <template x-for="cls in selectedClasses" :key="cls.id">
                            <li class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-[0.95rem] text-slate-700 shadow-sm dark:bg-slate-900 dark:text-slate-200">
                                <span x-text="cls.name"></span>
                                <span class="text-[0.85rem] text-slate-500 dark:text-slate-400" x-text="'(' + cls.code + ')'"></span>
                            </li>
                        </template>
                    </ul>
                </div>
                <div x-show="formationId && selectedClasses.length === 0" x-cloak class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-[0.95rem] text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                    {{ __('Aucune classe n\'est encore ouverte pour cette formation.') }}
                </div>

                {{-- A. Tarif officiel (lecture seule, source = base de données) --}}
                <div x-show="selectedFormation" x-cloak class="mt-4 flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-[1.0625rem] dark:border-slate-700 dark:bg-slate-800/50">
                    <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Tarif officiel de la formation') }}</span>
                    <span class="font-extrabold text-slate-800 dark:text-slate-100" x-text="money(formationPrice)"></span>
                </div>

                {{-- B/C/D. Prix final accordé + réduction + montant final à payer --}}
                <div x-show="selectedFormation" x-cloak class="mt-4 space-y-4 rounded-2xl border border-brand-200 bg-brand-50/40 p-5 dark:border-brand-500/30 dark:bg-brand-500/5">
                    <div>
                        <label for="final_price" class="ec-label text-[1.0625rem] font-semibold">{{ __('Prix final accordé à l\'étudiant') }} <span class="text-red-500">*</span></label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="number" id="final_price" name="final_price"
                                   class="ec-input text-[1.0625rem] @error('final_price') is-invalid @enderror"
                                   step="0.01" min="0" inputmode="decimal"
                                   :max="formationPrice"
                                   x-model="finalPriceInput"
                                   x-on:input="clampFinalPrice()">
                            <span class="text-[1.0625rem] font-semibold text-slate-500 dark:text-slate-400">{{ $currencySymbol }}</span>
                        </div>
                        <p class="ec-help mt-1 text-[0.95rem]">{{ __('L\'administrateur peut diminuer ce montant. Il ne peut jamais dépasser le tarif officiel ni être négatif.') }}</p>
                        @error('final_price') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                    </div>

                    {{-- C. Réduction calculée automatiquement --}}
                    <div class="flex items-center justify-between text-[1.0625rem]">
                        <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Réduction appliquée') }}</span>
                        <span class="font-bold" :class="discount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'" x-text="money(discount)"></span>
                    </div>

                    {{-- D. Montant final à payer --}}
                    <div class="flex items-center justify-between border-t border-brand-200 pt-4 text-[1.15rem] dark:border-brand-500/30">
                        <span class="font-extrabold text-brand-800 dark:text-brand-200">{{ __('Montant final à payer') }}</span>
                        <span class="font-extrabold text-brand-800 dark:text-brand-200" x-text="money(finalPayable)"></span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 5 — PAIEMENT
        ══════════════════════════════════════════════════════════════ --}}
        <section class="ec-card overflow-hidden lg:col-span-2">
            <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">5</span>
                    <x-icon name="banknotes" class="h-6 w-6" />
                    {{ __('Paiement') }}
                </h2>
            </header>
            <div class="space-y-5 p-6">

                {{-- Choix Avance / Payé --}}
                <div role="radiogroup" aria-label="{{ __('Type de paiement') }}" class="grid gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-2xl border p-5 text-center transition"
                           :class="paymentOption === 'paye' ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200 dark:border-emerald-500/50 dark:bg-emerald-500/10 dark:ring-emerald-500/20' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'">
                        <input type="radio" name="payment_option" value="paye" class="sr-only" x-model="paymentOption" @error('payment_option') is-invalid @enderror>
                        <span class="block text-[1.15rem] font-extrabold text-emerald-700 dark:text-emerald-300">{{ __('Payé') }}</span>
                        <span class="mt-1 block text-[0.95rem] text-slate-500 dark:text-slate-400">{{ __('La totalité du tarif est réglée, reste à payer : 0.') }}</span>
                    </label>
                    <label class="cursor-pointer rounded-2xl border p-5 text-center transition"
                           :class="paymentOption === 'avance' ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200 dark:border-amber-500/50 dark:bg-amber-500/10 dark:ring-amber-500/20' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'">
                        <input type="radio" name="payment_option" value="avance" class="sr-only" x-model="paymentOption">
                        <span class="block text-[1.15rem] font-extrabold text-amber-700 dark:text-amber-300">{{ __('Avance') }}</span>
                        <span class="mt-1 block text-[0.95rem] text-slate-500 dark:text-slate-400">{{ __('Un paiement partiel ; le solde reste à recouvrer.') }}</span>
                    </label>
                </div>
                @error('payment_option') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror

                {{-- Récapitulatif de calcul --}}
                <div x-show="paymentOption" x-cloak class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-5 dark:border-slate-700 dark:bg-slate-800/40">
                    <div class="flex items-center justify-between text-[1.0625rem]">
                        <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Prix final de la formation') }}</span>
                        <span class="font-bold text-slate-800 dark:text-slate-100" x-text="money(finalPayable)"></span>
                    </div>

                    {{-- Champ avance uniquement pour « Avance » --}}
                    <div x-show="paymentOption === 'avance'" x-cloak>
                        <label for="avance_amount" class="ec-label text-[1.0625rem] font-semibold">{{ __('Montant de l\'avance') }} <span class="text-red-500">*</span></label>
                        <input type="number" id="avance_amount" name="avance_amount" step="0.01" min="0"
                               inputmode="decimal" x-model.number="avanceAmount"
                               class="ec-input text-[1.0625rem] @error('avance_amount') is-invalid @enderror"
                               value="{{ old('avance_amount') }}">
                        @error('avance_amount') <p class="ec-error text-[0.95rem]">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-200 pt-4 text-[1.15rem] dark:border-slate-700">
                        <span class="font-extrabold text-slate-700 dark:text-slate-200">{{ __('Reste à payer') }}</span>
                        <span class="font-extrabold" :class="remaining > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'" x-text="money(remaining)"></span>
                    </div>
                    <p class="ec-help text-[0.95rem]">{{ __('Le calcul et l\'enregistrement du solde sont également effectués côté serveur.') }}</p>
                </div>
            </div>
        </section>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center justify-end gap-3 pb-6 lg:col-span-2">
            <a href="{{ route('students.index') }}" class="ec-btn-secondary text-[1.0625rem]">{{ __('Annuler') }}</a>
            <button type="submit" class="ec-btn-primary px-6 text-[1.0625rem]" id="btn-submit-student">
                <x-icon name="check" class="h-5 w-5" />
                {{ __('Enregistrer l\'élève') }}
            </button>
        </div>

        </x-ui.form-grid>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function enrollmentFormApp(config) {
        return {
            formations: config.formations,
            isCenterAdmin: config.isCenterAdmin,
            forcedCenterId: config.forcedCenterId,
            currencySymbol: config.currencySymbol || 'DH',

            centerId: document.getElementById('center_id')?.value || (config.isCenterAdmin ? String(config.forcedCenterId) : ''),
            formationId: @js((string) old('formation_id', (string) ($preselected ?? ''))),
            visibleFormationCount: 0,

            // Prix final accordé : initialisé sur le tarif officiel de la formation
            // pré-sélectionnée (ou valeur conservée après échec de validation).
            finalPriceInput: @js(old('final_price', '')),

            paymentOption: @js(old('payment_option', '')),
            avanceAmount: {{ Js::from((float) old('avance_amount', 0)) }},

            profileName: '',
            profilePreview: '',
            cinFiles: [],
            otherFiles: [],

            get effectiveCenterId() {
                return this.isCenterAdmin ? String(this.forcedCenterId) : String(this.centerId || '');
            },
            get availableFormations() {
                // Nouveau parcours : le select propose directement les six
                // formations générales officielles du centre (aucune étape Domaine).
                const center = this.effectiveCenterId;
                if (center === '') return this.formations;
                return this.formations.filter(f => String(f.center_id) === center);
            },
            get selectedFormation() {
                return this.formations.find(f => String(f.id) === String(this.formationId)) || null;
            },
            get selectedClasses() {
                return this.selectedFormation ? (this.selectedFormation.classes || []) : [];
            },
            get formationPrice() {
                return this.selectedFormation ? Number(this.selectedFormation.price) : 0;
            },
            // Prix final réellement dû : borné à [0, tarif officiel]. Une valeur
            // vide ou invalide retombe sur le tarif officiel (aucune réduction).
            get finalPayable() {
                const raw = this.finalPriceInput;
                if (raw === '' || raw === null || raw === undefined) return this.formationPrice;
                const n = Number(raw);
                if (Number.isNaN(n)) return this.formationPrice;
                return Math.max(0, Math.min(n, this.formationPrice));
            },
            get discount() {
                return Math.max(0, this.formationPrice - this.finalPayable);
            },
            get remaining() {
                const base = this.finalPayable;
                if (this.paymentOption === 'paye') return 0;
                if (this.paymentOption === 'avance') return Math.max(0, base - (Number(this.avanceAmount) || 0));
                return base;
            },

            money(value) {
                const n = Number(value) || 0;
                return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + this.currencySymbol;
            },

            init() {
                const centerSelect = document.getElementById('center_id');
                centerSelect?.addEventListener('change', () => { this.centerId = centerSelect.value; this.applyCascade(); });
                if (this.isCenterAdmin && centerSelect) this.centerId = this.forcedCenterId;
                this.applyCascade();

                // Le prix final suit automatiquement le tarif officiel de la formation
                // choisie : on ne conserve jamais le prix personnalisé de la formation
                // précédente (§4).
                this.$watch('formationId', () => {
                    this.finalPriceInput = this.selectedFormation ? Number(this.selectedFormation.price) : '';
                });

                // Première synchronisation (formation pré-sélectionnée depuis la carte
                // ou après échec de validation) : ne jamais écraser une saisie déjà conservée.
                if (this.selectedFormation && (this.finalPriceInput === '' || this.finalPriceInput === null)) {
                    this.finalPriceInput = Number(this.selectedFormation.price);
                }
            },

            // Bornage immédiat de la saisie : jamais négatif, jamais supérieur au
            // tarif officiel (le backend revalide de toute façon ces bornes).
            clampFinalPrice() {
                if (this.finalPriceInput === '' || this.finalPriceInput === null) return;
                const n = Number(this.finalPriceInput);
                if (Number.isNaN(n)) return;
                this.finalPriceInput = Math.max(0, Math.min(n, this.formationPrice));
            },

            // Filtre par centre uniquement (Super Admin multi-centres). L'Admin
            // Centre reçoit déjà un catalogue filtré serveur.
            applyCascade() {
                const center = this.effectiveCenterId;
                const formationSelect = document.getElementById('formation_id');
                if (!formationSelect) return;
                let visible = 0;
                formationSelect.querySelectorAll('option[data-center-id]').forEach((option) => {
                    const match = center === '' || option.dataset.centerId === center;
                    option.hidden = !match;
                    option.disabled = !match;
                    if (match) visible++;
                });
                this.visibleFormationCount = visible;

                // Réinitialiser la formation si elle ne fait plus partie du
                // catalogue après changement de centre.
                const selected = formationSelect.selectedOptions[0];
                if (selected && selected.dataset.centerId && (selected.hidden || selected.disabled)) {
                    formationSelect.value = '';
                    this.formationId = '';
                }
            },

            onProfileChange(event) {
                const file = event.target.files[0];
                if (!file) { this.profileName = ''; this.profilePreview = ''; return; }
                this.profileName = file.name;
                const reader = new FileReader();
                reader.onload = (e) => { this.profilePreview = e.target.result; };
                reader.readAsDataURL(file);
            },

            addFiles(kind, event) {
                const input = event.target;
                const dt = new DataTransfer();
                this[kind + 'Files'].forEach(f => dt.items.add(f));
                Array.from(input.files).forEach(f => dt.items.add(f));
                this[kind + 'Files'] = Array.from(dt.files);
                input.files = dt.files;
                input.value = input.files.length ? input.value : '';
            },

            removeFile(kind, index) {
                const dt = new DataTransfer();
                this[kind + 'Files'].forEach((f, i) => { if (i !== index) dt.items.add(f); });
                this[kind + 'Files'] = Array.from(dt.files);
                const ref = kind === 'cin' ? this.$refs.cinInput : this.$refs.otherInput;
                if (ref) ref.files = dt.files;
            },
        };
    }
</script>
@endpush
