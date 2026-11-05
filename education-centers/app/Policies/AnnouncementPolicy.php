<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
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
        return $user->isCenterAdmin() || $user->isSecretary();
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return ($user->isCenterAdmin() || $user->isSecretary())
            && ($announcement->center_id === null || (int) $user->center_id === (int) $announcement->center_id);
    }

    public function create(User $user): bool
    {
        return $user->isSecretary();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->isSecretary()
            && ($announcement->center_id === null || (int) $user->center_id === (int) $announcement->center_id);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->isSecretary()
            && ($announcement->center_id === null || (int) $user->center_id === (int) $announcement->center_id);
    }
}
