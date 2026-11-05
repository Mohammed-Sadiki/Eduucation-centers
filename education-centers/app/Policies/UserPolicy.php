<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Interception avant toute vérification.
     * Le Super Admin a tous les droits sur les utilisateurs.
     */
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * viewAny : voir la liste des utilisateurs.
     * Super Admin : tous | Responsable Centre : seulement son centre (lecture seule).
     * Secrétaire & Teacher & Student : interdit (403).
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isSuperAdmin() || $authUser->isCenterAdmin();
    }

    /**
     * view : consulter un utilisateur spécifique.
     * Responsable Centre ne peut voir que les utilisateurs de son centre (lecture seule).
     * Secrétaire : interdit.
     */
    public function view(User $authUser, User $user): bool
    {
        if ($authUser->isCenterAdmin()) {
            return (int) $authUser->center_id === (int) $user->center_id;
        }

        return false;
    }

    /**
     * create : créer un utilisateur.
     * Exclusivité Super Admin (création de responsables, secrétaires, profs).
     * Responsable Centre et Secrétaire ne peuvent pas créer d'utilisateurs.
     */
    public function create(User $authUser): bool
    {
        return false;
    }

    /**
     * update : modifier un utilisateur.
     * Exclusivité Super Admin.
     * Le Responsable Centre est strictement en lecture seule, Secrétaire n'a pas accès.
     */
    public function update(User $authUser, User $user): bool
    {
        return false;
    }

    /**
     * delete : supprimer un utilisateur.
     * Super Admin uniquement (après contrôle métier).
     */
    public function delete(User $authUser, User $user): bool
    {
        if ($authUser->isSuperAdmin()) {
            // Ne peut pas supprimer le seul super_admin restant
            if ($user->isSuperAdmin()) {
                return User::where('role', User::ROLE_SUPER_ADMIN)->count() > 1;
            }

            return true;
        }

        return false;
    }

    /**
     * toggleStatus : désactiver/réactiver un utilisateur.
     * Super Admin uniquement.
     * Responsable Centre : lecture seule.
     */
    public function toggleStatus(User $authUser, User $user): bool
    {
        return false;
    }
}
