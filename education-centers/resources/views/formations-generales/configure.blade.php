@extends('layouts.portal')

@section('title', __('Configurer la formation officielle') . ' — ' . config('app.name'))
@section('description', __('Ajustez le prix, la durée, la description, le statut et le professeur responsable.'))

@php
    $authUser = auth()->user();
    $isSuperAdmin = $authUser->isSuperAdmin();
@endphp

@section('content')

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white shadow-lg shadow-violet-600/30">
                <x-icon name="pencil" class="h-6 w-6 text-white" stroke-width="2" />
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Configuration de la formation générale officielle') }}</p>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    {{ __($domain->name ?? $formation->name) }}
                    <span class="ms-2 text-base font-normal text-slate-500 dark:text-slate-400">· {{ $formation->center?->name }}</span>
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Aucune nouvelle formation ne sera créée : cette page modifie uniquement les paramètres de l’emplacement officiel déjà en place.') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('formations-generales.picker', $isSuperAdmin ? ['center_id' => $formation->center_id] : []) }}"
               id="configure-back-picker"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-brand-400 transition">
                <x-icon name="arrow-left" class="h-4 w-4 rtl:rotate-180" stroke-width="2" />
                {{ __('Choisir une autre formation') }}
            </a>
            <a href="{{ route('formations-generales.show', $formation) }}"
               id="configure-back-show"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 hover:text-brand-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-brand-400 transition">
                <x-icon name="eye" class="h-4 w-4" stroke-width="2" />
                {{ __('Voir l\'espace de la formation') }}
            </a>
        </div>
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
          action="{{ route('formations-generales.update', $formation) }}"
          id="form-configure-formation">
        @csrf
        @method('PATCH')

        <x-ui.form-grid>

        {{-- Bloc identité (lecture seule) --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Identité de l\'emplacement officiel') }}</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Libellé') }}</dt>
                    <dd class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ __($domain->name ?? $formation->name) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Code') }}</dt>
                    <dd class="mt-1 font-mono text-sm text-slate-700 dark:text-slate-300">{{ $formation->code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Centre') }}</dt>
                    <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $formation->center?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Code officiel') }}</dt>
                    <dd class="mt-1 font-mono text-sm text-slate-700 dark:text-slate-300">{{ $formation->official_code }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Ces champs font partie du catalogue de référence et ne sont pas modifiables.') }}
            </p>
        </section>

        {{-- Paramètres configurables --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Paramètres de la formation') }}</h2>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                {{-- Prix annuel officiel --}}
                <div>
                    <label for="annual_price" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('Prix officiel (annuel)') }}
                    </label>
                    <input type="number" step="0.01" min="0" id="annual_price" name="annual_price"
                           value="{{ old('annual_price', $formation->annual_price) }}"
                           class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
                           placeholder="0.00">
                    @error('annual_price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Laisser vide pour réutiliser le tarif par défaut de la formation générale.') }}</p>
                </div>

                {{-- Devise --}}
                <div>
                    <label for="currency" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('Devise') }}
                    </label>
                    <input type="text" id="currency" name="currency" maxlength="8"
                           value="{{ old('currency', $formation->currency ?? default_currency()) }}"
                           class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white">
                    @error('currency')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Durée en semaines --}}
                <div>
                    <label for="duration_weeks" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('Durée (semaines)') }}
                    </label>
                    <input type="number" min="0" max="520" id="duration_weeks" name="duration_weeks"
                           value="{{ old('duration_weeks', $formation->duration_weeks) }}"
                           class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white">
                    @error('duration_weeks')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Durée en heures --}}
                <div>
                    <label for="duration_hours" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('Durée (heures)') }}
                    </label>
                    <input type="number" min="0" max="10000" id="duration_hours" name="duration_hours"
                           value="{{ old('duration_hours', $formation->duration_hours) }}"
                           class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white">
                    @error('duration_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Statut --}}
                <div>
                    <label for="status" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('Statut') }} <span class="text-red-500">*</span>
                    </label>
                    <select id="status" name="status" required
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white">
                        <option value="active"   @selected(old('status', $formation->status) === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(old('status', $formation->status) === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                    @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Professeur responsable --}}
                <div>
                    <label for="teacher_id" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('Professeur responsable') }}
                    </label>
                    <select id="teacher_id" name="teacher_id"
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white">
                        <option value="">— {{ __('Non assigné') }} —</option>
                        @foreach ($centerTeachers as $t)
                            <option value="{{ $t->id }}" @selected((int) old('teacher_id', $formation->teacher_id) === (int) $t->id)>
                                {{ $t->full_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('teacher_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Un seul responsable par formation officielle. Les professeurs affichés sont ceux du centre de cette formation.') }}
                    </p>
                </div>

                {{-- Description --}}
                <div class="sm:col-span-2">
                    <label for="description" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('Description') }}
                    </label>
                    <textarea id="description" name="description" rows="4" maxlength="2000"
                              class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white">{{ old('description', $formation->description) }}</textarea>
                    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center justify-end gap-3 lg:col-span-2">
            <a href="{{ route('formations-generales.show', $formation) }}"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 transition">
                {{ __('Annuler') }}
            </a>
            <button type="submit"
                    id="btn-save-configuration"
                    class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:ring-offset-2 dark:bg-violet-500 dark:hover:bg-violet-600">
                <x-icon name="check" class="h-4 w-4" stroke-width="2" />
                {{ __('Enregistrer la configuration') }}
            </button>
        </div>
        </x-ui.form-grid>
    </form>

@endsection
