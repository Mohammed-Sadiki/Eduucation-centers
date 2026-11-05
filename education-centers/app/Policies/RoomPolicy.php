<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    /**
     * Interception avant toute vérification.
     * Le Super Admin a tous les droits sur toutes les salles de tous les centres.
     */
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * viewAny : voir la liste des salles.
     * Accessible au Super Admin, au Responsable Centre et à la Secrétaire.
     * Refusé pour Professeur et Élève (403).
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isCenterAdmin() || $authUser->isSecretary();
    }

    /**
     * view : consulter une salle spécifique.
     * Responsable Centre & Secrétaire : uniquement les salles de leur propre centre.
     */
    public function view(User $authUser, Room $room): bool
    {
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $room->center_id;
        }

        return false;
    }

    /**
     * create : créer une salle.
     * Super Admin et Secrétaire uniquement.
     * Responsable Centre : lecture seule.
     */
    public function create(User $authUser): bool
    {
        return $authUser->isSecretary();
    }

    /**
     * update : modifier une salle.
     * Super Admin et Secrétaire uniquement.
     * Responsable Centre : lecture seule.
     */
    public function update(User $authUser, Room $room): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $room->center_id;
        }

        return false;
    }

    /**
     * toggleStatus : activer / désactiver une salle.
     * Super Admin et Secrétaire uniquement.
     */
    public function toggleStatus(User $authUser, Room $room): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $room->center_id;
        }

        return false;
    }

    /**
     * delete : supprimer une salle.
     * Super Admin uniquement.
     */
    public function delete(User $authUser, Room $room): bool
    {
        return false;
    }
}
