@extends('layouts.portal')

@php
    // Une seule vue sert à la création ET à la modification d'une formation
    // générale personnalisée : $formation est défini uniquement en édition.
    $editing = isset($formation) && $formation instanceof \App\Models\Formation;
    $pageTitle = $editing ? __('Modifier la formation personnalisée') : __('Nouvelle formation générale');
    $user = auth()->user();
    $isSuperAdmin = $user->isSuperAdmin();
    $currentCenterId = old('center_id', $editing ? ($formation->center_id ?? 'all') : ($center->id ?? ''));
@endphp

@section('title', $pageTitle . ' — ' . config('app.name'))
@section('description', __('Ajoutez une formation générale directement rattachée au centre.'))

@section('content')

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5 [direction:ltr]" dir="ltr">
            <x-ui.back-button :fallback="route('formations-generales.index', $center ? ['center_id' => $center->id] : [])" />
            <div class="flex items-center gap-3 {{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-600/30">
                    <x-icon name="{{ $editing ? 'pencil' : 'plus' }}" class="h-6 w-6 text-white" stroke-width="2" />
                </span>
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                        {{ $pageTitle }}
                        @if($center)
                            <span class="ms-2 text-base font-normal text-slate-500 dark:text-slate-400">· {{ $center->name }}</span>
                        @elseif($editing && $formation->center_id === null)
                            <span class="ms-2 text-base font-normal text-slate-500 dark:text-slate-400">· {{ __('Tous les centres') }}</span>
                        @endif
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('La formation suit les mêmes règles que les six officielles (maximum 2 classes, 1 professeur responsable).') }}
                    </p>
                </div>
            </div>
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
          action="{{ $editing
              ? route('formations-generales.update-custom', $formation)
              : route('formations-generales.store') }}"
          id="form-custom-formation"
          enctype="multipart/form-data">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-ui.form-grid>

            {{-- ═══════════════════════════ CARTE 1 — Identité ═══════════════════════════ --}}
            <x-ui.form-section :number="1" :title="__('Identité de la formation')">
                <div class="grid gap-5 p-6 sm:grid-cols-2">

                    {{-- Sélection du centre concerné --}}
                    <div class="sm:col-span-2">
                        <label for="center_id" class="ec-label text-base font-semibold">
                            {{ __('Centre concerné') }} <span class="text-red-500">*</span>
                        </label>
                        @if ($isSuperAdmin)
                            <select id="center_id" name="center_id" required
                                    class="ec-select text-base @error('center_id') is-invalid @enderror">
                                <option value="" disabled @selected($currentCenterId === '' || $currentCenterId === null)>
                                    {{ __('— Choisir un centre ou tous les centres —') }}
                                </option>
                                <option value="all" @selected($currentCenterId === 'all' || (string)$currentCenterId === '0')>
                                    {{ __('Tous les centres') }}
                                </option>
                                @foreach ($centers as $c)
                                    <option value="{{ $c->id }}" @selected((string)$currentCenterId === (string)$c->id)>
                                        {{ $c->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('center_id') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                            <p class="ec-help mt-1 text-sm">{{ __('Choisissez un centre précis ou « Tous les centres » pour rendre la formation visible partout.') }}</p>
                        @else
                            {{-- Admin Centre / Secrétaire : centre forcé automatiquement côté serveur (pas de champ d'entrée) --}}
                            <div class="ec-input flex items-center justify-between bg-slate-50 text-base text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                <span>{{ $user->center?->name ?? __('Votre centre') }}</span>
                                <span class="ec-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('Automatique') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <label for="name" class="ec-label text-base font-semibold">{{ __('Nom de la formation') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $formation->name ?? '') }}"
                               required
                               class="ec-input text-base @error('name') is-invalid @enderror"
                               placeholder="{{ __('Ex : Assistance médicale') }}">
                        @error('name') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                        <p class="ec-help mt-1 text-sm">{{ __('Le nom doit être unique dans le périmètre concerné.') }}</p>
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
                                <option value="{{ $t->id }}"
                                        data-center-id="{{ $t->center_id }}"
                                        @selected((int) old('teacher_id', $formation->teacher_id ?? 0) === (int) $t->id)>
                                    {{ $t->full_name_with_formation }}
                                </option>
                            @endforeach
                        </select>
                        @error('teacher_id') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                        <p class="ec-help mt-1 text-sm">{{ __('Facultatif à la création. Un seul responsable.') }}</p>
                    </div>

                    <div>
                        <label for="image" class="ec-label text-base font-semibold">{{ __('Image de la carte (facultative)') }}</label>
                        <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"
                               onchange="previewFormationImage(event)"
                               class="ec-input w-full text-base @error('image') is-invalid @enderror file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-base file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
                        @error('image') <p class="ec-error text-sm">{{ $message }}</p> @enderror
                        <p class="ec-help mt-1 text-sm">{{ __('JPG, PNG ou WebP — 2 Mo maximum. Sans image, une icône générique habille la carte.') }}</p>

                        {{-- Aperçu en direct de l'image sélectionnée --}}
                        <div id="image-live-preview-container" class="mt-3 hidden items-center gap-3 rounded-xl border border-brand-200 bg-brand-50/50 p-2.5 dark:border-brand-800/40 dark:bg-brand-950/20">
                            <img id="image-live-preview" src="#" alt="{{ __('Nouvel aperçu') }}"
                                 class="h-16 w-24 rounded-lg object-cover border border-brand-300 shadow-xs">
                            <div class="text-xs text-brand-800 dark:text-brand-200 font-medium">
                                {{ __('Aperçu de la nouvelle image sélectionnée.') }}
                            </div>
                        </div>

                        @if (!empty($formation?->image_path))
                            <div id="image-current-container" class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <img src="{{ asset('storage/' . $formation->image_path) }}" alt="{{ __('Image actuelle') }}"
                                     class="h-14 w-24 rounded-lg object-cover border border-slate-200 dark:border-slate-700">
                                <span class="text-xs">{{ __('Image actuelle — envoyez un nouveau fichier pour la remplacer.') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </x-ui.form-section>

            {{-- Barre d'actions --}}
            <div class="flex flex-wrap items-center justify-end gap-3 pt-2 lg:col-span-2">
                <a href="{{ route('formations-generales.index', $center ? ['center_id' => $center->id] : []) }}"
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

    <script>
        function previewFormationImage(event) {
            const input = event.target;
            const container = document.getElementById('image-live-preview-container');
            const preview = document.getElementById('image-live-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.classList.remove('hidden');
                    container.classList.add('flex');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                container.classList.add('hidden');
                container.classList.remove('flex');
            }
        }

        // Filtrage dynamique des professeurs selon le centre sélectionné
        document.addEventListener('DOMContentLoaded', function () {
            const centerSelect = document.getElementById('center_id');
            const teacherSelect = document.getElementById('teacher_id');

            if (!centerSelect || !teacherSelect) return;

            const teacherOptions = Array.from(teacherSelect.querySelectorAll('option')).slice(1); // Exclut « Non assigné »

            function filterTeachersByCenter() {
                const selectedCenter = centerSelect.value;
                const currentSelectedTeacherId = teacherSelect.value;
                let isCurrentTeacherStillVisible = false;

                teacherOptions.forEach(option => {
                    const teacherCenterId = option.getAttribute('data-center-id');

                    // Si « Tous les centres » ou vide : on montre tous les professeurs
                    // Si un centre spécifique est sélectionné : on montre uniquement les profs de ce centre
                    if (!selectedCenter || selectedCenter === 'all' || selectedCenter === '0' || teacherCenterId === selectedCenter) {
                        option.hidden = false;
                        option.disabled = false;
                        if (option.value === currentSelectedTeacherId) {
                            isCurrentTeacherStillVisible = true;
                        }
                    } else {
                        option.hidden = true;
                        option.disabled = true;
                    }
                });

                // Si le professeur précédemment sélectionné n'appartient pas au nouveau centre, on réinitialise à « Non assigné »
                if (currentSelectedTeacherId && !isCurrentTeacherStillVisible) {
                    teacherSelect.value = '';
                }
            }

            centerSelect.addEventListener('change', filterTeachersByCenter);
            filterTeachersByCenter();
        });
    </script>

@endsection
