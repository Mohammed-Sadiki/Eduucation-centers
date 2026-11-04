@extends('layouts.portal')

@php
    // Une seule vue sert à la création ET à la modification d'une formation
    // générale personnalisée : $formation est défini uniquement en édition.
    $editing = isset($formation) && $formation instanceof \App\Models\Formation;
    $pageTitle = $editing ? __('Modifier la formation personnalisée') : __('Nouvelle formation générale');
@endphp

@section('title', $pageTitle . ' — ' . config('app.name'))
@section('description', __('Ajoutez une formation générale directement rattachée au centre, sans domaine ni sous-formation.'))

@section('content')

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-600/30">
                <x-icon name="{{ $editing ? 'pencil' : 'plus' }}" class="h-6 w-6 text-white" stroke-width="2" />
            </span>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    {{ $pageTitle }}
                    <span class="ms-2 text-base font-normal text-slate-500 dark:text-slate-400">· {{ $center->name }}</span>
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('La formation est liée au centre ; elle suit les mêmes règles que les six officielles (maximum 2 classes, 1 professeur responsable).') }}
                </p>
            </div>
        </div>

        <a href="{{ route('formations-generales.picker', ['center_id' => $center->id]) }}"
           id="custom-back-picker"
           class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-brand-400 transition">
            <x-icon name="arrow-left" class="h-4 w-4 rtl:rotate-180" stroke-width="2" />
            {{ __('Retour au catalogue') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $editing
              ? route('formations-generales.update-custom', $formation)
              : route('formations-generales.store') }}"
          id="form-custom-formation"
          enctype="multipart/form-data">
        @csrf
        @if ($editing)
            @method('PUT')
        @else
            {{-- Super Admin : le centre cible est transmis ; Admin Centre forcé serveur --}}
            @if (auth()->user()->isSuperAdmin())
                <input type="hidden" name="center_id" value="{{ $center->id }}">
            @endif
        @endif

        <x-ui.form-grid>

            {{-- ═══════════════════════════ CARTE 1 — Identité ═══════════════════════════ --}}
            <x-ui.form-section :number="1" :title="__('Identité de la formation')">
                <div class="grid gap-5 p-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="ec-label text-base font-semibold">{{ __('Nom de la formation') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $formation->name ?? '') }}"
                               required
                               class="ec-input text-base @error('name') is-invalid @enderror"
                               placeholder="{{ __('Ex : Assistance médicale') }}">
                        @error('name') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                        <p class="ec-help mt-1 text-sm">{{ __('Le nom doit être unique dans le centre. Les six formations officielles restent en place.') }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="description" class="ec-label text-base font-semibold">{{ __('Description') }}</label>
                        <textarea id="description" name="description" rows="4" maxlength="2000"
                                  class="ec-input text-base @error('description') is-invalid @enderror"
                                  placeholder="{{ __('Décrivez brièvement le programme…') }}">{{ old('description', $formation->description ?? '') }}</textarea>
                        @error('description') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-ui.form-section>

            {{-- ═══════════════════════════ CARTE 2 — Tarif & durée ═══════════════════════════ --}}
            <x-ui.form-section :number="2" :title="__('Tarif et durée')">
                <div class="grid gap-5 p-6 sm:grid-cols-2">
                    <div>
                        <label for="annual_price" class="ec-label text-base font-semibold">{{ __('Prix officiel (annuel)') }} <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" id="annual_price" name="annual_price"
                                   value="{{ old('annual_price', $formation->annual_price ?? '') }}"
                                   required
                                   class="ec-input text-base pe-14 @error('annual_price') is-invalid @enderror"
                                   placeholder="0.00">
                            <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-sm font-semibold text-slate-400">DH</span>
                        </div>
                        @error('annual_price') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="duration_weeks" class="ec-label text-base font-semibold">{{ __('Durée (semaines)') }}</label>
                        <input type="number" min="1" max="520" id="duration_weeks" name="duration_weeks"
                               value="{{ old('duration_weeks', $formation->duration_weeks ?? '') }}"
                               class="ec-input text-base @error('duration_weeks') is-invalid @enderror"
                               placeholder="{{ __('Ex : 24') }}">
                        @error('duration_weeks') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="duration_hours" class="ec-label text-base font-semibold">{{ __('Durée (heures)') }}</label>
                        <input type="number" min="1" max="10000" id="duration_hours" name="duration_hours"
                               value="{{ old('duration_hours', $formation->duration_hours ?? '') }}"
                               class="ec-input text-base @error('duration_hours') is-invalid @enderror"
                               placeholder="{{ __('Ex : 360') }}">
                        @error('duration_hours') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="status" class="ec-label text-base font-semibold">{{ __('Statut') }} <span class="text-red-500">*</span></label>
                        <select id="status" name="status" required
                                class="ec-select text-base @error('status') is-invalid @enderror">
                            <option value="active" @selected(old('status', $formation->status ?? 'active') === 'active')>{{ __('Active') }}</option>
                            <option value="inactive" @selected(old('status', $formation->status ?? 'active') === 'inactive')>{{ __('Inactive') }}</option>
                        </select>
                        @error('status') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-ui.form-section>

            {{-- ═══════════════════════════ CARTE 3 — Professeur & visuel ═══════════════════════════ --}}
            <x-ui.form-section :number="3" :title="__('Professeur responsable et image')" span="full">
                <div class="grid gap-5 p-6 sm:grid-cols-2">
                    <div>
                        <label for="teacher_id" class="ec-label text-base font-semibold">{{ __('Professeur responsable') }}</label>
                        <select id="teacher_id" name="teacher_id"
                                class="ec-select text-base @error('teacher_id') is-invalid @enderror">
                            <option value="">— {{ __('Non assigné') }} —</option>
                            @foreach ($centerTeachers as $t)
                                <option value="{{ $t->id }}" @selected((int) old('teacher_id', $formation->teacher_id ?? 0) === (int) $t->id)>
                                    {{ $t->full_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('teacher_id') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                        <p class="ec-help mt-1 text-sm">{{ __('Facultatif à la création. Un seul responsable, obligatoirement du centre.') }}</p>
                    </div>

                    <div>
                        <label for="image" class="ec-label text-base font-semibold">{{ __('Image de la carte (facultative)') }}</label>
                        <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"
                               class="ec-input w-full text-base @error('image') is-invalid @enderror file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-base file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
                        @error('image') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                        <p class="ec-help mt-1 text-sm">{{ __('JPG, PNG ou WebP — 2 Mo maximum. Sans image, une icône générique habille la carte.') }}</p>

                        @if (!empty($formation?->image_path))
                            <div class="mt-2 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <img src="{{ asset('storage/' . $formation->image_path) }}" alt="{{ __('Image actuelle') }}"
                                     class="h-12 w-20 rounded-lg object-cover border border-slate-200 dark:border-slate-700">
                                <span>{{ __('Image actuelle — envoyez un fichier pour la remplacer.') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </x-ui.form-section>

            {{-- Barre d'actions --}}
            <div class="flex flex-wrap items-center justify-end gap-3 pt-2 lg:col-span-2">
                <a href="{{ route('formations-generales.picker', ['center_id' => $center->id]) }}"
                   class="ec-btn-secondary text-base">{{ __('Annuler') }}</a>
                <button type="submit" id="btn-save-custom-formation" class="ec-btn-primary px-6 text-base">
                    <x-icon name="check" class="h-5 w-5" />
                    {{ $editing ? __('Enregistrer les modifications') : __('Créer la formation') }}
                </button>
            </div>
        </x-ui.form-grid>
    </form>

    @if (!empty($formation))
        {{-- Suppression (formation personnalisée vide uniquement : ni classe, ni inscription) --}}
        <div class="mt-8 rounded-2xl border border-rose-200/70 bg-rose-50/50 p-5 dark:border-rose-500/30 dark:bg-rose-500/10">
            <h2 class="text-sm font-bold uppercase tracking-wide text-rose-700 dark:text-rose-300">{{ __('Supprimer cette formation') }}</h2>
            <p class="mt-1 text-xs text-rose-700/80 dark:text-rose-300/80">
                {{ __('Possible uniquement si la formation ne possède ni classe ni inscription active.') }}
            </p>
            <form method="POST" action="{{ route('formations-generales.destroy-custom', $formation) }}" class="mt-3"
                  onsubmit="return confirm(@js(__('Supprimer définitivement cette formation personnalisée ?')));">
                @csrf
                @method('DELETE')
                <button type="submit" id="btn-delete-custom-formation"
                        class="inline-flex items-center gap-2 rounded-xl border border-rose-300 bg-white px-4 py-2 text-xs font-semibold text-rose-700 shadow-xs hover:bg-rose-100 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20 transition">
                    <x-icon name="trash" class="h-4 w-4" stroke-width="2" />
                    {{ __('Supprimer la formation') }}
                </button>
            </form>
        </div>
    @endif

@endsection
