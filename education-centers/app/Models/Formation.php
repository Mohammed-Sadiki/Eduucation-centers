<?php

namespace App\Models;

use App\Concerns\HasDomainScope;
use App\Concerns\HasTranslations;
use App\Models\Concerns\HasContextScope;
use App\Services\FinanceService;
use App\Support\AcademicPerimeter;
use Illuminate\Database\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Formation extends Model
{
    use HasContextScope;
    use HasDomainScope;
    use HasFactory;
    use HasTranslations;

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
        'image_path',
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
     * Invalidage du cache de résolution du périmètre dès qu'une formation change
     * (une création, un changement de statut ou de domaine modifie la résolution
     * « formation générale » utilisée par tous les filtres de l'application).
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            AcademicPerimeter::flush();
        });

        static::deleted(function (): void {
            AcademicPerimeter::flush();
        });
    }

    /**
     * Formation générale de rattachement : la formation elle-même si elle est
     * déjà générale, sinon la formation générale du même centre portant le même
     * code de domaine (cas des anciennes sous-formations non encore reparentées).
     */
    public function generalFormationModel(): ?self
    {
        return AcademicPerimeter::generalFormation($this);
    }

    /**
     * Accessor : nom de la formation générale dont cette formation porte les
     * données. Identique à son propre nom pour une formation générale ; nom de
     * la formation équivalente pour une ancienne sous-formation. C'est le
     * libellé à utiliser partout où une formation est affichée.
     */
    public function getGeneralFormationNameAttribute(): string
    {
        $general = $this->generalFormationModel();

        return (string) (($general ? $general->translated('name') ?? $general->name : null)
            ?? $this->translated('name') ?? $this->name ?? '');
    }

    /**
     * Accessor : identifiant de la formation générale de rattachement. C'est la
     * clé à envoyer dans un `<select>` de formations générales : une ancienne
     * sous-formation porte les données de sa formation générale, mais n'est
     * jamais sélectionnable elle-même.
     */
    public function getGeneralFormationIdAttribute(): ?int
    {
        return $this->generalFormationModel()?->id;
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
     * Classes du périmètre de cette formation : celles rattachées directement et
     * celles rattachées à ses alias historiques (anciennes sous-formations non
     * encore reparentées). C'est LA requête à utiliser pour afficher, compter ou
     * vérifier les classes d'une formation générale ; `classes()` reste la
     * relation brute (écriture, migrations, tests de structure).
     *
     * @return Builder<ClassRoom>
     */
    public function perimeterClasses(): Builder
    {
        $query = ClassRoom::query()
            ->with('formation')
            ->whereIn('formation_id', AcademicPerimeter::equivalentIds($this));

        if ($this->center_id !== null) {
            $query->where('center_id', $this->center_id);
        }

        return $query;
    }

    /**
     * Inscriptions du périmètre de cette formation (alias historiques inclus) :
     * mêmes règles que {@see self::perimeterClasses()}.
     *
     * @return Builder<Enrollment>
     */
    public function perimeterEnrollments(): Builder
    {
        $query = Enrollment::query()
            ->whereIn('formation_id', AcademicPerimeter::equivalentIds($this));

        if ($this->center_id !== null) {
            $query->where('center_id', $this->center_id);
        }

        return $query;
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
     * Scope : les formations du fonctionnement COURANT de l'application, c'est-à-dire
     * sélectionnables dans les formulaires et proposées dans les catalogues :
     *  - les formations générales (officielles ou ajoutées par un centre) ;
     *  - les formations héritées qui n'ont aucun équivalent général actif : elles
     *    portent seules la formation courante de leur centre (donnée jamais
     *    migrée), ce ne sont pas des reliquats d'une hiérarchie dépassée.
     *
     * Une ancienne sous-formation qui a un équivalent général est exclue : elle
     * n'est plus qu'un alias technique de cette formation générale, dont elle
     * partage les données (voir {@see AcademicPerimeter}).
     *
     * Implémentation unique de la règle : tous les compteurs, catalogues et
     * filtres de l'application passent par ce scope.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_official', true)
                ->orWhereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('formations as general_twin')
                        ->whereColumn('general_twin.center_id', 'formations.center_id')
                        ->whereColumn('general_twin.domain', 'formations.domain')
                        ->where('general_twin.is_official', true)
                        ->where('general_twin.status', 'active');
                });
        });
    }

    /**
     * Scope : uniquement les formations générales officielles.
     */
    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_official', true);
    }

    /**
     * Scope : formations générales PERSONNALISÉES créées par un centre
     * (is_official = true mais hors des six codes officiels : official_code
     * NULL). Elles suivent les mêmes règles métier (≤2 classes, 1
     * responsable) mais ne dépendent pas du DomainRegistry.
     */
    public function scopeCustomGeneral(Builder $query): Builder
    {
        return $query->where('is_official', true)->whereNull('official_code');
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
            $query->where(function ($q) use ($ids) {
                $q->whereIn('center_id', $ids)
                    ->orWhereNull('center_id');
            });
        }

        /** @var Collection<int, static> $formations */
        $formations = $query->get();

        // Chargement groupé des classes (≤ 2 par formation générale) pour
        // éviter N requêtes dans la vue (pré-chargement).
        //
        // Périmètre : les classes d'une formation générale sont celles qui lui
        // sont rattachées directement ET celles rattachées à ses alias
        // historiques (anciennes sous-formations non encore reparentées) : c'est
        // l'autorité unique {@see AcademicPerimeter} qui définit ce regroupement,
        // jamais la vue ni le contrôleur.
        $classIds = [];
        foreach ($formations as $formation) {
            foreach (AcademicPerimeter::equivalentIds($formation) as $id) {
                $classIds[$id] = true;
            }
        }

        $classesByGeneral = ClassRoom::query()
            ->with('formation')
            ->whereIn('formation_id', array_keys($classIds) ?: [0])
            ->orderBy('name')
            ->get(['id', 'formation_id', 'name', 'code'])
            ->groupBy(fn (ClassRoom $c): int => (int) AcademicPerimeter::generalFormationId($c->formation_id));

        $out = [];
        foreach ($formations as $formation) {
            $classes = $classesByGeneral->get($formation->id, collect());
            $out[] = [
                'id' => (int) $formation->id,
                'center_id' => (int) $formation->center_id,
                'name' => $formation->translated('name') ?? $formation->name,
                'code' => $formation->code,
                // Tarif annuel résolu avec la même logique que l'ancien
                // contrôleur (annual_price > price > défaut par domaine),
                // pour cohérence avec la configuration financière existante.
                'price' => FinanceService::getFormationAnnualPrice($formation),
                'classes' => $classes->map(fn (ClassRoom $c): array => [
                    'id' => (int) $c->id,
                    // Affiché sous la formation déjà sélectionnée : seul le
                    // groupe fait foi (« Groupe A »), jamais une ancienne
                    // sous-formation. ClassRoom ne porte pas les traductions.
                    'name' => $c->group_label,
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
     * Nombre de classes du périmètre de cette formation (alias historiques
     * compris) : c'est la règle « maximum 2 classes par formation générale » qui
     * s'applique aux données réelles, pas seulement aux classes déjà reparentées.
     */
    public function classesCount(): int
    {
        return (int) $this->perimeterClasses()->count();
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
