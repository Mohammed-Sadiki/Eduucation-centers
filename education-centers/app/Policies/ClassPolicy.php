<?php

namespace App\Policies;

use App\Models\ClassRoom;
use App\Models\User;

class ClassPolicy
{
    /**
     * Interception avant toute vérification.
     * Le Super Admin a tous les droits sur toutes les classes de tous les centres.
     */
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * viewAny : voir la liste des classes.
     * Admin Centre (Responsable) & Secrétaire : leur centre.
     * Professeur : uniquement ses classes affectées.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isCenterAdmin()
            || $authUser->isSecretary()
            || ($authUser->isTeacher() && $authUser->teacher !== null);
    }

    /**
     * view : consulter une classe spécifique.
     * Admin Centre & Secrétaire : leur centre.
     * Professeur : classe affectée via class_teacher.
     */
    public function view(User $authUser, ClassRoom $classRoom): bool
    {
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $classRoom->center_id;
        }

        if ($authUser->isTeacher()) {
            $teacher = $authUser->teacher;

            return $teacher !== null
                && (int) $teacher->center_id === (int) $classRoom->center_id
                && $classRoom->hasTeacher($teacher);
        }

        return false;
    }

    /**
     * create : créer une classe.
     * Super Admin et Secrétaire uniquement.
     * Responsable Centre : lecture seule.
     */
    public function create(User $authUser): bool
    {
        return $authUser->isSecretary();
    }

    /**
     * update : modifier une classe.
     * Super Admin et Secrétaire uniquement.
     * Responsable Centre : lecture seule.
     */
    public function update(User $authUser, ClassRoom $classRoom): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $classRoom->center_id;
        }

        return false;
    }

    /**
     * toggleStatus : activer / désactiver une classe.
     * Super Admin et Secrétaire uniquement.
     */
    public function toggleStatus(User $authUser, ClassRoom $classRoom): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $classRoom->center_id;
        }

        return false;
    }

    /**
     * delete : supprimer une classe.
     * Super Admin uniquement.
     */
    public function delete(User $authUser, ClassRoom $classRoom): bool
    {
        return false;
    }

    /**
     * assignTeacher : affecter un professeur à une classe.
     * Super Admin et Secrétaire uniquement.
     */
    public function assignTeacher(User $authUser, ClassRoom $classRoom): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $classRoom->center_id;
        }

        return false;
    }

    /**
     * unassignTeacher : retirer un professeur d'une classe.
     * Super Admin et Secrétaire uniquement.
     */
    public function unassignTeacher(User $authUser, ClassRoom $classRoom): bool
    {
        if ($authUser->isSecretary()) {
            return (int) $authUser->center_id === (int) $classRoom->center_id;
        }

        return false;
    }
}
