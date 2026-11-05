<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
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
     * Voir la liste des séances.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCenterAdmin() || $user->isSecretary() || $user->isTeacher() || $user->isStudent();
    }

    /**
     * Voir le détail d'une séance.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        if ($user->isStudent()) {
            if ((int) $user->center_id !== (int) $schedule->center_id || $user->student === null) {
                return false;
            }

            return Enrollment::query()
                ->where('student_id', $user->student->id)
                ->where('center_id', $user->center_id)
                ->where('class_id', $schedule->class_id)
                ->where('status', Enrollment::STATUS_ACCEPTED)
                ->exists();
        }

        if ($user->isCenterAdmin() || $user->isSecretary()) {
            return (int) $user->center_id === (int) $schedule->center_id;
        }

        if ($user->isTeacher()) {
            $teacher = $user->teacher;

            return $teacher !== null
                && (int) $schedule->center_id === (int) $teacher->center_id
                && (int) $schedule->teacher_id === (int) $teacher->id
                && $schedule->classRoom !== null
                && $schedule->classRoom->hasTeacher($teacher);
        }

        return false;
    }

    /**
     * Créer une séance.
     * Super Admin et Secrétaire uniquement.
     * Responsable Centre : lecture seule.
     */
    public function create(User $user): bool
    {
        return $user->isSecretary();
    }

    /**
     * Mettre à jour une séance.
     * Super Admin et Secrétaire uniquement.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        return $user->isSecretary() && (int) $user->center_id === (int) $schedule->center_id;
    }

    /**
     * Supprimer une séance.
     * Super Admin uniquement.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        return false;
    }

    /**
     * Modifier le statut (annuler, terminer, etc.).
     * Super Admin et Secrétaire uniquement.
     */
    public function toggleStatus(User $user, Schedule $schedule): bool
    {
        return $user->isSecretary() && (int) $user->center_id === (int) $schedule->center_id;
    }
}
