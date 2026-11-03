@props([
    'title' => null,
    'icon' => null,
    'number' => null,
    'span' => null,
    'description' => null,
])

{{--
    Carte de section de formulaire — wrapper cohérent autour de ec-card.
    - `span="full"` : force la pleine largeur (lg:col-span-2).
    - En-tête numéroté + icône optionnel.
    - Slot principal = corps de la section.
--}}
<section {{ $attributes->merge(['class' => 'ec-card overflow-hidden' . ($span === 'full' ? ' lg:col-span-2' : '')]) }}>
    @if ($title)
        <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
            <h2 class="flex items-center gap-3 text-xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300 lg:text-2xl">
                @if ($number !== null)
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">{{ $number }}</span>
                @endif
                @if ($icon)
                    <x-icon :name="$icon" class="h-5 w-5 lg:h-6 lg:w-6" />
                @endif
                {{ $title }}
            </h2>
            @if ($description)
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>
