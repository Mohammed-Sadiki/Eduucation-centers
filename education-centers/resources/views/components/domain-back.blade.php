{{-- Lien « retour au tableau de bord du domaine » affiché uniquement quand un
     domaine est actif en session (navigation depuis les cartes KPI du dashboard).
     Le destinataire du retour dépend du rôle : dashboard domaine de l'Admin
     Centre ou du Super Admin. Aucun lien si aucun domaine n'est sélectionné. --}}
@php
    $ctx = user_context();
    $backUrl = null;
    if ($ctx->hasDomain() && ($ctx->isSuperAdmin() || $ctx->isCenterAdmin())) {
        $routeName = $ctx->isCenterAdmin()
            ? 'center-admin.domain.dashboard'
            : 'super-admin.domain.dashboard';
        $backUrl = route($routeName, $ctx->domainSlug());
    }
    $domainLabel = $ctx->hasDomain() ? (\App\Support\DomainRegistry::labels()[$ctx->domainSlug()] ?? $ctx->domainSlug()) : null;
@endphp

@if($backUrl)
    <a href="{{ $backUrl }}"
       id="link-retour-dashboard-domaine"
       class="mb-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-600 shadow-2xs transition hover:-translate-y-px hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
       title="{{ __('Revenir au tableau de bord de la formation générale') }} {{ $domainLabel }}">
        <x-icon name="arrow-uturn-left" class="h-4 w-4 text-slate-400 rtl:rotate-180 dark:text-slate-500" />
        <span>{{ __('Retour tableau de bord') }} · {{ $domainLabel }}</span>
    </a>
@endif
