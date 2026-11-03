<?php

namespace App\Models;

use App\Concerns\HasDomainScope;
use App\Concerns\HasTranslations;
use App\Models\Concerns\HasContextScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    use HasFactory;
    use HasTranslations;
    use HasDomainScope;
    use HasContextScope;

    /**
     * Nombre maximal de classes autorisées pour une formation générale officielle.
     */
    public const MAX_CLASSES = 2;

    /**
     * Fields whose displayed value can be localized.
     *
     * @var list<string>
     */
    protected array $translatable = ['name', 'description'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'center_id',
        'domain',
        'name',
        'code',
        'description',
        'duration_hours',
        'duration_weeks',
        'price',
        'annual_price',
        'currency',
        'status',
        'is_official',
        'official_code',
        'teacher_id',
        'merged_into_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
            'duration_weeks' => 'integer',
            'price' => 'decimal:2',
            'annual_price' => 'decimal:2',
            'is_official' => 'boolean',
        ];
    }

    /**
     * Relation : Une formation appartient à un centre.
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Relation : domaine de rattachement (clé personnalisée slug <-> colonne domain).
     * Nommée domainModel() car un attribut `domain` (colonne string) existe déjà :
     * $formation->domain renvoie le slug, $formation->domainModel renvoie le Domain.
     */
    public function domainModel(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain', 'slug');
    }

    /**
     * Relation : Une formation possède plusieurs classes.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    /**
     * Relation : Inscriptions liées à cette formation.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Relation : professeur responsable de la formation générale officielle.
     * Un seul responsable maximum (colonne unique) ; null = non assigné.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    /**
     * Relation : formation officielle dans laquelle cette formation héritée a été
     * fusionnée (null tant que la fusion n'est pas validée/exécutée).
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * Relation : correspondance historique (ancienne → officielle).
     */
    public function lineage(): HasMany
    {
        return $this->hasMany(FormationLineage::class, 'official_formation_id');
    }

    /**
     * Scope : uniquement les formations générales officielles.
     */
    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_official', true);
    }

    /**
     * Scope : formations générales officielles actives d'un centre.
     * Utilisé par tous les nouveaux formulaires (élève, professeur, classe…)
     * comme catalogue unique « Centre → Formation → Classe », sans domaine.
     */
    public function scopeOfficialActiveFor(Builder $query, int $centerId): Builder
    {
        return $query
            ->where('is_official', true)
            ->where('center_id', $centerId)
            ->where('status', 'active');
    }

    /**
     * Retrouve la formation officielle d'un centre pour un code officiel donné.
     */
    public static function officialFor(int $centerId, ?string $officialCode = null): ?self
    {
        return static::query()
            ->official()
            ->where('center_id', $centerId)
            ->when($officialCode, fn (Builder $q) => $q->where('official_code', $officialCode))
            ->first();
    }

    /**
     * Liste à plat des formations générales officielles actives, pour alimenter
     * un `<select>` de type « Formation » dans les nouveaux formulaires.
     *
     * Filtre par centre(s) autorisé(s) ; si la liste est vide, toutes les
     * officielles actives sont retournées (Super Admin multi-centres).
     * Chaque entrée inclut : id, center_id, name, code, price, classes.
     *
     * @param  iterable<int>|null  $centerIds  Centres autorisés (null = tous).
     * @return list<array<string,mixed>>
     */
    public static function selectionForCenters(?iterable $centerIds = null): array
    {
        $query = static::query()
            ->where('is_official', true)
            ->where('status', 'active')
            ->orderBy('center_id')
            ->orderBy('name');

        if ($centerIds !== null) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $centerIds))));
            if ($ids === []) {
                return [];
            }
            $query->whereIn('center_id', $ids);
        }

        /** @var \Illuminate\Database\Collection<int, static> $formations */
        $formations = $query->get();

        // Chargement groupé des classes (≤ 2 par formation officielle) pour
        // éviter N requêtes dans la vue (pré-chargement).
        $classesByFormation = ClassRoom::query()
            ->whereIn('formation_id', $formations->pluck('id')->all())
            ->orderBy('name')
            ->get(['id', 'formation_id', 'name', 'code'])
            ->groupBy('formation_id');

        $out = [];
        foreach ($formations as $formation) {
            $classes = $classesByFormation->get($formation->id, collect());
            $out[] = [
                'id'        => (int) $formation->id,
                'center_id' => (int) $formation->center_id,
                'name'      => $formation->translated('name') ?? $formation->name,
                'code'      => $formation->code,
                // Tarif annuel résolu avec la même logique que l'ancien
                // contrôleur (annual_price > price > défaut par domaine),
                // pour cohérence avec la configuration financière existante.
                'price'     => \App\Services\FinanceService::getFormationAnnualPrice($formation),
                'classes'   => $classes->map(fn (ClassRoom $c): array => [
                    'id'   => (int) $c->id,
                    'name' => $c->translated('name') ?? $c->name,
                    'code' => $c->code,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * La formation est-elle une formation générale officielle ?
     */
    public function isOfficial(): bool
    {
        return (bool) $this->is_official;
    }

    /**
     * Nombre de classes rattachées à cette formation.
     */
    public function classesCount(): int
    {
        return (int) $this->classes()->count();
    }

    /**
     * Reste-t-il un emplacement de classe (règle : maximum 2 par formation officielle).
     * Une formation non officielle n'est jamais bornée ici (données héritées).
     */
    public function hasClassSlotAvailable(): bool
    {
        if (! $this->isOfficial()) {
            return true;
        }

        return $this->classesCount() < self::MAX_CLASSES;
    }

    /**
     * Vérifier si la formation est active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Vérifier si la formation est inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Accessor : formatage du prix avec devise.
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === null) {
            return '—';
        }

        return format_price($this->price, $this->currency ?? default_currency());
    }

    /**
     * Accessor : formatage de la durée.
     */
    public function getFormattedDurationAttribute(): string
    {
        $parts = [];
        if ($this->duration_weeks) {
            $parts[] = "{$this->duration_weeks} sem.";
        }
        if ($this->duration_hours) {
            $parts[] = "{$this->duration_hours} h";
        }

        return ! empty($parts) ? implode(' / ', $parts) : '—';
    }
}
