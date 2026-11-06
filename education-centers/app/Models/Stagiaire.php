<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stagiaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'center_id',
        'created_by',
        'first_name',
        'last_name',
        'date_of_birth',
        'place_of_birth',
        'gender',
        'nationality',
        'address',
        'phone',
        'whatsapp',
        'emergency_contact_phone',
        'profile_photo_path',
        'email',
        'company_name',
        'company_phone',
        'company_location',
        'cin_number',
        'cin_issued_date',
        'cin_issued_place',
        'cin_recto_path',
        'cin_verso_path',
        'stage_start_date',
        'stage_end_date',
        'stage_type',
        'stage_price',
        'currency',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'cin_issued_date' => 'date',
            'stage_start_date' => 'date',
            'stage_end_date' => 'date',
            'stage_price' => 'decimal:2',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function diplomas(): HasMany
    {
        return $this->hasMany(StagiaireDiploma::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StagiaireDocument::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFormattedStagePriceAttribute(): string
    {
        return format_price($this->stage_price ?? 0, $this->currency ?? default_currency());
    }

    public function isComplete(): bool
    {
        return ! empty($this->cin_number)
            && ! empty($this->cin_recto_path)
            && ! empty($this->stage_start_date)
            && ! empty($this->stage_end_date)
            && $this->stage_price !== null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'nouveau' => __('Nouveau'),
            'en_cours' => __('Stage en cours'),
            'termine' => __('Terminé'),
            'suspendu' => __('Suspendu'),
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'en_cours' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
            'nouveau' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'termine' => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
            'suspendu' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
            'annule' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function getBirthDateAttribute()
    {
        return $this->date_of_birth;
    }

    public function getBirthPlaceAttribute(): ?string
    {
        return $this->place_of_birth;
    }

    public function getCinDeliveredAtAttribute()
    {
        return $this->cin_issued_date;
    }

    public function getCinDeliveredPlaceAttribute(): ?string
    {
        return $this->cin_issued_place;
    }
}
