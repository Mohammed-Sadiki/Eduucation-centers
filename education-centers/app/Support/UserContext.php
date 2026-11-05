<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\User;

/**
 * Contexte applicatif résolu pour la requête courante.
 *
 * Centralise trois dimensions qui pilotent le filtrage et la sécurité :
 *  - le rôle / l'utilisateur connecté ;
 *  - le centre de travail (null = tous les centres pour le Super Admin,
 *    forcé au centre propre pour un Admin Centre ou Secrétaire) ;
 *  - le domaine de formation actif (slug validé côté serveur).
 *
 * Il est construit par le middleware {@see \App\Http\Middleware\ResolveDomainContext}
 * et accessible via le container ou le helper global `context()`.
 */
class UserContext
{
    public function __construct(
        protected ?User $currentUser = null,
        protected ?string $domainSlug = null,
        protected ?int $centerId = null,
    ) {}

    public function user(): ?User
    {
        return $this->currentUser;
    }

    public function role(): ?string
    {
        return $this->currentUser?->role;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->currentUser?->isSuperAdmin();
    }

    public function isCenterAdmin(): bool
    {
        return (bool) $this->currentUser?->isCenterAdmin();
    }

    public function isSecretary(): bool
    {
        return (bool) $this->currentUser?->isSecretary();
    }

    /**
     * Identifiants = true pour l'Admin Centre, la Secrétaire et tous les rôles rattachés à un
     * centre. Le Super Admin a center_id NULL => retourne null (tous centres).
     */
    public function centerId(): ?int
    {
        return $this->centerId;
    }

    /**
     * Un filtre centre est-il actif (périmètre restreint à un seul centre).
     */
    public function hasCenter(): bool
    {
        return $this->centerId !== null;
    }

    /**
     * Le Super Admin peut restreindre volontairement sa vue à un centre ;
     * un Admin Centre ou une Secrétaire est figé sur son centre (pas de changement possible).
     */
    public function canSwitchCenter(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Slug du domaine actif (null si aucun domaine sélectionné / contexte « tous »).
     */
    public function domainSlug(): ?string
    {
        return $this->domainSlug;
    }

    public function hasDomain(): bool
    {
        return $this->domainSlug !== null;
    }

    /**
     * Modèle Domain du domaine actif (null si aucun).
     */
    public function domain(): ?Domain
    {
        return $this->domainSlug ? DomainRegistry::find($this->domainSlug) : null;
    }

    /**
     * Libellé du domaine actif, ou null.
     */
    public function domainLabel(): ?string
    {
        return $this->domain()?->name;
    }
}
