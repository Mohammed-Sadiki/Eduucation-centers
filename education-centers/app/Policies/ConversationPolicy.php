<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        // Must be a registered participant in the conversation
        if (! $conversation->isParticipant($user)) {
            return false;
        }

        // Additional center isolation check:
        // If the user is a Center Admin or Secretary, verify they belong to the conversation's center
        if ($user->isCenterAdmin() || $user->isSecretary()) {
            if ($conversation->center_id && (int) $conversation->center_id !== (int) $user->center_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can send a message in the conversation.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
