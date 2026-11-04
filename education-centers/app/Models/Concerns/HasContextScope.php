<?php

namespace App\Models\Concerns;

use App\Support\AcademicPerimeter;
use App\Support\UserContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtrage « dans le contexte courant » (centre + formation générale).
 *
 * Contrairement à un global scope, ce filtrage est explicite et opt-in : les
 * contrôleurs qui doivent respecter le périmètre appelent `withinContext()`.
 * Il s'appuie sur :
 *  - la colonne `center_id` de la table pour le volet centre ;
 *  - l'autorité unique du périmètre
 *    {@see AcademicPerimeter::applyTo()} pour le volet formation,
 *    lorsque le modèle sait se rattacher à une formation.
 *
 * Super Admin sans centre restreint et sans espace de formation actif => aucun
 * filtre (vue globale), ce qui préserve le comportement existant.
 */
trait HasContextScope
{
    /**
     * Restreindre la requête au centre et à la formation générale du contexte courant.
     */
    public function scopeWithinContext(Builder $query, ?UserContext $context = null): Builder
    {
        $context ??= app(UserContext::class);

        // Volet centre : uniquement si la table porte center_id.
        if ($context->hasCenter() && $this->hasCenterColumn()) {
            $query->where($this->qualifyColumn('center_id'), $context->centerId());
        }

        // Volet formation : même autorité que les listes et les menus déroulants.
        if ($context->hasDomain()) {
            AcademicPerimeter::for($context->user(), $context->centerId(), $context->domainSlug())
                ->applyTo($query);
        }

        return $query;
    }

    /**
     * Seulement le volet centre du contexte.
     */
    public function scopeWithinCenterContext(Builder $query, ?UserContext $context = null): Builder
    {
        $context ??= app(UserContext::class);

        if ($context->hasCenter() && $this->hasCenterColumn()) {
            $query->where($this->qualifyColumn('center_id'), $context->centerId());
        }

        return $query;
    }

    /**
     * La table possède-t-elle une colonne center_id ?
     */
    protected function hasCenterColumn(): bool
    {
        return array_key_exists('center_id', $this->getAttributes())
            || in_array('center_id', $this->getFillable(), true);
    }
}
