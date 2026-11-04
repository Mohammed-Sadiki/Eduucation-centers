@props(['num' => null, 'icon' => null, 'title' => null, 'span' => null])

{{--
    Carte de section du formulaire élève (création ET modification).
    Reproduit exactement l'écriture de la page « Ajouter un élève », qui reste
    la référence : carte ec-card, en-tête numéroté + icône, corps en slot.
    `span="full"` occupe toute la largeur (lg:col-span-2) comme la section Paiement.
--}}
<section class="ec-card overflow-hidden{{ $span === 'full' ? ' lg:col-span-2' : '' }}">
    <header class="border-b border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-800/40">
        <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-brand-700 dark:text-brand-300">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-base font-extrabold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">{{ $num }}</span>
            @if($icon)
                <x-icon :name="$icon" class="h-6 w-6" />
            @endif
            {{ $title }}
        </h2>
    </header>

    {{ $slot }}
</section>
