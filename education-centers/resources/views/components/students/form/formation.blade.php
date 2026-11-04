@props(['f' => []])

@php
    $mode = $f['mode'] ?? 'create';
    $student = $f['student'] ?? null;
    $enrollment = $f['enrollment'] ?? null;
    $formationsData = $f['formations'] ?? [];
    $currencySymbol = $f['currencySymbol'] ?? config('currency.symbol', 'DH');

    // Création : formation pré-sélectionnée depuis la carte de la formation
    // (?formation_id=). Modification : formation de l'inscription enregistrée.
    $preselected = $f['preselectedFormationId'] ?? null;
    $selectedFormationId = (string) old('formation_id', (string) ($preselected ?? ''));
@endphp

<x-students.form.card num="4" icon="book" :title="__('Formation')">
    <div class="p-6">
        <label for="formation_id" class="ec-label text-base font-semibold">{{ __('Formation') }} <span class="text-red-500">*</span></label>
        <select id="formation_id" name="formation_id" class="ec-select text-base @error('formation_id') is-invalid @enderror"
                x-model="formationId" required>
            <option value="">{{ __('— Sélectionner une formation —') }}</option>
            @foreach($formationsData as $formation)
                <option value="{{ $formation['id'] }}"
                        data-center-id="{{ $formation['center_id'] }}"
                        @selected($selectedFormationId === (string) $formation['id'])>
                    {{ $formation['name'] }}
                </option>
            @endforeach
        </select>
        @error('formation_id') <p class="ec-error text-sm">{{ $message }}</p> @enderror

        <p class="ec-help mt-2 text-sm">
            {{ __('Choisissez la formation générale du centre. Les classes associées apparaissent automatiquement (maximum 2 par formation).') }}
        </p>

        {{-- Classe déjà affectée à l'inscription (modification, tant que la formation ne change pas) --}}
        @if($mode === 'edit' && $enrollment?->classRoom)
            <div x-show="formationId === '{{ \App\Support\AcademicPerimeter::generalFormationId($enrollment->formation_id) }}'" class="mt-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                {{ __('Classe actuelle de l\'inscription') }} :
                <strong>{{ $enrollment->classRoom->perimeter_label }}</strong>
                <span class="text-slate-500 dark:text-slate-400">({{ $enrollment->classRoom->code }})</span>
            </div>
        @endif

        {{-- Aperçu en lecture seule des classes de la formation sélectionnée (≤ 2) --}}
        <div x-show="selectedClasses.length > 0" x-cloak class="mt-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/50">
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ __('Classes de cette formation') }}
                <span class="ms-2 rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                    <span x-text="selectedClasses.length"></span>/{{ \App\Models\Formation::MAX_CLASSES }}
                </span>
            </p>
            <ul class="mt-2 flex flex-wrap gap-2">
                <template x-for="cls in selectedClasses" :key="cls.id">
                    <li class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-sm text-slate-700 shadow-sm dark:bg-slate-900 dark:text-slate-200">
                        <span x-text="cls.name"></span>
                        <span class="text-xs text-slate-500 dark:text-slate-400" x-text="'(' + cls.code + ')'"></span>
                    </li>
                </template>
            </ul>
        </div>
        <div x-show="formationId && selectedClasses.length === 0" x-cloak class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
            {{ __('Aucune classe n\'est encore ouverte pour cette formation.') }}
        </div>

        {{-- A. Tarif officiel (lecture seule, source = base de données) --}}
        <div x-show="selectedFormation" x-cloak class="mt-4 flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-base dark:border-slate-700 dark:bg-slate-800/50">
            <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Tarif officiel de la formation') }}</span>
            <span class="font-extrabold text-slate-800 dark:text-slate-100" x-text="money(formationPrice)"></span>
        </div>

        {{-- B/C/D. Prix final accordé + réduction + montant final à payer --}}
        <div x-show="selectedFormation" x-cloak class="mt-4 space-y-4 rounded-2xl border border-brand-200 bg-brand-50/40 p-5 dark:border-brand-500/30 dark:bg-brand-500/5">
            <div>
                <label for="final_price" class="ec-label text-base font-semibold">{{ __('Prix final accordé à l\'étudiant') }} <span class="text-red-500">*</span></label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="number" id="final_price" name="final_price"
                           class="ec-input text-base @error('final_price') is-invalid @enderror"
                           step="0.01" min="0" inputmode="decimal"
                           :max="formationPrice"
                           x-model="finalPriceInput"
                           x-on:input="clampFinalPrice()">
                    <span class="text-base font-semibold text-slate-500 dark:text-slate-400">{{ $currencySymbol }}</span>
                </div>
                <p class="ec-help mt-1 text-sm">{{ __('L\'administrateur peut diminuer ce montant. Il ne peut jamais dépasser le tarif officiel ni être négatif.') }}</p>
                @error('final_price') <p class="ec-error text-sm">{{ $message }}</p> @enderror
            </div>

            {{-- C. Réduction calculée automatiquement --}}
            <div class="flex items-center justify-between text-base">
                <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Réduction appliquée') }}</span>
                <span class="font-bold" :class="discount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'" x-text="money(discount)"></span>
            </div>

            {{-- D. Montant final à payer --}}
            <div class="flex items-center justify-between border-t border-brand-200 pt-4 text-lg dark:border-brand-500/30">
                <span class="font-extrabold text-brand-800 dark:text-brand-200">{{ __('Montant final à payer') }}</span>
                <span class="font-extrabold text-brand-800 dark:text-brand-200" x-text="money(finalPayable)"></span>
            </div>
        </div>
    </div>
</x-students.form.card>
