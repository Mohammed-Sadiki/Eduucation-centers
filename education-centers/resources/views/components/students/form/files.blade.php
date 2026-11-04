@props(['f' => []])

@php
    $student = $f['student'] ?? null;
    $mode = $f['mode'] ?? 'create';
    $isEdit = $mode === 'edit' && $student;

    // Photo déjà enregistrée : affichée uniquement si le fichier existe vraiment
    // sur son disque (jamais une image cassée).
    $photoUrl = $isEdit ? $student->profilePhotoUrl() : null;

    // Documents déjà rattachés au dossier (aucun n'est supprimé par la modification).
    $existingDocs = $isEdit ? $student->documents : collect();
    $cinDocs = $existingDocs->filter(fn ($d) => $d->type === 'identity');
    $otherDocs = $existingDocs->reject(fn ($d) => $d->type === 'identity');
@endphp

<x-students.form.card num="2" icon="document" :title="__('Pièces et fichiers')">
    <div class="grid gap-6 p-6">

        {{-- Photo de profil + aperçu --}}
        <div>
            <label class="ec-label text-base font-semibold">{{ __('Photo de profil') }}</label>
            <p class="ec-help text-sm">{{ __('JPG ou PNG, 5 Mo maximum. Un aperçu s\'affiche avant l\'enregistrement.') }}</p>
            @if($isEdit)
                <p class="ec-help text-sm">{{ __('La photo actuelle est conservée si vous ne choisissez pas de nouveau fichier.') }}</p>
            @endif
            <div class="mt-2 flex items-center gap-4">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-slate-300 bg-slate-50 text-slate-400 dark:border-slate-600 dark:bg-slate-800">
                    <img x-show="profilePreview" x-cloak :src="profilePreview" alt="{{ __('Aperçu de la photo') }}" class="h-full w-full object-cover">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" x-show="!profilePreview" alt="{{ $student->full_name }}" class="h-full w-full object-cover">
                    @else
                        <x-icon name="user" x-show="!profilePreview" x-cloak class="h-9 w-9 text-slate-400" />
                    @endif
                </div>
                <input type="file" name="profile_photo" x-ref="profileInput" accept="image/jpeg,image/png"
                       x-on:change="onProfileChange($event)"
                       class="block w-full text-base file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-base file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-300">
            </div>
            <p x-show="profileName" x-cloak class="ec-help mt-1 text-sm" x-text="profileName"></p>
            @error('profile_photo') <p class="ec-error text-sm">{{ $message }}</p> @enderror
        </div>

        {{-- CIN (photos multiples) --}}
        <div>
            <label class="ec-label text-base font-semibold">{{ __('CIN — Téléchargement des photos') }}</label>
            <p class="ec-help text-sm">{{ __('JPG ou PNG, 4 Mo maximum par photo (recto / verso).') }}</p>
            @if($isEdit)
                <p class="ec-help text-sm">{{ __('Les photos déjà enregistrées restent au dossier : tout nouveau téléversement les complète sans rien effacer.') }}</p>
            @endif
            <input type="file" name="cin_files[]" x-ref="cinInput" accept="image/jpeg,image/png" multiple
                   x-on:change="addFiles('cin', $event)"
                   class="block w-full text-base file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-base file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
            <ul x-show="cinFiles.length" x-cloak class="mt-3 flex flex-wrap gap-2">
                <template x-for="(f, i) in cinFiles" :key="i">
                    <li class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <span class="max-w-[220px] truncate" x-text="f.name"></span>
                        <button type="button" x-on:click="removeFile('cin', i)" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                    </li>
                </template>
            </ul>
            @error('cin_files.*') <p class="ec-error text-sm">{{ $message }}</p> @enderror

            @if($isEdit && $cinDocs->isNotEmpty())
                <p class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('Déjà enregistré') }} :</p>
                <ul class="mt-1 flex flex-wrap gap-2">
                    @foreach($cinDocs as $doc)
                        <li class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <x-icon name="document" class="h-4 w-4 shrink-0" />
                            <a href="{{ route('documents.download', $doc) }}" class="max-w-[220px] truncate font-semibold hover:underline" title="{{ $doc->original_name ?? $doc->title }}">
                                {{ $doc->title }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Autres fichiers --}}
        <div>
            <label class="ec-label text-base font-semibold">{{ __('Autres fichiers') }}</label>
            <p class="ec-help text-sm">{{ __('PDF, JPG, PNG, DOC, DOCX — 8 Mo maximum par fichier.') }}</p>
            <input type="file" name="other_files[]" x-ref="otherInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple
                   x-on:change="addFiles('other', $event)"
                   class="block w-full text-base file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-base file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200">
            <ul x-show="otherFiles.length" x-cloak class="mt-3 flex flex-wrap gap-2">
                <template x-for="(f, i) in otherFiles" :key="i">
                    <li class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <span class="max-w-[220px] truncate" x-text="f.name"></span>
                        <button type="button" x-on:click="removeFile('other', i)" class="text-red-500 hover:text-red-700" :aria-label="{{ __('Retirer ce fichier') }}">&times;</button>
                    </li>
                </template>
            </ul>
            <div class="mt-2">
                <button type="button" x-on:click="$refs.otherInput.click()" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-brand-300 px-4 py-2 text-base font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-300 dark:hover:bg-brand-500/10">
                    <x-icon name="plus" class="h-5 w-5" /> {{ __('Ajouter un fichier') }}
                </button>
            </div>
            @error('other_files.*') <p class="ec-error text-sm">{{ $message }}</p> @enderror

            @if($isEdit && $otherDocs->isNotEmpty())
                <p class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('Déjà enregistré') }} :</p>
                <ul class="mt-1 flex flex-wrap gap-2">
                    @foreach($otherDocs as $doc)
                        <li class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <x-icon name="document" class="h-4 w-4 shrink-0" />
                            <a href="{{ route('documents.download', $doc) }}" class="max-w-[220px] truncate font-semibold hover:underline" title="{{ $doc->original_name ?? $doc->title }}">
                                {{ $doc->title }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-students.form.card>
