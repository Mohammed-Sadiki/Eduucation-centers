@props([
    'domain',
    'routeUrl',
    'imageUrl' => null,
    'iconName' => null,
    'badgeLabel' => null,
])

@php
    // Libellé du badge de statut : personnalisable (ex. « Personnalisée »)
    // tout en conservant « Actif » par défaut pour le catalogue officiel.
    $badgeLabel ??= __('Actif');

    $paletteConfig = [
        'sante' => [
            'color'       => 'emerald',
            'img'         => 'sante',
            'icon'        => 'heart',
            'card_bg'     => 'bg-emerald-50/50 dark:bg-emerald-950/20',
            'card_border' => 'border-emerald-200/80 dark:border-emerald-800/50',
            'chip'        => 'bg-emerald-500 text-white shadow-emerald-500/30',
            'badge'       => 'bg-emerald-100/90 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
            'dot'         => 'bg-emerald-500',
            'btn'         => 'bg-emerald-100/80 hover:bg-emerald-200/90 text-emerald-800 dark:bg-emerald-500/20 dark:hover:bg-emerald-500/30 dark:text-emerald-200',
            'circle'      => 'bg-emerald-500 text-white',
        ],
        'informatique' => [
            'color'       => 'blue',
            'img'         => 'informatique',
            'icon'        => 'monitor',
            'card_bg'     => 'bg-blue-50/50 dark:bg-blue-950/20',
            'card_border' => 'border-blue-200/80 dark:border-blue-800/50',
            'chip'        => 'bg-blue-500 text-white shadow-blue-500/30',
            'badge'       => 'bg-blue-100/90 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300',
            'dot'         => 'bg-blue-500',
            'btn'         => 'bg-blue-100/80 hover:bg-blue-200/90 text-blue-800 dark:bg-blue-500/20 dark:hover:bg-blue-500/30 dark:text-blue-200',
            'circle'      => 'bg-blue-500 text-white',
        ],
        'hotellerie' => [
            'color'       => 'amber',
            'img'         => 'hotellerie',
            'icon'        => 'building',
            'card_bg'     => 'bg-amber-50/50 dark:bg-amber-950/20',
            'card_border' => 'border-amber-200/80 dark:border-amber-800/50',
            'chip'        => 'bg-amber-500 text-white shadow-amber-500/30',
            'badge'       => 'bg-amber-100/90 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
            'dot'         => 'bg-amber-500',
            'btn'         => 'bg-amber-100/80 hover:bg-amber-200/90 text-amber-800 dark:bg-amber-500/20 dark:hover:bg-amber-500/30 dark:text-amber-200',
            'circle'      => 'bg-amber-500 text-white',
        ],
        'maconnerie' => [
            'color'       => 'orange',
            'img'         => 'maconnerie',
            'icon'        => 'wrench',
            'card_bg'     => 'bg-orange-50/50 dark:bg-orange-950/20',
            'card_border' => 'border-orange-200/80 dark:border-orange-800/50',
            'chip'        => 'bg-orange-500 text-white shadow-orange-500/30',
            'badge'       => 'bg-orange-100/90 text-orange-800 dark:bg-orange-500/20 dark:text-orange-300',
            'dot'         => 'bg-orange-500',
            'btn'         => 'bg-orange-100/80 hover:bg-orange-200/90 text-orange-800 dark:bg-orange-500/20 dark:hover:bg-orange-500/30 dark:text-orange-200',
            'circle'      => 'bg-orange-500 text-white',
        ],
        'design-interieur' => [
            'color'       => 'violet',
            'img'         => 'design-interieur',
            'icon'        => 'home',
            'card_bg'     => 'bg-violet-50/50 dark:bg-violet-950/20',
            'card_border' => 'border-violet-200/80 dark:border-violet-800/50',
            'chip'        => 'bg-violet-500 text-white shadow-violet-500/30',
            'badge'       => 'bg-violet-100/90 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
            'dot'         => 'bg-violet-500',
            'btn'         => 'bg-violet-100/80 hover:bg-violet-200/90 text-violet-800 dark:bg-violet-500/20 dark:hover:bg-violet-500/30 dark:text-violet-200',
            'circle'      => 'bg-violet-500 text-white',
        ],
        'design-graphique' => [
            'color'       => 'pink',
            'img'         => 'design-graphique',
            'icon'        => 'brush',
            'card_bg'     => 'bg-pink-50/50 dark:bg-pink-950/20',
            'card_border' => 'border-pink-200/80 dark:border-pink-800/50',
            'chip'        => 'bg-pink-500 text-white shadow-pink-500/30',
            'badge'       => 'bg-pink-100/90 text-pink-800 dark:bg-pink-500/20 dark:text-pink-300',
            'dot'         => 'bg-pink-500',
            'btn'         => 'bg-pink-100/80 hover:bg-pink-200/90 text-pink-800 dark:bg-pink-500/20 dark:hover:bg-pink-500/30 dark:text-pink-200',
            'circle'      => 'bg-pink-500 text-white',
        ],
    ];

    $cfg = $paletteConfig[$domain->slug] ?? [
        'color'       => 'brand',
        'img'         => $domain->slug,
        'icon'        => $domain->icon ?: 'home',
        'card_bg'     => 'bg-slate-50/50 dark:bg-slate-900/30',
        'card_border' => 'border-slate-200 dark:border-slate-800',
        'chip'        => 'bg-brand-500 text-white shadow-brand-500/30',
        'badge'       => 'bg-brand-100/90 text-brand-800 dark:bg-brand-500/20 dark:text-brand-300',
        'dot'         => 'bg-brand-500',
        'btn'         => 'bg-brand-100/80 hover:bg-brand-200/90 text-brand-800 dark:bg-brand-500/20 dark:hover:bg-brand-500/30 dark:text-brand-200',
        'circle'      => 'bg-brand-500 text-white',
    ];

    $imageWebp = "images/domaines/{$cfg['img']}.webp";
    $imageJpg  = "images/domaines/{$cfg['img']}.jpg";
    $hasImage = $imageUrl !== null || file_exists(public_path($imageWebp)) || file_exists(public_path($imageJpg));
@endphp

<div id="card-domaine-{{ $domain->slug }}"
     class="group flex flex-col overflow-hidden rounded-3xl border {{ $cfg['card_border'] }} {{ $cfg['card_bg'] }} shadow-xs transition-all duration-200 hover:-translate-y-1 hover:shadow-xl dark:shadow-none">

    {{-- 1. Grande photographie illustrative horizontale en haut --}}
    <div class="relative aspect-16/9 w-full overflow-hidden bg-slate-100 dark:bg-slate-800">
        @if ($imageUrl !== null)
            {{-- Image fournie par une formation personnalisée (upload centre) --}}
            <img src="{{ $imageUrl }}"
                 alt="{{ __('Illustration de la formation :domain', ['domain' => __($domain->name)]) }}"
                 loading="lazy"
                 class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            >
        @elseif ($hasImage)
            <picture>
                @if (file_exists(public_path($imageWebp)))
                    <source srcset="{{ asset($imageWebp) }}" type="image/webp">
                @endif
                <img src="{{ asset($imageJpg) }}"
                     alt="{{ __('Photographie du domaine :domain', ['domain' => __($domain->name)]) }}"
                     loading="lazy"
                     class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                     onerror="this.onerror=null; this.parentElement.style.display='none'; this.closest('.relative').querySelector('.fallback-banner').classList.remove('hidden');"
                >
            </picture>
        @endif

        {{-- Fallback visuel élégant si l'image est indisponible --}}
        <div class="fallback-banner {{ $hasImage ? 'hidden' : 'flex' }} absolute inset-0 items-center justify-center bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-800 dark:to-slate-900">
            <x-icon :name="$iconName ?: $cfg['icon']" class="h-14 w-14 text-slate-400 dark:text-slate-600" />
        </div>
    </div>

    {{-- Corps de la carte --}}
    <div class="flex flex-1 flex-col p-6 sm:p-7">
        {{-- En-tête : Icône SVG + Titre + Badge Actif --}}
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                {{-- 2. Icône SVG colorée représentant le domaine --}}
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl shadow-md {{ $cfg['chip'] }}" aria-hidden="true">
                    <x-icon :name="$iconName ?: $cfg['icon']" class="h-6 w-6 text-white" stroke-width="2" />
                </span>

                {{-- 3. Titre du domaine --}}
                <h3 class="truncate text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __($domain->name) }}
                </h3>
            </div>

            {{-- 4. Badge de statut (« Actif » par défaut, personnalisable) --}}
            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $cfg['badge'] }}">
                <span class="h-2 w-2 rounded-full {{ $cfg['dot'] }}" aria-hidden="true"></span>
                <span>{{ $badgeLabel }}</span>
            </span>
        </div>

        {{-- 5. Description courte --}}
        <p class="mt-4 flex-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __($domain->description) }}
        </p>

        {{-- 6. Bouton coloré « Accéder à l'espace [Nom du domaine] » avec une flèche --}}
        <div class="mt-6 pt-2">
            <a href="{{ $routeUrl }}"
               class="group/btn flex w-full items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition-all duration-150 {{ $cfg['btn'] }}"
               aria-label="{{ __('Accéder à l\'espace :domain', ['domain' => __($domain->name)]) }}">
                <span class="truncate">
                    {{ __('Accéder à l\'espace :domain', ['domain' => __($domain->name)]) }}
                </span>
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full shadow-xs transition-transform duration-150 group-hover/btn:translate-x-1 rtl:group-hover/btn:-translate-x-1 {{ $cfg['circle'] }}" aria-hidden="true">
                    <x-icon name="arrow-right" class="h-4 w-4 rtl:rotate-180" stroke-width="2" />
                </span>
            </a>
        </div>
    </div>
</div>
