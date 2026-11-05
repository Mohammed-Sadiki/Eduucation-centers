<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
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

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->isStudent()) {
            return (int) $user->center_id === (int) $attendance->center_id && $user->student?->id === $attendance->student_id;
        }

        if ($user->isCenterAdmin() || $user->isSecretary()) {
            return (int) $user->center_id === (int) $attendance->center_id;
        }

        return $this->teacherCanAccessAttendance($user, $attendance);
    }

    public function create(User $user): bool
    {
        return $user->isSecretary() || ($user->isTeacher() && $user->teacher !== null);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->isSecretary()) {
            return (int) $user->center_id === (int) $attendance->center_id;
        }

        return $this->teacherCanAccessAttendance($user, $attendance)
            && $attendance->schedule !== null
            && (int) $attendance->schedule->teacher_id === (int) $user->teacher?->id;
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->isSecretary() && (int) $user->center_id === (int) $attendance->center_id;
    }

    /**
     * Le professeur ne voit que les présences de ses classes affectées.
     */
    private function teacherCanAccessAttendance(User $user, Attendance $attendance): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        $teacher = $user->teacher;

        return $teacher !== null
            && (int) $attendance->center_id === (int) $teacher->center_id
            && $attendance->classRoom !== null
            && (int) $attendance->classRoom->center_id === (int) $teacher->center_id
            && $attendance->classRoom->hasTeacher($teacher);
    }
}
