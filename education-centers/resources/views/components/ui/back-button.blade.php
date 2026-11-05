@props([
    'fallback' => null,
    'label' => null,
    'classes' => '',
])

@php
    $fallbackUrl = \App\Support\NavigationHelper::resolveFallback($fallback);
    $buttonLabel = $label ?? __('Retour');
    $buttonId = $attributes->get('id', 'universal-back-button');
    $hasSuccessFlash = session()->has('success') || session()->has('status');
@endphp

<div class="inline-flex shrink-0 items-center [direction:ltr]" dir="ltr">
    <a href="{{ $fallbackUrl }}"
       id="{{ $buttonId }}"
       x-data="{
           hasSuccess: {{ $hasSuccessFlash ? 'true' : 'false' }},
           handleBack(event) {
               // Si un formulaire vient d'être soumis avec succès, respecter la redirection et aller au parent
               if (this.hasSuccess) {
                   return;
               }

               const referrer = document.referrer;
               const currentHost = window.location.host;

               // Si aucun historique ou navigation directe
               if (window.history.length <= 1 || !referrer) {
                   return;
               }

               try {
                   const refUrl = new URL(referrer);

                   // Si le référent ne provient pas de l'application
                   if (refUrl.host !== currentHost) {
                       return;
                   }

                   // Éviter les boucles si le référent est la même page
                   if (refUrl.pathname === window.location.pathname) {
                       return;
                   }

                   // Ne jamais retourner vers les pages d'authentification
                   if (refUrl.pathname.includes('/login') || refUrl.pathname.includes('/logout') || refUrl.pathname.includes('/register') || refUrl.pathname.includes('/password')) {
                       return;
                   }

                   // Navigation historique valide
                   event.preventDefault();
                   window.history.back();
               } catch (e) {
                   // Repli vers href natif en cas d'exception
               }
           }
       }"
       x-on:click="handleBack($event)"
       title="{{ $buttonLabel }}"
       aria-label="{{ $buttonLabel }}"
       class="group inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-2xs transition-all duration-150 hover:-translate-x-0.5 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:bg-slate-800 dark:hover:text-white active:scale-95 {{ $classes }}">
        <svg class="h-5 w-5 transition-transform duration-150 group-hover:-translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
    </a>
</div>
