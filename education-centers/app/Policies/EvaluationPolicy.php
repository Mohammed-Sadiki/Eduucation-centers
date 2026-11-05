<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isCenterAdmin() || $user->isSecretary() || $user->isTeacher() || $user->isStudent();
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        if ($user->isStudent()) {
            if ((int) $user->center_id !== (int) $evaluation->center_id) {
                return false;
            }

            if (! in_array($evaluation->status, ['published', 'closed'], true)) {
                return false;
            }

            return $user->student !== null
                && Enrollment::query()
                    ->where('student_id', $user->student->id)
                    ->where('center_id', $user->center_id)
                    ->where('class_id', $evaluation->class_id)
                    ->where('status', Enrollment::STATUS_ACCEPTED)
                    ->exists();
        }

        if ($user->isCenterAdmin() || $user->isSecretary()) {
            return (int) $user->center_id === (int) $evaluation->center_id;
        }

        return $this->teacherOwnsEvaluation($user, $evaluation);
    }

    public function create(User $user): bool
    {
        return $user->isSecretary() || $user->isTeacher();
    }

    public function update(User $user, Evaluation $evaluation): bool
    {
        if ($user->isSecretary()) {
            return (int) $user->center_id === (int) $evaluation->center_id;
        }

        return $this->teacherOwnsEvaluation($user, $evaluation);
    }

    public function delete(User $user, Evaluation $evaluation): bool
    {
        return $user->isSecretary() && (int) $user->center_id === (int) $evaluation->center_id;
    }

    /**
     * Le professeur doit être propriétaire de l'évaluation ET affecté à sa
     * classe, dans son propre centre.
     */
    private function teacherOwnsEvaluation(User $user, Evaluation $evaluation): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        $teacher = $user->teacher;

        return $teacher !== null
            && (int) $evaluation->center_id === (int) $teacher->center_id
            && (int) $evaluation->teacher_id === (int) $teacher->id
            && $evaluation->classRoom !== null
            && (int) $evaluation->classRoom->center_id === (int) $teacher->center_id
            && $evaluation->classRoom->hasTeacher($teacher);
    }
}
