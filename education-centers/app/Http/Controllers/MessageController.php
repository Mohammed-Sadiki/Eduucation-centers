<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    /**
     * Interface principale de messagerie (Super Admin et Admin Centre).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Seuls le Super Admin, les Admins de Centre et les Secrétaires accèdent à cette interface
        if (! $user->isSuperAdmin() && ! $user->isCenterAdmin() && ! $user->isSecretary()) {
            abort(403, __('Accès non autorisé au module de messagerie.'));
        }

        // Récupération des contacts autorisés selon la matrice
        $contacts = $this->getAllowedContacts($user);

        // Conversation active passée en paramètre d'URL (ex: conversation_id)
        $activeConversationId = $request->query('conversation_id');

        return view('messages.index', compact('user', 'contacts', 'activeConversationId'));
    }

    /**
     * Espace "Messagerie" dédié aux professeurs.
     */
    public function teacherIndex(Request $request)
    {
        $user = $request->user();

        if (! $user->isTeacher()) {
            abort(403, __('Accès réservé aux professeurs.'));
        }

        $contacts = $this->getAllowedContacts($user);
        $activeConversationId = $request->query('conversation_id');

        return view('teacher.messages', compact('user', 'contacts', 'activeConversationId'));
    }

    /**
     * Liste JSON des conversations pour l'utilisateur connecté.
     */
    public function getConversations(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_CENTER_ADMIN, User::ROLE_SECRETARY, User::ROLE_TEACHER], true)) {
            abort(403, __('Accès non autorisé.'));
        }

        $conversations = Conversation::whereHas('participants', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->when($user->isCenterAdmin() || $user->isSecretary(), function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->whereNull('center_id')->orWhere('center_id', $user->center_id);
                });
            })
            ->with([
                'participants.user.center',
                'latestMessage.attachments',
                'center',
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

        $data = $conversations->map(function ($conv) use ($user) {
            $other = $conv->getOtherParticipant($user);
            $unreadCount = $conv->unreadCountFor($user);
            $latest = $conv->latestMessage;

            $roleLabel = match ($other?->role) {
                User::ROLE_SUPER_ADMIN => __('Super Admin'),
                User::ROLE_CENTER_ADMIN => __('Admin Centre'),
                User::ROLE_TEACHER => __('Professeur'),
                default => $other?->role ?? __('Utilisateur'),
            };

            return [
                'id' => $conv->id,
                'other_user' => [
                    'id' => $other?->id,
                    'name' => $other?->name ?? __('Utilisateur'),
                    'role' => $roleLabel,
                    'role_code' => $other?->role,
                    'center_name' => $other?->center?->name ?? ($conv->center?->name ?? __('Direction')),
                    'email' => $other?->email,
                ],
                'last_message' => $latest ? [
                    'content' => $latest->content ?: ($latest->attachments->count() ? '📎 ' . __('Fichier joint') : ''),
                    'time' => $latest->created_at->locale(app()->getLocale())->diffForHumans(null, true),
                    'created_at' => $latest->created_at->toIso8601String(),
                    'is_mine' => $latest->sender_id === $user->id,
                ] : null,
                'unread_count' => $unreadCount,
                'updated_at' => $conv->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'conversations' => $data,
            'total_unread' => $user->totalUnreadMessagesCount(),
        ]);
    }

    /**
     * Démarrer ou ouvrir une conversation directe avec un utilisateur cible.
     */
    public function startDirect(Request $request, User $target): JsonResponse
    {
        $currentUser = $request->user();

        // Vérification stricte des autorisations d'échange
        if (! $this->canCommunicate($currentUser, $target)) {
            return response()->json([
                'error' => __('Vous n\'êtes pas autorisé à initier une communication avec cet utilisateur.'),
            ], 403);
        }

        $conversation = Conversation::findOrCreateDirect($currentUser, $target);

        return response()->json([
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Récupérer les messages d'une conversation.
     */
    public function getMessages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Contrôle d'accès : l'utilisateur doit être participant
        if (! $conversation->isParticipant($user)) {
            abort(403, __('Accès interdit à cette conversation.'));
        }

        // Isolation pour l'admin de centre ou secrétaire : ne peut pas voir les conversations privées d'un autre centre
        if (($user->isCenterAdmin() || $user->isSecretary()) && $conversation->center_id && $conversation->center_id !== $user->center_id) {
            abort(403, __('Accès interdit aux conversations d\'un autre centre.'));
        }

        // Marquer la conversation comme lue pour cet utilisateur
        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        $messages = $conversation->messages()
            ->with(['sender.center', 'attachments'])
            ->get();

        $other = $conversation->getOtherParticipant($user);

        $roleLabel = match ($other?->role) {
            User::ROLE_SUPER_ADMIN => __('Super Admin'),
            User::ROLE_CENTER_ADMIN => __('Admin Centre'),
            User::ROLE_TEACHER => __('Professeur'),
            default => $other?->role ?? __('Utilisateur'),
        };

        // La messagerie est bidirectionnelle : les professeurs peuvent répondre
        // aux administrateurs dans les conversations existantes.

        $formatted = $messages->map(function ($msg) use ($user) {
            return [
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'sender_name' => $msg->sender->name,
                'sender_role' => match ($msg->sender->role) {
                    User::ROLE_SUPER_ADMIN => __('Super Admin'),
                    User::ROLE_CENTER_ADMIN => __('Admin Centre'),
                    User::ROLE_TEACHER => __('Professeur'),
                    default => $msg->sender->role,
                },
                'sender_center' => $msg->sender->center?->name ?? __('Direction Générale'),
                'is_mine' => $msg->sender_id === $user->id,
                'content' => $msg->content,
                'time' => $msg->created_at->format('H:i'),
                'date' => $msg->created_at->locale(app()->getLocale())->isoFormat('D MMMM YYYY'),
                'created_at' => $msg->created_at->toIso8601String(),
                'attachments' => $msg->attachments->map(function ($att) {
                    return [
                        'id' => $att->id,
                        'name' => $att->original_name,
                        'size' => $att->formatted_size,
                        'is_image' => $att->isImage(),
                        'url' => route('messages.attachment', $att->id),
                    ];
                }),
            ];
        });

        return response()->json([
            'conversation_id' => $conversation->id,
            'can_reply' => true,
            'other_user' => [
                'id' => $other?->id,
                'name' => $other?->name ?? __('Utilisateur'),
                'role' => $roleLabel,
                'role_code' => $other?->role,
                'center_name' => $other?->center?->name ?? __('Direction Générale'),
                'email' => $other?->email,
            ],
            'messages' => $formatted,
            'total_unread' => $user->totalUnreadMessagesCount(),
        ]);
    }

    /**
     * Envoyer un message dans une conversation.
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // 1. Vérification que l'utilisateur participe à la conversation
        if (! $conversation->isParticipant($user)) {
            abort(403, __('Accès interdit à cette conversation.'));
        }

        // 2. Vérification de la cible dans la matrice
        $target = $conversation->getOtherParticipant($user);
        if ($target && ! $this->canCommunicate($user, $target)) {
            abort(403, __('Communication non autorisée avec ce destinataire.'));
        }

        // 4. Isolation pour Admin de Centre / Secrétaire
        if (($user->isCenterAdmin() || $user->isSecretary()) && $conversation->center_id && $conversation->center_id !== $user->center_id) {
            abort(403, __('Accès interdit aux conversations d\'un autre centre.'));
        }

        $request->validate([
            'content' => 'nullable|string|max:5000',
            'file'    => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp', // 10MB
        ]);

        if (empty($request->input('content')) && ! $request->hasFile('file')) {
            return response()->json(['error' => __('Le message ou un fichier est requis.')], 422);
        }

        DB::beginTransaction();
        try {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $user->id,
                'content'         => $request->input('content'),
            ]);

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('messages/' . $conversation->id, 'local');

                MessageAttachment::create([
                    'message_id'    => $message->id,
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getClientMimeType(),
                    'file_size'     => $file->getSize(),
                ]);
            }

            // Met à jour l'horodatage de la conversation
            $conversation->update(['last_message_at' => now()]);

            // Marque comme lu pour l'expéditeur
            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);

            // Notification pour le destinataire
            if ($target) {
                $this->notifyRecipient($target, $user, $message, $conversation);
            }

            DB::commit();

            $message->load('attachments', 'sender');

            return response()->json([
                'success' => true,
                'message' => [
                    'id'          => $message->id,
                    'sender_id'   => $message->sender_id,
                    'sender_name' => $message->sender->name,
                    'is_mine'     => true,
                    'content'     => $message->content,
                    'time'        => $message->created_at->format('H:i'),
                    'date'        => $message->created_at->locale(app()->getLocale())->isoFormat('D MMMM YYYY'),
                    'created_at'  => $message->created_at->toIso8601String(),
                    'attachments' => $message->attachments->map(function ($att) {
                        return [
                            'id'       => $att->id,
                            'name'     => $att->original_name,
                            'size'     => $att->formatted_size,
                            'is_image' => $att->isImage(),
                            'url'      => route('messages.attachment', $att->id),
                        ];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => __('Erreur lors de l\'envoi du message: :message', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Nombre total de messages non lus pour l'utilisateur connecté.
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->totalUnreadMessagesCount(),
        ]);
    }

    /**
     * Téléchargement sécurisé d'une pièce jointe.
     */
    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        $user = $request->user();
        $conversation = $attachment->message->conversation;

        if (! $conversation->isParticipant($user)) {
            abort(403, __('Vous n\'êtes pas autorisé à accéder à cette pièce jointe.'));
        }

        if (($user->isCenterAdmin() || $user->isSecretary()) && $conversation->center_id && $conversation->center_id !== $user->center_id) {
            abort(403, __('Accès non autorisé.'));
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->file_path)) {
            abort(404, __('Fichier introuvable.'));
        }

        return $disk->download($attachment->file_path, $attachment->original_name);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // MÉTHODES PRIVÉES DE CONTRÔLE D'ACCÈS ET NOTIFICATIONS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Vérifie si l'expéditeur a le droit d'envoyer un message au destinataire
     * selon la matrice d'autorisations stricte.
     */
    public function canCommunicate(User $sender, User $target): bool
    {
        // Règle 0 : Pas de communication avec soi-même
        if ($sender->id === $target->id) {
            return false;
        }

        // Règle 1 : Les étudiants et comptes inactifs n'ont aucun accès à la messagerie
        if ($sender->isStudent() || $target->isStudent() || ! $sender->isActive() || ! $target->isActive()) {
            return false;
        }

        // Règle 2 : Super Admin peut communiquer avec :
        // - Tous les Admins Centre
        // - Toutes les Secrétaires
        // - Tous les Professeurs
        if ($sender->isSuperAdmin()) {
            return $target->isCenterAdmin() || $target->isSecretary() || $target->isTeacher();
        }

        // Règle 3 : Admin de Centre peut communiquer avec :
        // - Le Super Admin
        // - Tous les Professeurs de son propre centre
        // - Toutes les Secrétaires de son propre centre
        // - Ne peut pas communiquer avec les utilisateurs des autres centres, sauf le Super Admin
        // - Ne peut pas communiquer avec les autres Admins de Centre
        if ($sender->isCenterAdmin()) {
            if ($target->isSuperAdmin()) {
                return true;
            }

            if ($target->isTeacher()) {
                $targetCenters = $target->centerIds();
                return in_array((int) $sender->center_id, $targetCenters, true);
            }

            if ((int) $target->center_id !== (int) $sender->center_id) {
                return false;
            }

            return $target->isSecretary();
        }

        // Règle 4 : Secrétaire peut communiquer avec :
        // - Le Super Admin
        // - L'Admin de son propre centre
        // - Tous les Professeurs de son propre centre
        // - Ne peut pas communiquer avec les utilisateurs des autres centres
        // - Ne peut pas communiquer avec d'autres secrétaires
        if ($sender->isSecretary()) {
            if ($target->isSuperAdmin()) {
                return true;
            }

            if ($target->isTeacher()) {
                $targetCenters = $target->centerIds();
                return in_array((int) $sender->center_id, $targetCenters, true);
            }

            if ((int) $target->center_id !== (int) $sender->center_id) {
                return false;
            }

            return $target->isCenterAdmin();
        }

        // Règle 5 : Professeur peut communiquer avec :
        // - Le Super Admin
        // - L'Admin de son propre centre
        // - Les Secrétaires de son propre centre
        // - Ne peut pas communiquer avec les autres professeurs ni les utilisateurs des autres centres
        if ($sender->isTeacher()) {
            if ($target->isSuperAdmin()) {
                return true;
            }

            // Vérifie l'appartenance au centre du professeur (centre direct ou classes affectées)
            $teacherCenters = $sender->centerIds();
            if (! in_array((int) $target->center_id, $teacherCenters, true)) {
                return false;
            }

            return $target->isCenterAdmin() || $target->isSecretary();
        }

        return false;
    }

    /**
     * Récupère la liste des contacts autorisés à l'initiation d'une conversation.
     */
    private function getAllowedContacts(User $user)
    {
        if ($user->isSuperAdmin()) {
            // Super Admin : tous les admins de centre, secrétaires + tous les professeurs actifs
            return User::whereIn('role', [User::ROLE_CENTER_ADMIN, User::ROLE_SECRETARY, User::ROLE_TEACHER])
                ->where('status', User::STATUS_ACTIVE)
                ->with(['center', 'teacher.classes'])
                ->orderBy('role')
                ->orderBy('name')
                ->get();
        }

        if ($user->isCenterAdmin()) {
            // Admin de centre : Super Admin + Secrétaires de son centre + Professeurs de son centre
            $superAdmins = User::where('role', User::ROLE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            $secretaries = User::where('role', User::ROLE_SECRETARY)
                ->where('status', User::STATUS_ACTIVE)
                ->where('center_id', $user->center_id)
                ->with('center')
                ->get();

            $teachers = User::where('role', User::ROLE_TEACHER)
                ->where('status', User::STATUS_ACTIVE)
                ->where(function ($q) use ($user) {
                    $q->where('center_id', $user->center_id)
                      ->orWhereHas('teacher.classes', function ($c) use ($user) {
                          $c->where('center_id', $user->center_id);
                      });
                })
                ->with(['center', 'teacher.classes'])
                ->get();

            return $superAdmins->concat($secretaries)->concat($teachers)->sortBy('name')->values();
        }

        if ($user->isSecretary()) {
            // Secrétaire : Super Admin + Admin de son centre + Professeurs de son centre
            $superAdmins = User::where('role', User::ROLE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            $centerAdmins = User::where('role', User::ROLE_CENTER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->where('center_id', $user->center_id)
                ->with('center')
                ->get();

            $teachers = User::where('role', User::ROLE_TEACHER)
                ->where('status', User::STATUS_ACTIVE)
                ->where(function ($q) use ($user) {
                    $q->where('center_id', $user->center_id)
                      ->orWhereHas('teacher.classes', function ($c) use ($user) {
                          $c->where('center_id', $user->center_id);
                      });
                })
                ->with(['center', 'teacher.classes'])
                ->get();

            return $superAdmins->concat($centerAdmins)->concat($teachers)->sortBy('name')->values();
        }

        if ($user->isTeacher()) {
            // Professeur : Super Admin + Admin de son centre + Secrétaires de son centre
            $superAdmins = User::where('role', User::ROLE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            $centerIds = $user->centerIds();

            $admins = User::whereIn('role', [User::ROLE_CENTER_ADMIN, User::ROLE_SECRETARY])
                ->where('status', User::STATUS_ACTIVE)
                ->whereIn('center_id', $centerIds)
                ->with('center')
                ->get();

            return $superAdmins->concat($admins)->sortBy('name')->values();
        }

        return collect();
    }

    /**
     * Envoie une notification système au destinataire du message.
     */
    private function notifyRecipient(User $target, User $sender, Message $message, Conversation $conversation): void
    {
        $link = $target->isTeacher()
            ? route('teacher.messages', ['conversation_id' => $conversation->id])
            : route('messages.index', ['conversation_id' => $conversation->id]);

        $preview = Str::limit($message->content ?: __('Fichier joint'), 80);

        AppNotification::create([
            'user_id'      => $target->id,
            'center_id'    => $target->center_id ?? $sender->center_id,
            'type'         => 'message',
            'title'        => __('Nouveau message de :name', ['name' => $sender->name]),
            'message'      => $preview,
            'icon'         => 'bi-chat-dots',
            'link'         => $link,
            'related_type' => Message::class,
            'related_id'   => $message->id,
        ]);
    }
}
