<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
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
        return $user->isCenterAdmin() || $user->isSecretary() || $user->isStudent();
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->isStudent()) {
            return (int) $user->center_id === (int) $document->center_id
                && $user->student?->id === $document->student_id
                && $document->status === 'active';
        }

        return ($user->isCenterAdmin() || $user->isSecretary()) && (int) $user->center_id === (int) $document->center_id;
    }

    public function create(User $user): bool
    {
        return $user->isSecretary();
    }

    public function update(User $user, Document $document): bool
    {
        return $user->isSecretary() && (int) $user->center_id === (int) $document->center_id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isSecretary() && (int) $user->center_id === (int) $document->center_id;
    }

    public function download(User $user, Document $document): bool
    {
        if ($user->isStudent()) {
            return (int) $user->center_id === (int) $document->center_id
                && $user->student?->id === $document->student_id
                && $document->status === 'active';
        }

        return ($user->isCenterAdmin() || $user->isSecretary() || $user->isSuperAdmin())
            && ((int) $user->center_id === (int) $document->center_id || $user->isSuperAdmin());
    }
}
