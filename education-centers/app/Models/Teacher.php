<?php

namespace App\Models;

use App\Concerns\HasDomainScope;
use App\Models\Concerns\HasContextScope;
use App\Support\AcademicPerimeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Teacher extends Model
{
    use HasContextScope;
    use HasDomainScope;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'center_id',
        'domain',
        'formation_id',
        'teacher_number',
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
        'specialization',
        'qualification',
        'monthly_salary',
        'hire_date',
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
            'hire_date' => 'date',
            'monthly_salary' => 'decimal:2',
        ];
    }

    /**
     * Get the user account for this teacher.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the educational center for this teacher.
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Formation principale enseignée par ce professeur.
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * Classes auxquelles ce professeur est affecté.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'class_teacher', 'teacher_id', 'class_id')
            ->withTimestamps();
    }

    /**
     * Relation : Séances d'emploi du temps du professeur.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Fiches de salaire du professeur (module Finance existant).
     */
    public function salaries(): HasMany
    {
        return $this->hasMany(TeacherSalary::class);
    }

    /**
     * Relation vers les modèles Domain via le pivot teacher_domains.
     */
    public function teachingDomains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'teacher_domains', 'teacher_id', 'domain_slug', 'id', 'slug')
            ->withTimestamps();
    }

    /**
     * Synchronise les domaines d'enseignement d'un professeur.
     * Met également à jour la colonne `domain` (premier domaine ou null) pour rétro-compatibilité.
     *
     * @param  array<int, string>  $slugs
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
    }

    /**
     * Un professeur peut enseigner dans plusieurs domaines :
     * son domaine principal (colonne `domain`), les domaines déclarés via `teacher_domains`
     * OU tout domaine de classe auquel il est affecté via le pivot `class_teacher`.
     */
    public function scopeForDomain(Builder $query, ?string $slug): Builder
    {
        if (! $slug || $slug === 'all') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($slug) {
            $q->where('domain', $slug)
                ->orWhereHas('teachingDomains', fn (Builder $d) => $d->where('domains.slug', $slug))
                ->orWhereHas('classes.formation', fn (Builder $f) => $f->where('domain', $slug));
        });
    }

    /**
     * Même règle que `forDomain`, mais résolue par l'autorité unique du périmètre
     * {@see AcademicPerimeter} : un professeur relève d'une formation
     * générale quand il y est rattaché (formation principale), quand il enseigne
     * dans une classe de cette formation (alias historiques inclus) ou quand il
     * déclare le domaine de cette formation (colonne propre ou pivot).
     *
     * Le trait `HasDomainScope` ne peut pas deviner ces chemins ici (la table
     * `teachers` porte sa propre colonne `domain` et un rattachement multiple) :
     * cette surcharge est donc obligatoire pour que la liste des professeurs et
     * les espaces de formation utilisent exactement le même filtre.
     *
     * @param  list<int>  $formationIds
     */
    public function scopeWithinGeneralFormations(Builder $query, array $formationIds, bool $keepUnattached = false): Builder
    {
        $slugs = Formation::query()
            ->whereIn('id', $formationIds)
            ->whereNotNull('domain')
            ->distinct()
            ->pluck('domain')
            ->all();

        return $query->where(function (Builder $q) use ($formationIds, $slugs, $keepUnattached) {
            $q->whereIn('formation_id', $formationIds)
                ->orWhereHas('classes.formation', fn (Builder $f) => $f->whereIn('formations.id', $formationIds));

            if ($slugs !== []) {
                $q->orWhereIn('domain', $slugs)
                    ->orWhereHas('teachingDomains', fn (Builder $d) => $d->whereIn('domains.slug', $slugs));
            }

            if ($keepUnattached) {
                // Un professeur qui n'est rattaché à AUCUN périmètre (ni formation
                // principale, ni classe, ni domaine déclaré) n'est pas un reliquat
                // de l'ancienne hiérarchie : il reste visible dans la liste.
                $q->orWhere(function (Builder $u) {
                    $u->whereNull('formation_id')
                        ->where(fn (Builder $d) => $d->whereNull('domain')->orWhere('domain', ''))
                        ->doesntHave('classes')
                        ->doesntHave('teachingDomains');
                });
            }
        });
    }

    /**
     * Slugs des domaines d'un professeur (pivot teacher_domains + domaine principal + classes affectées).
     *
     * @return Collection<int, string>
     */
    public function domainSlugs(): Collection
    {
        $fromPivot = DB::table('teacher_domains')
            ->where('teacher_id', $this->id)
            ->pluck('domain_slug');

        $fromClasses = $this->classes()
            ->with('formation:id,domain')
            ->get()
            ->pluck('formation.domain');

        return $fromPivot
            ->concat($fromClasses)
            ->push($this->domain)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Domaines (modèles) d'un professeur.
     */
    public function domains(): Collection
    {
        return Domain::whereIn('slug', $this->domainSlugs())->ordered()->get();
    }

    /**
     * Check if the teacher is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the teacher is inactive.
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
     * Nom de la formation ou du domaine enseigné par ce professeur.
     */
    public function getFormationNameAttribute(): ?string
    {
        if ($this->relationLoaded('formation') && $this->formation) {
            return $this->formation->translated('name') ?? $this->formation->name;
        }

        if ($this->formation_id) {
            $f = $this->formation;
            if ($f) {
                return $f->translated('name') ?? $f->name;
            }
        }

        if ($this->domain) {
            $labels = \App\Support\DomainRegistry::labels();
            return $labels[$this->domain] ?? ucfirst(str_replace('-', ' ', $this->domain));
        }

        return null;
    }

    /**
     * Nom complet du professeur suivi de sa formation entre parenthèses.
     * Exemple : "Amina Bennani (Santé)"
     */
    public function getFullNameWithFormationAttribute(): string
    {
        $fullName = $this->full_name;
        $formationName = $this->formation_name;

        return $formationName ? "{$fullName} ({$formationName})" : $fullName;
    }

    /**
     * Generate a unique teacher number based on center code.
     * Format: {CENTER_CODE}-T-{000001}
     * Examples: BER-T-000001, MUN-T-000001
     */
    public static function generateTeacherNumber(Center $center): string
    {
        $code = strtoupper($center->code);
        $count = self::where('center_id', $center->id)->count() + 1;
        $teacherNumber = sprintf('%s-T-%06d', $code, $count);

        while (self::where('teacher_number', $teacherNumber)->exists()) {
            $count++;
            $teacherNumber = sprintf('%s-T-%06d', $code, $count);
        }

        return $teacherNumber;
    }
}
