@props([
    'cancelUrl' => null,
    'cancelLabel' => null,
    'submitLabel' => null,
])

{{--
    Barre d'actions (Annuler / Enregistrer) en bas de formulaire.
    occupe toute la largeur dans la grille.
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-end gap-3 pt-2 lg:col-span-2']) }}>
    @if ($cancelUrl)
        <a href="{{ $cancelUrl }}" class="ec-btn-secondary text-base">
            {{ $cancelLabel ?? __('Annuler') }}
        </a>
    @endif
    <button type="submit" class="ec-btn-primary px-6 text-base" {{ $attributes->only('id') }}>
        <x-icon name="check" class="h-5 w-5" />
        {{ $submitLabel ?? __('Enregistrer') }}
    </button>
</div>
