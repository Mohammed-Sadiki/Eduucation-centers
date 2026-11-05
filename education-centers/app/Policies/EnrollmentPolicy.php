<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Le Super Admin a tous les droits sur tous les centres.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Voir la liste des inscriptions.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCenterAdmin() || $user->isSecretary();
    }

    /**
     * Consulter une inscription.
     */
    public function view(User $user, Enrollment $enrollment): bool
    {
        return ($user->isCenterAdmin() || $user->isSecretary()) && (int) $user->center_id === (int) $enrollment->center_id;
    }

    /**
     * Créer une inscription.
     * Super Admin et Secrétaire uniquement.
     */
    public function create(User $user): bool
    {
        return $user->isSecretary();
    }

    /**
     * Mettre à jour une inscription.
     * Super Admin et Secrétaire uniquement.
     */
    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->isSecretary() && (int) $user->center_id === (int) $enrollment->center_id;
    }

    /**
     * Supprimer une inscription.
     * Super Admin uniquement.
     */
    public function delete(User $user, Enrollment $enrollment): bool
    {
        return false;
    }

    /**
     * Modifier le statut d'une inscription (workflow).
     * Super Admin et Secrétaire uniquement.
     */
    public function changeStatus(User $user, Enrollment $enrollment): bool
    {
        return $user->isSecretary() && (int) $user->center_id === (int) $enrollment->center_id;
    }
}
