<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\Formation;
use App\Models\User;

/**
 * Contexte applicatif résolu pour la requête courante.
 *
 * Centralise quatre dimensions qui pilotent le filtrage et la sécurité :
 *  - le rôle / l'utilisateur connecté ;
 *  - le centre de travail (null = tous les centres pour le Super Admin,
 *    forcé au centre propre pour un Admin Centre ou Secrétaire) ;
 *  - le domaine de formation actif (slug validé côté serveur) ;
 *  - la formation active (espace formation isolée).
 *
 * Il est construit par le middleware {@see \App\Http\Middleware\ResolveDomainContext}
 * et accessible via le container ou le helper global `context()`.
 */
class UserContext
{
    protected ?Formation $resolvedFormation = null;

    public function __construct(
        protected ?User $currentUser = null,
        protected ?string $domainSlug = null,
        protected ?int $centerId = null,
        protected ?int $formationId = null,
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
     * ID de la formation active (null si aucun espace formation spécifique sélectionné).
     */
    public function formationId(): ?int
    {
        return $this->formationId;
    }

    public function hasFormation(): bool
    {
        return $this->formationId !== null;
    }

    /**
     * Modèle Formation actif (résolu à la demande).
     */
    public function formation(): ?Formation
    {
        if ($this->formationId === null) {
            return null;
        }

        if ($this->resolvedFormation === null) {
            $this->resolvedFormation = Formation::find($this->formationId);
        }

        return $this->resolvedFormation;
    }

    /**
     * Modèle Domain du domaine actif (null si aucun).
     */
    public function domain(): ?Domain
    {
        return $this->domainSlug ? DomainRegistry::find($this->domainSlug) : null;
    }

    /**
     * Libellé du domaine ou de la formation active.
     */
    public function domainLabel(): ?string
    {
        if ($this->hasFormation() && ($formation = $this->formation())) {
            return $formation->translated('name') ?? $formation->name;
        }

        return $this->domain()?->name;
    }
}
