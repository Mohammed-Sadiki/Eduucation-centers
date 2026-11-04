@props(['f' => []])

@php
    $mode = $f['mode'] ?? 'create';
    $student = $f['student'] ?? null;
    $isCenterAdmin = $f['isCenterAdmin'] ?? false;
    $forcedCenter = $f['forcedCenter'] ?? null;
    $centers = $f['centers'] ?? collect();

    // Modification : le centre du dossier reste celui enregistré (aucune
    // réaffectation possible, même pour un Super Admin) — il est donc présenté
    // comme en création pour un Admin Centre : panneau en lecture seule.
    $center = $mode === 'edit' ? ($student?->center ?? $forcedCenter) : $forcedCenter;
    $readOnly = $mode === 'edit' || $isCenterAdmin;
@endphp

<x-students.form.card num="3" icon="building" :title="__('Centre de formation')">
    <div class="grid gap-5 p-6 sm:grid-cols-2">
        @if($readOnly)
            {{-- Aucun sélecteur de centre : rattachement automatique, imposé côté serveur --}}
            <div class="sm:col-span-2">
                <label class="ec-label text-base font-semibold">{{ __('Centre de formation') }}</label>
                <div class="ec-input flex items-center justify-between bg-slate-50 text-base text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                    <span>{{ $center?->translated('name') ?? $center?->name }}</span>
                    <span class="ec-badge bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                        {{ $mode === 'edit' ? __('Non modifiable') : __('Automatique') }}
                    </span>
                </div>
                <p class="ec-help text-sm">
                    {{ $mode === 'edit'
                        ? __('Le centre du dossier ne change pas : inscriptions, paiements et documents lui restent rattachés.')
                        : __('L\'élève sera rattaché exclusivement à ce centre.') }}
                </p>
            </div>
        @else
            <div class="sm:col-span-2">
                <label for="center_id" class="ec-label text-base font-semibold">{{ __('Centre de formation') }} <span class="text-red-500">*</span></label>
                <select id="center_id" name="center_id" class="ec-select text-base @error('center_id') is-invalid @enderror" required>
                    <option value="">{{ __('— Sélectionner le centre —') }}</option>
                    @foreach($centers as $c)
                        <option value="{{ $c->id }}" @selected(old('center_id') == $c->id)>
                            {{ $c->translated('name') }} ({{ $c->code }})
                        </option>
                    @endforeach
                </select>
                @error('center_id') <p class="ec-error text-sm">{{ $message }}</p> @enderror
            </div>
        @endif
    </div>
</x-students.form.card>
