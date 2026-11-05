<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    /**
     * Interception avant toute vérification.
     * Le Super Admin a tous les droits sur les dossiers professeurs.
     */
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * viewAny : voir la liste des dossiers professeurs.
     * Super Admin, Responsable Centre et Secrétaire y ont accès.
     * Professeur et Élève : 403.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isCenterAdmin() || $authUser->isSecretary();
    }

    /**
     * view : consulter un dossier professeur.
     * Responsable Centre & Secrétaire : uniquement les professeurs de leur propre centre.
     */
    public function view(User $authUser, Teacher $teacher): bool
    {
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $teacher->center_id;
        }

        return false;
    }

    /**
     * create : créer un dossier professeur.
     * Exclusivité Super Admin.
     */
    public function create(User $authUser): bool
    {
        return false;
    }

    /**
     * update : modifier un dossier professeur.
     * Super Admin uniquement.
     * Responsable Centre & Secrétaire : lecture seule sur les profils enseignants.
     */
    public function update(User $authUser, Teacher $teacher): bool
    {
        return false;
    }

    /**
     * toggleStatus : activer / désactiver un dossier professeur.
     * Super Admin uniquement.
     */
    public function toggleStatus(User $authUser, Teacher $teacher): bool
    {
        return false;
    }

    /**
     * delete : supprimer un dossier professeur.
     * Super Admin uniquement.
     */
    public function delete(User $authUser, Teacher $teacher): bool
    {
        return false;
    }
}
