<?php

namespace App\Support;

use App\Concerns\HasDomainScope;
use App\Models\ClassRoom;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Autorité unique du périmètre pédagogique « Centre → Formation générale → Classe → Élève ».
 *
 * Ce classeur centralise les quatre règles que tous les modules doivent appliquer,
 * afin qu'aucune vue ni aucun contrôleur ne réinvente un filtrage concurrent :
 *
 *  1. le centre autorisé (forcé côté serveur selon le rôle) ;
 *  2. la formation générale ACTIVE du centre (seules formations proposées) ;
 *  3. les classes appartenant à cette formation (≤ 2 : Groupe A / Groupe B) ;
 *  4. les données rattachées (inscriptions, élèves, présences, évaluations,
 *     paiements…) qui doivent suivre exactement le même ensemble de formations.
 *
 * Pont de transition, documenté et non destructif : tant que les données
 * historiques ne sont pas reparentées, une classe peut encore être rattachée à
 * une ancienne sous-formation (is_official = false). Cette sous-formation est
 * alors RATTACHÉE EN LECTURE à la formation générale du même centre et du même
 * code de domaine : elle devient une simple alias technique de cette formation.
 * Elle n'apparaît donc jamais comme une formation sélectionnable, et le libellé
 * affiché est « Formation générale · Groupe X ». Après la transformation des
 * données (formation générale réelle + classes renommées), cet alias devient
 * un no-op automatique : rien d'autre à changer dans le code.
 *
 * La résolution est mémoïsée par processus et invalidée dès qu'une formation
 * est écrite (voir {@see Formation::booted()}), pour éviter toute
 * lecture obsolète au sein d'une même requête ou d'une même suite de tests.
 */
class AcademicPerimeter
{
    /** @var array<int, int|null> formationId => id de sa formation générale (null si aucune) */
    protected static array $generalOf = [];

    /** @var array<string, list<int>> "center:domain" => ids des formations héritées aliasées */
    protected static array $aliasesBySlot = [];

    /** @var array<int, list<int>> formationId => ids (formation + alias) à utiliser dans les requêtes */
    protected static array $scopedIds = [];

    /** @var array<int, array<int, string>> formationGénéraleId => [classId => libellé de groupe] */
    protected static array $groupLabels = [];

    /**
     * Requête des formations générales courant de l'application, pour les pages
     * publiques qui n'ont pas de session utilisateur (accueil, catalogue,
     * candidatures). Même règle que {@see self::formationsQuery()} :
     * formation officielle active, ou formation héritée n'ayant aucun équivalent
     * général actif (elle EST la formation courante de son centre).
     *
     * Une ancienne sous-formation devenue alias d'une formation générale n'y
     * apparaît jamais : le catalogue public ne peut donc plus proposer l'ancienne
     * hiérarchie.
     *
     * @return Builder<Formation>
     */
    public static function currentFormationsQuery(?int $centerId = null): Builder
    {
        $query = Formation::query()
            ->where('status', 'active')
            ->current()
            ->orderBy('name');

        if ($centerId) {
            $query->where('center_id', $centerId);
        }

        return $query;
    }

    /**
     * Vider les mémoires de résolution (appelé à chaque écriture de formation).
     */
    public static function flush(): void
    {
        static::$generalOf = [];
        static::$aliasesBySlot = [];
        static::$scopedIds = [];
        static::$groupLabels = [];
    }

    public function __construct(
        protected ?User $user = null,
        protected ?int $centerId = null,
        protected ?string $domainSlug = null,
    ) {}

    /**
     * Périmètre de la requête courante pour un utilisateur donné.
     *
     * @param  int|null  $centerId  Restitution volontaire du Super Admin sur un centre
     *                              (jamais appliquée à un Admin Centre, figé sur son centre).
     * @param  string|null  $domainSlug  Espace de formation forcé par l'appelant
     *                                   (tableaux de bord d'une formation) ; par défaut
     *                                   l'espace de la session.
     */
    public static function for(?User $user, ?int $centerId = null, ?string $domainSlug = null): self
    {
        $context = function_exists('user_context') ? user_context() : null;

        $forcedCenter = null;
        if ($user?->isCenterAdmin() || $user?->isTeacher()) {
            $forcedCenter = $user->center_id !== null ? (int) $user->center_id : null;
        } elseif ($centerId) {
            $forcedCenter = (int) $centerId;
        }

        return new self(
            user: $user,
            centerId: $forcedCenter,
            domainSlug: $domainSlug ?? $context?->domainSlug(),
        );
    }

    public function centerId(): ?int
    {
        return $this->centerId;
    }

    public function domainSlug(): ?string
    {
        return $this->domainSlug;
    }

    /**
     * Copie du périmètre sans la contrainte du domaine (espace de formation)
     * actif dans la session. À n'utiliser que pour les formulaires qui éditent
     * une ressource précise — classe, séance, professeur — et qui doivent alors
     * proposer TOUTES les formations générales du centre autorisé, y compris
     * celles hors de l'espace courant : restreindre la liste ferait perdre la
     * valeur en cours et la modifierait silencieusement à l'enregistrement.
     */
    public function withoutActiveDomain(): self
    {
        $clone = clone $this;
        $clone->domainSlug = null;

        return $clone;
    }

    // ─── Formations générales ────────────────────────────────────────────────

    /**
     * Requête des seules formations générales actives du périmètre : c'est LA
     * source des `<select>` de formation de toute l'application.
     *
     * @return Builder<Formation>
     */
    public function formationsQuery(): Builder
    {
        $query = Formation::query()
            ->where('is_official', true)
            ->where('status', 'active')
            ->orderBy('name');

        if ($this->centerId) {
            $query->where('center_id', $this->centerId);
        }

        if ($this->domainSlug && $this->domainSlug !== 'all') {
            $query->where('domain', $this->domainSlug);
        }

        return $query;
    }

    /**
     * @return list<array<string, mixed>> options {id, name, center_id, code, classes}
     */
    public function formationOptions(): array
    {
        return $this->formationsQuery()->get()
            ->map(fn (Formation $f): array => [
                'id' => (int) $f->id,
                'center_id' => (int) $f->center_id,
                'name' => $f->translated('name') ?? $f->name,
                'code' => $f->code,
            ])
            ->values()
            ->all();
    }

    /**
     * Une formation est-elle une formation générale active du périmètre ?
     * Sert aux validations serveur (un `<select>` n'est jamais une sécurité).
     */
    public function isSelectableFormation(int $formationId): bool
    {
        return $this->formationsQuery()->where('id', $formationId)->exists();
    }

    /**
     * Identifiants à utiliser dans TOUTES les requêtes de données rattachées à
     * « cette » formation : la formation générale + ses alias historiques.
     *
     * C'est ce qui garantit qu'un tableau filtré sur une formation générale
     * contient exactement les mêmes lignes que le filtre déroulant correspondant.
     *
     * @return list<int>
     */
    public function formationIds(?int $formationId = null): array
    {
        if ($formationId !== null) {
            if (isset(static::$scopedIds[$formationId])) {
                return static::$scopedIds[$formationId];
            }

            $formation = Formation::query()->find($formationId);

            // Une sous-formation héritée est résolue vers sa formation générale :
            // filtrer sur l'une ou l'autre revient au même jeu de données.
            return static::$scopedIds[$formationId] = $formation
                ? $this->idsOf(static::generalFormation($formation) ?? $formation)
                : [$formationId];
        }

        $ids = [];
        foreach ($this->formationsQuery()->get(['id', 'center_id', 'domain', 'official_code', 'is_official', 'status']) as $general) {
            foreach ($this->idsOf($general) as $id) {
                $ids[$id] = true;
            }
        }

        // Formations non officielles qui n'ont aucune formation générale
        // équivalente : elles constituent en elles-mêmes le courant du centre
        // (données jamais migrées). Elles ne sont jamais proposées comme
        // « sous-formation » d'une formation générale existante.
        foreach ($this->orphanFormationIds() as $id) {
            $ids[$id] = true;
        }

        return array_keys($ids);
    }

    /**
     * Identifiants des formations héritées orphelines actives du périmètre :
     * aucune formation générale active ne porte leur centre + leur domaine, il
     * n'existe donc pas d'ancienne hiérarchie à cacher derrière elles.
     *
     * @return list<int>
     */
    public function orphanFormationIds(): array
    {
        $query = Formation::query()
            ->where('is_official', false)
            ->where('status', 'active')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('formations as general_twin')
                    ->whereColumn('general_twin.center_id', 'formations.center_id')
                    ->whereColumn('general_twin.domain', 'formations.domain')
                    ->where('general_twin.is_official', true)
                    ->where('general_twin.status', 'active');
            });

        if ($this->centerId) {
            $query->where('center_id', $this->centerId);
        }

        if ($this->domainSlug && $this->domainSlug !== 'all') {
            $query->where('domain', $this->domainSlug);
        }

        return array_map('intval', $query->pluck('id')->all());
    }

    /**
     * Appliquer le périmètre à une requête portant une colonne `formation_id`
     * (inscriptions, évaluations, paiements, factures, certificats…).
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function applyFormationIds(Builder $query, string $column = 'formation_id', ?int $formationId = null): Builder
    {
        $ids = $this->formationIds($formationId);

        // Périmètre non restreint (aucune formation choisie, aucun espace de
        // formation actif) : les lignes qui ne relèvent d'aucune formation ne
        // sont pas des reliquats de l'ancienne hiérarchie et restent visibles.
        return $query->where(function (Builder $q) use ($column, $ids, $formationId) {
            $q->whereIn($column, $ids);

            if ($this->keepsUnattached($formationId)) {
                $q->orWhereNull($column);
            }
        });
    }

    /**
     * Contrainte générique sur un chemin de relation vers `formations`
     * (ex. classRoom.formation pour les séances et présences).
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function applyThroughFormation(Builder $query, string $relationPath, ?int $formationId = null): Builder
    {
        $ids = $this->formationIds($formationId);

        return $query->whereHas($relationPath, fn (Builder $f) => $f->whereIn('formations.id', $ids));
    }

    /**
     * Le périmètre est-il assez large pour maintenir visibles les lignes qui ne
     * relèvent d'aucune formation (dossier étudiant sans inscription, certificat
     * sans formation, clé étrangère non renseignée) ?
     *
     * Dès que l'utilisateur a restreint volontairement la vue — une formation
     * précise ou un espace de formation actif — ces lignes n'ont plus de sens
     * dans la liste et y sont remplacées par celles de la formation choisie.
     * Dans la vue large, elles doivent rester : ce ne sont pas des reliquats de
     * l'ancienne hiérarchie.
     */
    public function keepsUnattached(?int $formationId = null): bool
    {
        return $formationId === null && ($this->domainSlug === null || $this->domainSlug === 'all');
    }

    /**
     * Restreindre n'importe quelle requête de données au périmètre « formations
     * générales ». Les modèles qui déclarent {@see HasDomainScope}
     * réutilisent leurs chemins de relation existants ; les autres sont filtrés
     * sur leur colonne `formation_id`.
     *
     * C'est LE point d'entrée unique à utiliser par tous les modules : listes,
     * tableaux, statistiques et exports filtrent ainsi exactement comme les
     * menus déroulants correspondants.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function applyTo(Builder $query, ?int $formationId = null, string $column = 'formation_id'): Builder
    {
        $model = $query->getModel();

        // La requête porte-t-elle les FORMATIONS elles-mêmes ? Alors le périmètre
        // n'est pas une liste d'identifiants (qui inclurait les alias techniques)
        // mais la règle « formation générale courante » appliquée par
        // {@see \App\Models\Formation::scopeCurrent()} : catalogues, compteurs et
        // listes de formations ne peuvent plus proposer une ancienne
        // sous-formation comme si c'était une formation actuelle.
        if ($model instanceof Formation) {
            $query->current();

            if ($this->centerId) {
                $query->where('center_id', $this->centerId);
            }

            if ($this->domainSlug && $this->domainSlug !== 'all') {
                $query->where('domain', $this->domainSlug);
            }

            return $formationId === null
                ? $query
                : $query->whereIn('id', $this->formationIds($formationId));
        }

        if (method_exists($model, 'scopeWithinGeneralFormations')) {
            return $query->withinGeneralFormations(
                $this->formationIds($formationId),
                $this->keepsUnattached($formationId)
            );
        }

        return $this->applyFormationIds($query, $column, $formationId);
    }

    // ─── Classes ─────────────────────────────────────────────────────────────

    /**
     * Requête des classes du périmètre : uniquement des classes de formations
     * générales (alias historiques inclus), jamais des classes orphelines d'une
     * ancienne hiérarchie supprimée.
     *
     * @return Builder<ClassRoom>
     */
    public function classesQuery(?int $formationId = null): Builder
    {
        $query = ClassRoom::query()
            ->with('formation')
            ->whereIn('formation_id', $this->formationIds($formationId));

        if ($this->centerId) {
            $query->where('center_id', $this->centerId);
        }

        return $query;
    }

    /**
     * Collection de classes prête pour un `<select>` de filtre ou de formulaire,
     * triée par libellé « Formation générale · Groupe X ».
     *
     * @param  array<int>|Collection<int>|null  $restrictToClassIds  Sous-ensemble explicite
     *                                                               (ex. classes affectées à un professeur).
     * @return Collection<int, ClassRoom>
     */
    public function classesForSelect(array|Collection|null $restrictToClassIds = null, bool $activeOnly = false): Collection
    {
        $query = $this->classesQuery();

        if ($restrictToClassIds !== null) {
            $ids = array_values(array_map('intval', $restrictToClassIds instanceof Collection
                ? $restrictToClassIds->all()
                : $restrictToClassIds));

            $query->whereIn('id', $ids === [] ? [0] : $ids);
        }

        if ($activeOnly) {
            $query->where('status', 'active');
        }

        if ($this->domainSlug && $this->domainSlug !== 'all') {
            $query->forDomain($this->domainSlug);
        }

        return $query->get()
            ->sortBy(fn (ClassRoom $c): string => self::labelOf($c))
            ->values();
    }

    /**
     * Options « Formation générale · Groupe X » pour les filtres et formulaires.
     * Le libellé ne mentionne jamais une ancienne sous-formation.
     *
     * @return list<array<string, mixed>> {id, label, formation, group, center_id}
     */
    public function classOptions(?int $formationId = null): array
    {
        $options = $this->classesQuery($formationId)->get()
            ->map(function (ClassRoom $c): array {
                $general = self::generalFormation($c->formation);

                return [
                    'id' => (int) $c->id,
                    'label' => self::labelOf($c),
                    'group' => self::groupLabelOf($c),
                    'formation' => $general
                        ? ($general->translated('name') ?? $general->name)
                        : (($c->formation?->translated('name') ?? $c->formation?->name) ?? ''),
                    'center_id' => (int) $c->center_id,
                    'name' => $c->name,
                ];
            })
            ->all();

        usort($options, fn (array $a, array $b): int => [$a['formation'], $a['label']] <=> [$b['formation'], $b['label']]);

        return $options;
    }

    /**
     * La classe appartient-elle au périmètre (formation générale active, centre
     * autorisé, domaine actif le cas échéant) ? Utilisé par les validations.
     */
    public function allowsClass(int $classId, ?int $formationId = null): bool
    {
        $query = $this->classesQuery($formationId);

        if ($this->domainSlug && $this->domainSlug !== 'all') {
            $query->forDomain($this->domainSlug);
        }

        return $query->whereKey($classId)->exists();
    }

    // ─── Résolution statique (partagée par les vues et les modèles) ──────────

    /**
     * Formation générale d'une formation, quel que soit son statut :
     * elle-même si elle est déjà générale, sinon la formation générale active du
     * même centre portant le même code de domaine (alias historique).
     *
     * À défaut d'équivalent général actif, la formation héritée est sa propre
     * référence : une donnée jamais migrée ne doit jamais disparaître des listes.
     */
    public static function generalFormation(?Formation $formation): ?Formation
    {
        if (! $formation) {
            return null;
        }

        // Déjà générale : elle est sa propre formation générale.
        if ($formation->is_official) {
            return $formation;
        }

        if (array_key_exists($formation->id, static::$generalOf)) {
            $generalId = static::$generalOf[$formation->id];

            return $generalId === null ? null : Formation::query()->find($generalId);
        }

        // Une formation héritée sans domaine de rattachement ne peut pas être
        // résolue vers une formation générale : elle reste sa propre référence.
        $domain = $formation->domain === null ? '' : trim((string) $formation->domain);

        if ($domain === '') {
            static::$generalOf[$formation->id] = $formation->id;

            return $formation;
        }

        $general = Formation::query()
            ->where('is_official', true)
            ->where('center_id', $formation->center_id)
            ->where('domain', $domain)
            ->where('status', 'active')
            ->first();

        static::$generalOf[$formation->id] = $general?->id ?? $formation->id;

        return $general ?? $formation;
    }

    /**
     * Identifiant de la formation générale de rattachement (null si aucune).
     */
    public static function generalFormationId(Formation|int|null $formation): ?int
    {
        return static::generalFormation(static::toFormation($formation))?->id;
    }

    /**
     * Une formation fait-elle partie du fonctionnement courant, c'est-à-dire
     * est-elle sélectionnable dans un formulaire ?
     *
     * Oui : une formation générale (officielle ou ajoutée par un centre) active.
     * Oui : une formation héritée qui n'a aucun équivalent général — elle EST la
     * formation courante de son centre.
     * Non : une ancienne sous-formation qui a un équivalent général, car elle
     * n'est plus qu'un alias technique de celui-ci et ne doit jamais être proposée.
     *
     * Indépendante du contexte de session (centre/domaine) : les contrôles de
     * centre et de domaine sont faits par ailleurs.
     */
    public static function isCurrentFormation(Formation|int|null $formation): bool
    {
        $model = static::toFormation($formation);

        if (! $model) {
            return false;
        }

        return (int) static::generalFormation($model)?->id === (int) $model->id;
    }

    /**
     * Tous les identifiants de formations qui portent les données d'UNE MEME
     * formation générale : elle-même et ses alias historiques, dans les deux
     * sens. Une ancienne sous-formation et sa formation générale décrivent la
     * même réalité métier : comparer ou filtrer sur l'une vaut l'autre.
     *
     * À utiliser partout où le code compare ou filtre deux `formation_id`
     * (inscription ↔ classe, paiement ↔ formation, etc.).
     *
     * @return list<int>
     */
    public static function equivalentIds(Formation|int|null $formation): array
    {
        $model = static::toFormation($formation);

        if (! $model) {
            return [];
        }

        $general = static::generalFormation($model);

        if (! $general) {
            return [(int) $model->id];
        }

        $ids = [(int) $general->id, ...array_map('intval', static::aliasIds($general))];

        // La formation demandée elle-même reste dans la liste : une formation
        // générale sans alias, ou une formation jamais rattachée.
        $ids[] = (int) $model->id;

        return array_values(array_unique($ids));
    }

    /**
     * Deux formations décrivent-elles la même formation générale ?
     */
    public static function sameGeneralFormation(Formation|int|null $a, Formation|int|null $b): bool
    {
        $ida = static::toFormation($a)?->id;
        $idb = static::toFormation($b)?->id;

        if ($ida === null || $idb === null) {
            return false;
        }

        if ((int) $ida === (int) $idb) {
            return true;
        }

        return array_intersect(static::equivalentIds($a), static::equivalentIds($b)) !== [];
    }

    /**
     * Résoudre une formation à partir de son identifiant.
     */
    protected static function toFormation(Formation|int|null $formation): ?Formation
    {
        if ($formation instanceof Formation) {
            return $formation;
        }

        return $formation === null ? null : Formation::query()->find($formation);
    }

    /**
     * Libellé de groupe d'une classe, sans la mention d'une ancienne
     * sous-formation. Deux classes d'une même formation générale portant le même
     * libellé (cas de deux anciennes sous-formations regroupées) sont numérotées
     * « Groupe A » puis « Groupe B », la règle métier étant 2 classes maximum
     * par formation générale.
     */
    public static function groupLabelOf(ClassRoom $class): string
    {
        $general = static::generalFormation($class->formation);

        if (! $general) {
            return static::baseGroupLabel($class);
        }

        $labels = static::groupLabelsOf($general);
        $base = $labels[$class->id] ?? static::baseGroupLabel($class);

        $sameLabelCount = count(array_filter($labels, fn (string $label): bool => $label === $base));

        if ($sameLabelCount > 1) {
            $rank = array_search((int) $class->id, array_keys($labels), true);

            return __('Groupe :lettre', ['lettre' => static::letter($rank === false ? 0 : (int) $rank)]);
        }

        return $base;
    }

    /**
     * Libellé de groupe brut : nom enregistré amputé de l'ancienne sous-formation,
     * sans numérotation.
     */
    protected static function baseGroupLabel(ClassRoom $class): string
    {
        $general = static::generalFormation($class->formation);

        // Classe déjà rattachée à une formation générale : le nom enregistré fait foi.
        if ($general && (int) $general->id === (int) $class->formation_id) {
            return $class->name;
        }

        $name = (string) $class->name;

        if (str_contains($name, '·')) {
            $tail = trim(Str::afterLast($name, '·'));

            if ($tail !== '') {
                return $tail;
            }
        }

        // Repli sur le code (…-A / …-B) quand le nom ne sépare pas la spécialité.
        if (preg_match('/[-_\s]([A-Z])$/u', (string) $class->code, $m)) {
            return __('Groupe :lettre', ['lettre' => $m[1]]);
        }

        return $name;
    }

    /**
     * Libellés de groupe de toutes les classes d'une formation générale, ordonnés
     * par identifiant (ordre de création = ordre des groupes).
     *
     * @return array<int, string> classId => libellé
     */
    protected static function groupLabelsOf(Formation $general): array
    {
        if (isset(static::$groupLabels[$general->id])) {
            return static::$groupLabels[$general->id];
        }

        $ids = [(int) $general->id, ...array_map('intval', static::aliasIds($general))];

        return static::$groupLabels[$general->id] = ClassRoom::query()
            ->with('formation')
            ->whereIn('formation_id', $ids)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ClassRoom $c): array => [(int) $c->id => static::baseGroupLabel($c)])
            ->all();
    }

    /**
     * Lettre de groupe à partir d'un rang (0 → A, 1 → B…).
     */
    protected static function letter(int $rank): string
    {
        return $rank < 26 ? chr(ord('A') + $rank) : (string) ($rank + 1);
    }

    /**
     * Libellé complet d'une classe pour les filtres et les listes :
     * « Informatique · Groupe A ».
     */
    public static function labelOf(ClassRoom $class): string
    {
        $general = static::generalFormation($class->formation);

        $formationLabel = $general
            ? ($general->translated('name') ?? $general->name)
            : (($class->formation?->translated('name') ?? $class->formation?->name) ?? '');

        $group = static::groupLabelOf($class);

        return $formationLabel === ''
            ? $group
            : $formationLabel.' · '.$group;
    }

    /**
     * Alias techniques (anciennes sous-formations) d'une formation générale :
     * leurs données restent comptabilisées avec la formation générale, le temps
     * de la transformation.
     *
     * @return list<int>
     */
    public static function aliasIds(Formation $general): array
    {
        if (! $general->is_official) {
            return [];
        }

        // Cache complet construit une seule fois : ids des formations héritées
        // regroupées par (centre, domaine). Une seule requête, quelle que soit
        // la taille du périmètre.
        if (static::$aliasesBySlot === []) {
            static::$aliasesBySlot = Formation::query()
                ->where('is_official', false)
                ->get(['id', 'center_id', 'domain'])
                ->groupBy(fn (Formation $f): string => $f->center_id.':'.($f->domain ?? ''))
                ->map(fn ($group): array => $group->pluck('id')->map(fn ($id) => (int) $id)->all())
                ->all();
        }

        $slotKey = trim((string) ($general->official_code ?: $general->domain));

        // Pas de domaine de rattachement (formation générale personnalisée) :
        // aucune ancienne sous-formation ne peut lui être associée.
        if ($slotKey === '') {
            return [];
        }

        return static::$aliasesBySlot[$general->center_id.':'.$slotKey] ?? [];
    }

    /**
     * Ids de la formation générale + de ses alias (ordre stable).
     *
     * @return list<int>
     */
    protected function idsOf(Formation $formation): array
    {
        return [(int) $formation->id, ...array_map('intval', static::aliasIds($formation))];
    }
}
