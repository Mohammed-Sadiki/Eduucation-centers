<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AcademicPerimeter;
use App\Support\UserContext;
use Illuminate\Database\Eloquent\Builder;

abstract class Controller
{
    /**
     * Autorité unique du périmètre pédagogique « Centre → Formation générale →
     * Classe → Élève » pour un utilisateur donné. Le centre d'un Admin Centre
     * (ou d'un professeur) est toujours forcé côté serveur.
     */
    protected function perimeter(?User $user, ?int $centerId = null): AcademicPerimeter
    {
        return AcademicPerimeter::for($user, $centerId);
    }

    /**
     * Restreindre une requête à l'espace pédagogique actif (aucun effet si
     * aucun domaine n'est sélectionné).
     *
     * Le « domaine actif » du contexte est aujourd'hui le code d'une formation
     * générale : ce filtre passe donc par l'autorité unique du périmètre
     * ({@see AcademicPerimeter}) et résout les alias historiques, au lieu
     * d'une comparaison directe sur une colonne `domain`. Un seul mécanisme de
     * filtrage pour toute l'application : les tableaux suivent exactement les
     * mêmes règles que les menus déroulants des formations et des classes.
     */
    protected function applyDomainScope(Builder $query, ?UserContext $context = null): Builder
    {
        $context ??= user_context();

        if (! $context->hasDomain() && ! $context->hasFormation()) {
            return $query;
        }

        return $this->perimeter($context->user(), $context->centerId())->applyTo($query);
    }

    /**
     * Restreindre une requête au centre + domaine du contexte courant.
     * Le volet centre n'est appliqué que si la table porte center_id et que
     * le contexte est restreint à un centre (Admin Centre, ou Super Admin
     * ayant volontairement filtré). Le modèle doit utiliser HasContextScope.
     */
    protected function applyContextScope(Builder $query, ?UserContext $context = null): Builder
    {
        $context ??= user_context();

        if (method_exists($query->getModel(), 'scopeWithinContext')) {
            $query->withinContext($context);
        }

        return $query;
    }
}
