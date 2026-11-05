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
     * Espace "Messagerie" dédié aux professeurs (lectures + réponses aux admins).
     */
    public function teacherIndex(Request $request)
    {
        $user = $request->user();

        if (! $user->isTeacher()) {
            abort(403, __('Accès réservé aux professeurs.'));
        }

        $activeConversationId = $request->query('conversation_id');

        return view('teacher.messages', compact('user', 'activeConversationId'));
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

        // Un professeur ne peut pas initier une conversation : il répond uniquement
        // dans les conversations ouvertes par l'administration.
        if ($currentUser->isTeacher()) {
            return response()->json([
                'error' => __('Les professeurs répondent uniquement aux messages de l\'administration.'),
            ], 403);
        }

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

        // 1. Un professeur ne peut que répondre dans une conversation existante ouverte
        // par l'administration (jamais en initier une — voir canCommunicate).
        if ($user->isTeacher() && $conversation->type !== 'direct') {
            abort(403, __('Les professeurs ne peuvent répondre que dans les conversations de l\'administration.'));
        }

        // 2. Vérification que l'utilisateur participe à la conversation
        if (! $conversation->isParticipant($user)) {
            abort(403, __('Accès interdit à cette conversation.'));
        }

        // 3. Vérification de la cible dans la matrice
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
        // Règle 1 : Un professeur ne peut communiquer qu'avec l'administration
        // (Super Admin ou Admin du centre auquel il est rattaché) — jamais avec
        // un autre professeur, et jamais pour initier une conversation.
        if ($sender->isTeacher()) {
            if ($target->isSuperAdmin()) {
                return true;
            }

            if ($target->isCenterAdmin() || $target->isSecretary()) {
                return in_array((int) $target->center_id, $sender->centerIds(), true);
            }

            return false;
        }

        // Règle 2 : Un étudiant n'a aucun accès à cette messagerie
        if ($sender->isStudent() || $target->isStudent()) {
            return false;
        }

        // Règle 3 : Super Admin peut envoyer à :
        // - Tous les Admins Centre
        // - Toutes les Secrétaires
        // - Tous les Professeurs
        if ($sender->isSuperAdmin()) {
            return $target->isCenterAdmin() || $target->isSecretary() || $target->isTeacher();
        }

        // Règle 4 : Admin de Centre / Secrétaire peut envoyer à :
        // - Le Super Admin
        // - Les Professeurs rattachés à son propre centre (centre direct ou via classes affectées)
        if ($sender->isCenterAdmin() || $sender->isSecretary()) {
            if ($target->isSuperAdmin()) {
                return true;
            }

            // Interdiction formelle de communiquer avec un autre centre
            if (($target->isCenterAdmin() || $target->isSecretary()) && (int) $target->center_id !== (int) $sender->center_id) {
                return false;
            }

            // Si le destinataire est un professeur, vérifier qu'il est rattaché au centre de cet admin
            if ($target->isTeacher()) {
                $targetCenterIds = $target->centerIds();
                return in_array((int) $sender->center_id, $targetCenterIds, true);
            }

            return (int) $target->center_id === (int) $sender->center_id;
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

        if ($user->isCenterAdmin() || $user->isSecretary()) {
            // Admin de centre / Secrétaire : Super Admin + Professeurs affectés à son centre
            $superAdmins = User::where('role', User::ROLE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            // Professeurs dont le centre principal est ce centre OU qui ont une classe dans ce centre
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

            return $superAdmins->concat($teachers)->sortBy('name')->values();
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
