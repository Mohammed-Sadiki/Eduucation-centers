<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;
    use \App\Concerns\HasDomainScope;
    use \App\Models\Concerns\HasContextScope;

    /**
     * Le domaine d'une inscription est hérité de sa formation.
     */
    protected function domainPaths(): array
    {
        return ['formation'];
    }

    protected $table = 'enrollments';

    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'center_id',
        'student_id',
        'formation_id',
        'class_id',
        'school_year',
        'official_price',
        'final_price',
        'registration_number',
        'application_date',
        'status',
        'notes',
        'decision_date',
        'decision_notes',
        'accepted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'decision_date' => 'date',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'official_price' => 'decimal:2',
            'final_price' => 'decimal:2',
        ];
    }

    /**
     * Tarif officiel de référence de l'inscription.
     *
     * Priorité à la trace enregistrée au moment de l'inscription (`official_price`) ;
     * à défaut (inscriptions antérieures, ou création « dossier seul » sans prix),
     * on retombe sur le tarif actuel du catalogue via FinanceService. Ne renvoie
     * JAMAIS null : c'est la base de comparaison de la réduction.
     */
    public function officialPriceReference(): float
    {
        if ($this->official_price !== null) {
            return (float) $this->official_price;
        }

        // Tracé absent (ancienne inscription) : référence = tarif catalogue. La
        // relation est déjà chargée dans les boucles financières (eager loading) ;
        // sinon Eloquent la charge à la demande.
        $formation = $this->formation;

        return $formation ? \App\Services\FinanceService::getFormationAnnualPrice($formation) : 0.0;
    }

    /**
     * Montant réellement dû pour CETTE inscription : le prix final accordé s'il a
     * été saisi, sinon le tarif officiel (aucune réduction). C'est la valeur que
     * tous les calculs financiers doivent utiliser — jamais le catalogue brut.
     */
    public function getPayableAmount(): float
    {
        if ($this->final_price !== null) {
            return max(0.0, (float) $this->final_price);
        }

        // Inscription sans prix final explicite (création « dossier seul » ou
        // données antérieures) : référence = tarif officiel.
        return $this->officialPriceReference();
    }

    /**
     * Réduction accordée (tarif officiel − prix final), jamais négative.
     */
    public function getDiscountAmount(): float
    {
        if ($this->final_price === null) {
            return 0.0;
        }

        return max(0.0, $this->officialPriceReference() - (float) $this->final_price);
    }

    /**
     * Une réduction a-t-elle été appliquée à cette inscription ?
     */
    public function hasDiscount(): bool
    {
        return $this->getDiscountAmount() > 0.01;
    }

    /**
     * Versements réellement encaissés pour CETTE inscription : ceux qui y sont
     * rattachés, ainsi que ceux enregistrés sur la même formation sans
     * identifiant d'inscription (écritures antérieures). En lecture seule : la
     * mise à jour d'un dossier étudiant ne modifie jamais ces versements.
     */
    public function paidPayments(): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::query()
            ->where('center_id', $this->center_id)
            ->where('student_id', $this->student_id)
            ->where('status', 'paid')
            ->where(function ($q): void {
                $q->where('enrollment_id', $this->id)
                    ->orWhere(function ($q2): void {
                        $q2->whereNull('enrollment_id')
                            ->where('formation_id', $this->formation_id);
                    });
            })
            ->orderByDesc('payment_date')
            ->get();
    }

    /**
     * Total déjà encaissé (solde conservé tel quel).
     */
    public function paidAmount(): float
    {
        return (float) $this->paidPayments()->sum('amount');
    }

    /**
     * Reste réellement dû après les versements déjà enregistrés.
     */
    public function getRemainingAmount(): float
    {
        return max(0.0, $this->getPayableAmount() - $this->paidAmount());
    }

    /**
     * Relation : Une inscription appartient à un centre.
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Relation : Une inscription appartient à un étudiant.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Relation : Une inscription concerne une formation.
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * Relation : Une inscription peut être affectée à une classe.
     */
    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    /**
     * Versements rattachés à cette inscription (module Finance).
     */
    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Factures / échéances rattachées à cette inscription (module Finance).
     */
    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // --- Helpers de statut ---

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // --- Accessors utiles ---

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('En attente'),
            self::STATUS_UNDER_REVIEW => __('En examen'),
            self::STATUS_ACCEPTED => __('Acceptée'),
            self::STATUS_REJECTED => __('Refusée'),
            self::STATUS_CANCELLED => __('Annulée'),
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            self::STATUS_UNDER_REVIEW => 'bg-info-subtle text-info-emphasis border border-info-subtle',
            self::STATUS_ACCEPTED => 'bg-success-subtle text-success border border-success-subtle',
            self::STATUS_REJECTED => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::STATUS_CANCELLED => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            default => 'bg-light text-dark border',
        };
    }

    public function getFormattedApplicationDateAttribute(): string
    {
        return $this->application_date ? $this->application_date->format('d/m/Y') : '';
    }

    /**
     * Génère un numéro d'inscription unique et lisible par centre.
     * Format : {CENTER_CODE}-INS-{000001}
     * Exemples : BER-INS-000001, MUN-INS-000001
     */
    public static function generateRegistrationNumber(Center $center): string
    {
        $code = strtoupper($center->code);
        $prefix = "{$code}-INS-";

        // Trouve le numéro séquentiel le plus élevé pour ce préfixe
        $lastEnrollment = static::where('registration_number', 'LIKE', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastEnrollment && preg_match('/'.preg_quote($prefix, '/').'(\d+)/', $lastEnrollment->registration_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
            $exists = static::where('registration_number', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }
}
