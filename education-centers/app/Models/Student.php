<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    use HasFactory;
    use \App\Concerns\HasDomainScope;
    use \App\Models\Concerns\HasContextScope;

    /**
     * Un étudiant peut suivre plusieurs domaines via ses inscriptions.
     */
    protected function domainPaths(): array
    {
        return ['enrollments.formation'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'center_id',
        'student_number',
        'first_name',
        'last_name',
        'date_of_birth',
        'place_of_birth',
        'gender',
        'nationality',
        'address',
        'postal_code',
        'city',
        'country',
        'phone',
        'whatsapp',
        'profile_photo_path',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'notes',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Get the user account for this student.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the educational center for this student.
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Inscriptions de l'étudiant aux formations/classes.
     */
    public function enrollments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Slugs des domaines suivis par l'étudiant (via ses inscriptions).
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function domainSlugs(): \Illuminate\Support\Collection
    {
        return $this->enrollments()
            ->with('formation:id,domain')
            ->get()
            ->pluck('formation.domain')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Domaines (modèles) suivis par l'étudiant.
     */
    public function domains(): \Illuminate\Support\Collection
    {
        return Domain::whereIn('slug', $this->domainSlugs())->ordered()->get();
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Attendance::class); }
    public function grades(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Grade::class); }
    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Payment::class); }
    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Invoice::class); }
    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Document::class); }
    public function certificates(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Certificate::class); }

    /**
     * Check if the student is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the student is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Accessor for full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Disques où une photo de profil peut se trouver, par ordre de préférence.
     * « private » est le disque d'écriture actuel (donnée personnelle) ; « public »
     * reste en lecture pour les dossiers enregistrés avant cette règle.
     *
     * @var list<string>
     */
    private const PHOTO_DISKS = ['private', 'public'];

    /**
     * Nom du disque qui contient réellement la photo, ou null si aucune photo
     * valide n'existe (chemin null ou fichier absent du disque).
     */
    public function profilePhotoDisk(): ?string
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        foreach (self::PHOTO_DISKS as $disk) {
            if (Storage::disk($disk)->exists($this->profile_photo_path)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * Une photo affichable existe-t-elle vraiment sur le disque ?
     */
    public function hasProfilePhoto(): bool
    {
        return $this->profilePhotoDisk() !== null;
    }

    /**
     * URL du fichier stocké sur le disque public (jamais le cas des photos
     * privées), ou URL sécurisée de la route dédiée pour le stockage privé.
     *
     * L'ancienne écriture asset('storage/' . $path) produisait une URL du disque
     * public alors que la photo est enregistrée sur le disque privé : l'image
     * était donc cassée. La route students.profile-photo sert le fichier après
     * contrôle d'autorisation, exactement comme pour les stagiaires.
     */
    public function profilePhotoUrl(): ?string
    {
        $disk = $this->profilePhotoDisk();

        if ($disk === null) {
            return null;
        }

        return $disk === 'public'
            ? Storage::disk('public')->url($this->profile_photo_path)
            : route('students.profile-photo', $this);
    }

    /**
     * Generate a unique student number based on the center code.
     * Format: {CENTER_CODE}-{000001}
     */
    public static function generateStudentNumber(Center $center): string
    {
        $code = strtoupper($center->code);
        $count = self::where('center_id', $center->id)->count() + 1;
        $studentNumber = sprintf('%s-%06d', $code, $count);

        // Guarantee uniqueness in case of deletions
        while (self::where('student_number', $studentNumber)->exists()) {
            $count++;
            $studentNumber = sprintf('%s-%06d', $code, $count);
        }

        return $studentNumber;
    }
}
