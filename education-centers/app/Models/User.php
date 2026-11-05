<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Rôles disponibles.
     */
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_CENTER_ADMIN = 'center_admin';

    public const ROLE_SECRETARY = 'secretary';

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STUDENT = 'student';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_CENTER_ADMIN,
        self::ROLE_SECRETARY,
        self::ROLE_TEACHER,
        self::ROLE_STUDENT,
    ];

    /**
     * Statuts disponibles.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * Champs autorisés à la masse assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'center_id',
        'domain',
        'formation_id',
        'phone',
        'status',
        'locale',
    ];

    /**
     * Champs masqués dans la sérialisation.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts automatiques.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Règles de cohérence métier à la sauvegarde du modèle.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Par défaut, si aucun rôle n'est spécifié
            if (empty($user->role)) {
                $user->role = empty($user->center_id) ? self::ROLE_SUPER_ADMIN : self::ROLE_STUDENT;
            }

            // Un super_admin ne doit JAMAIS avoir de center_id
            if ($user->role === self::ROLE_SUPER_ADMIN) {
                $user->center_id = null;
            } else {
                // Pour tout utilisateur non super_admin (center_admin, teacher, student) : center_id est obligatoire
                if (empty($user->center_id)) {
                    throw new \InvalidArgumentException(
                        __('L\'utilisateur avec le rôle [:role] doit obligatoirement être rattaché à un centre (center_id requis).', ['role' => $user->role])
                    );
                }
            }
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Relations
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Un utilisateur appartient à un centre.
     * Retourne null pour le Super Admin (center_id = NULL).
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Un utilisateur étudiant possède un dossier étudiant (0 ou 1).
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Un utilisateur professeur possède un dossier professeur (0 ou 1).
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Formation principale enseignée par le professeur.
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * Domaines d'enseignement associés à l'utilisateur (professeur).
     */
    public function teachingDomains(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'user_domains', 'user_id', 'domain_slug', 'id', 'slug')
            ->withTimestamps();
    }

    /**
     * Synchronise les domaines d'enseignement d'un utilisateur / professeur.
     * Met également à jour le dossier Teacher associé s'il existe.
     *
     * @param array<int, string> $slugs
     */
    public function syncTeachingDomains(array $slugs): void
    {
        $validSlugs = Domain::whereIn('slug', $slugs)->pluck('slug')->toArray();
        $this->teachingDomains()->sync($validSlugs);

        $primary = $validSlugs[0] ?? null;
        if ($this->domain !== $primary) {
            $this->domain = $primary;
            $this->save();
        }

        // Si l'utilisateur possède déjà un dossier Teacher, on synchronise aussi Teacher
        if ($this->teacher) {
            $this->teacher->syncTeachingDomains($validSlugs);
        }
    }

    /**
     * Récupère la liste des slugs de domaines d'enseignement de cet utilisateur.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function domainSlugs(): \Illuminate\Support\Collection
    {
        $userSlugs = \Illuminate\Support\Facades\DB::table('user_domains')
            ->where('user_id', $this->id)
            ->pluck('domain_slug');

        if ($this->domain) {
            $userSlugs = $userSlugs->push($this->domain);
        }

        if ($this->formation && $this->formation->domain) {
            $userSlugs = $userSlugs->push($this->formation->domain);
        }

        if ($this->teacher) {
            $userSlugs = $userSlugs->concat($this->teacher->domainSlugs());
        }

        return $userSlugs->filter()->unique()->values();
    }

    /**
     * Modèles de domaines d'enseignement.
     */
    public function domains(): \Illuminate\Support\Collection
    {
        return Domain::whereIn('slug', $this->domainSlugs())->ordered()->get();
    }

    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function totalUnreadMessagesCount(): int
    {
        $participants = $this->conversationParticipants()->get();
        $count = 0;
        foreach ($participants as $participant) {
            $query = Message::where('conversation_id', $participant->conversation_id)
                ->where('sender_id', '!=', $this->id);
            if ($participant->last_read_at) {
                $query->where('created_at', '>', $participant->last_read_at);
            }
            $count += $query->count();
        }

        return $count;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Vérification des Rôles
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Détermine si l'utilisateur est un Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Détermine si l'utilisateur est un Admin de Centre (Responsable Centre).
     */
    public function isCenterAdmin(): bool
    {
        return $this->role === self::ROLE_CENTER_ADMIN;
    }

    /**
     * Détermine si l'utilisateur est un Responsable de Centre (alias de isCenterAdmin).
     */
    public function isCenterManager(): bool
    {
        return $this->isCenterAdmin();
    }

    /**
     * Détermine si l'utilisateur est une Secrétaire.
     */
    public function isSecretary(): bool
    {
        return $this->role === self::ROLE_SECRETARY;
    }

    /**
     * Détermine si l'utilisateur est un Professeur.
     */
    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    /**
     * Détermine si l'utilisateur est un Élève.
     */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    /**
     * Détermine si l'utilisateur a un rôle spécifique parmi une liste.
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return in_array($this->role, $roles, true);
    }

    /**
     * Détermine si l'utilisateur appartient à un centre spécifique.
     */
    public function belongsToCenter(int $centerId): bool
    {
        return $this->center_id === $centerId;
    }

    /**
     * Retourne tous les IDs de centre auxquels l'utilisateur est rattaché.
     * Pour un professeur, inclut son center_id principal ET les centres de ses classes (via class_teacher).
     *
     * @return array<int>
     */
    public function centerIds(): array
    {
        $ids = [];
        if ($this->center_id) {
            $ids[] = (int) $this->center_id;
        }

        if ($this->isTeacher() && $this->relationLoaded('teacher') ? $this->teacher : $this->teacher()->first()) {
            $teacher = $this->teacher;
            if ($teacher) {
                $classCenterIds = $teacher->classes()->pluck('center_id')->filter()->all();
                foreach ($classCenterIds as $cid) {
                    $ids[] = (int) $cid;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Détermine si le compte utilisateur est actif.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Détermine si le compte utilisateur est inactif.
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }
}
