<?php

namespace App\Policies;

use App\Models\Formation;
use App\Models\User;

class FormationPolicy
{
    /**
     * Interception avant toute vérification.
     * Le Super Admin a tous les droits sur les formations de tous les centres.
     */
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * viewAny : voir la liste des formations.
     * Accessible au Super Admin, au Responsable Centre et à la Secrétaire.
     * Refusé pour Professeur et Élève (403).
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isCenterAdmin() || $authUser->isSecretary();
    }

    /**
     * view : consulter une formation spécifique.
     * Responsable Centre & Secrétaire : uniquement les formations de leur propre centre.
     */
    public function view(User $authUser, Formation $formation): bool
    {
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $formation->center_id;
        }

        return false;
    }

    /**
     * create : créer une formation.
     * Super Admin et Secrétaire uniquement.
     * Responsable Centre : lecture seule.
     */
    public function create(User $authUser): bool
    {
        return $authUser->isSecretary();
    }

    /**
     * update : modifier une formation.
     * Super Admin et Secrétaire uniquement (sur les formations de son centre).
     * Responsable Centre : lecture seule.
     */
    public function update(User $authUser, Formation $formation): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $formation->center_id;
        }

        return false;
    }

    /**
     * toggleStatus : activer / désactiver une formation.
     * Super Admin et Secrétaire uniquement.
     */
    public function toggleStatus(User $authUser, Formation $formation): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $formation->center_id;
        }

        return false;
    }

    /**
     * delete : supprimer une formation.
     * Super Admin uniquement.
     */
    public function delete(User $authUser, Formation $formation): bool
    {
        return false;
    }
}
