<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Center extends Model
{
    use HasFactory;
    use HasTranslations;

    /**
     * Fields whose displayed value can be localized.
     *
     * @var list<string>
     */
    protected array $translatable = ['name', 'address'];

    /**
     * Champs autorisés à la masse assignment.
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'postal_code',
        'country',
        'phone',
        'email',
        'logo',
        'status',
    ];

    /**
     * Casts automatiques des attributs.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Valeurs par défaut des attributs.
     */
    protected $attributes = [
        'country' => 'Germany',
        'status' => 'active',
    ];

    // ──────────────────────────────────────────────────────────────────────
    //  Relations
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Un centre possède plusieurs utilisateurs.
     * Les utilisateurs sans center_id (Super Admin) ne sont pas inclus ici.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Un centre possède plusieurs dossiers étudiants.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Un centre possède plusieurs dossiers professeurs.
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    /**
     * Un centre propose plusieurs formations.
     */
    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }

    /**
     * Un centre propose plusieurs formations générales du fonctionnement courant :
     * mêmes règles que {@see Formation::scopeCurrent()} (générales
     * actives + héritées sans équivalent général), restreintes à ce centre.
     * C'est la relation à utiliser pour compter ou afficher « les formations »
     * d'un centre dans les tableaux de bord, catalogues et statistiques ;
     * `formations()` reste la relation brute (hiérarchie historique complète).
     */
    public function generalFormations(): HasMany
    {
        return $this->hasMany(Formation::class)->where('status', 'active')->current();
    }

    /**
     * Un centre possède plusieurs classes.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    /**
     * Un centre dispose de plusieurs salles de formation.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Un centre possède plusieurs séances d'emploi du temps.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Un centre gère plusieurs inscriptions.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    /*
     * Les relations suivantes seront ajoutées dans les prochaines étapes :
     *
     * public function students(): HasMany  → Étape Students
     * public function teachers(): HasMany  → Étape Teachers
     * public function formations(): HasMany → Étape Formations
     * public function classes(): HasMany   → Étape Classes
     * public function rooms(): HasMany     → Étape Rooms
     */

    // ──────────────────────────────────────────────────────────────────────
    //  Accesseurs / Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Retourne true si le centre est actif.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Retourne l'URL du logo ou null si aucun logo défini.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo) {
            return asset('storage/'.$this->logo);
        }

        return null;
    }

    /**
     * Vérifie si le centre possède des utilisateurs liés (hors Super Admin).
     * Utile avant suppression.
     */
    public function hasLinkedUsers(): bool
    {
        return $this->users()->exists();
    }
}
