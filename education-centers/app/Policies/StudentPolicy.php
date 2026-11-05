<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Interception avant toute vérification.
     * Le Super Admin a tous les droits sur les dossiers étudiants.
     */
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * viewAny : voir la liste des dossiers étudiants.
     * Accessible au Super Admin, au Responsable Centre (lecture seule) et à la Secrétaire.
     * Professeur et Élève : 403.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isCenterAdmin() || $authUser->isSecretary();
    }

    /**
     * view : consulter un dossier étudiant.
     * Responsable Centre & Secrétaire : uniquement les étudiants de leur propre centre.
     */
    public function view(User $authUser, Student $student): bool
    {
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $student->center_id;
        }

        return false;
    }

    /**
     * create : créer un dossier étudiant.
     * Super Admin et Secrétaire uniquement.
     * Le Responsable Centre est strictement en lecture seule.
     */
    public function create(User $authUser): bool
    {
        return $authUser->isSecretary();
    }

    /**
     * update : modifier un dossier étudiant.
     * Super Admin et Secrétaire (de son propre centre).
     * Le Responsable Centre est strictement en lecture seule.
     */
    public function update(User $authUser, Student $student): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $student->center_id;
        }

        return false;
    }

    /**
     * toggleStatus : activer / désactiver un dossier étudiant.
     * Super Admin et Secrétaire (de son propre centre).
     */
    public function toggleStatus(User $authUser, Student $student): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $student->center_id;
        }

        return false;
    }

    /**
     * delete : supprimer un dossier étudiant.
     * Super Admin uniquement.
     */
    public function delete(User $authUser, Student $student): bool
    {
        return false;
    }
}
