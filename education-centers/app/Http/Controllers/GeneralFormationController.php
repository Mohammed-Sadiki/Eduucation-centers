<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Teacher;
use App\Services\DomainDashboardData;
use App\Services\OfficialFormationService;
use App\Support\DomainRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Espace des FORMATIONS GÉNÉRALES OFFICIELLES (Centre → Formation → Classe).
 *
 * Surface ADDITIVE de la phase réversible de la refonte : elle ne remplace pas
 * l'ancienne navigation par domaine (qui reste entièrement fonctionnelle pour
 * l'accès aux données historiques). Elle présente les six formations officielles
 * d'un centre et applique les nouvelles règles UNIQUEMENT à ces formations :
 *  - maximum 2 classes par formation ;
 *  - un seul professeur responsable (assignation jamais automatique).
 *
 * Aucune opération ici ne reparente ni ne modifie des données héritées.
 */
class GeneralFormationController extends Controller
{
    public function __construct(
        private readonly OfficialFormationService $formations,
    ) {}

    /**
     * Portail des six formations générales officielles (reprise exacte du design
     * des cartes), pour le centre ciblé.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Formation::class);

        session()->forget('active_formation_id');
        session()->forget('active_domain');

        $authUser = $request->user();

        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $centerId = (int) $authUser->center_id;
            $centers = collect();
        } else {
            $centers = Center::orderBy('name')->get();
            $centerId = (int) $request->query('center_id', (string) ($centers->first()->id ?? 0));
        }

        // Métad visuelles (image/icône/couleur/libellé) : les 6 programmes.
        $domains = DomainRegistry::visible();

        // Formation officielle correspondante du centre, indexée par code.
        $officialByCode = Formation::query()
            ->official()
            ->where('center_id', $centerId)
            ->get()
            ->keyBy('official_code');

        // Formations générales personnalisées du centre ou valables pour tous les centres (center_id NULL).
        $customFormations = Formation::query()
            ->customGeneral()
            ->where(function ($q) use ($centerId) {
                $q->where('center_id', $centerId)
                    ->orWhereNull('center_id');
            })
            ->orderBy('name')
            ->get();

        return view('formations-generales.index', [
            'domains' => $domains,
            'officialByCode' => $officialByCode,
            'customFormations' => $customFormations,
            'centers' => $centers,
            'centerId' => $centerId,
        ]);
    }

    /**
     * Sélecteur de configuration : présente les SIX emplacements officiels
     * autorisés (Santé / Informatique / Hôtellerie / Maçonnerie /
     * Design intérieur / Design graphique), leur état de configuration, ainsi
     * que les formations générales PERSONNALISÉES déjà créées par le centre.
     *
     * Le catalogue est extensible : les six officielles restent la base,
     * mais le bouton « + » permet d'ajouter des formations supplémentaires,
     * liées directement au centre, sans domaine ni sous-formation.
     * Si l'emplacement officiel est déjà configuré, on ouvre son édition.
     */
    public function picker(Request $request): View
    {
        Gate::authorize('viewAny', Formation::class);

        session()->forget('active_formation_id');
        session()->forget('active_domain');

        $authUser = $request->user();

        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $centerId = (int) $authUser->center_id;
            $centers = collect();
        } else {
            $centers = Center::orderBy('name')->get();
            $centerId = (int) $request->query('center_id', (string) ($centers->first()->id ?? 0));
        }

        $domains = DomainRegistry::visible();

        $officialByCode = Formation::query()
            ->official()
            ->where('center_id', $centerId)
            ->get()
            ->keyBy('official_code');

        // Formations personnalisées du centre (hors des six codes officiels).
        $customFormations = Formation::query()
            ->customGeneral()
            ->where('center_id', $centerId)
            ->with('teacher')
            ->orderBy('name')
            ->get();

        return view('formations-generales.picker', [
            'domains' => $domains,
            'officialByCode' => $officialByCode,
            'customFormations' => $customFormations,
            'centers' => $centers,
            'centerId' => $centerId,
        ]);
    }

    /**
     * Formulaire de création d'une formation générale personnalisée.
     * Le catalogue des six officielles reste la base ; cette page permet
     * d'ajouter des formations supplémentaires liées directement au centre,
     * sans domaine ni sous-formation.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Formation::class);

        $authUser = $request->user();
        $centers = Center::query()->orderBy('name')->get();

        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $centerId = (int) $authUser->center_id;
        } else {
            // Super Admin : centre choisi via ?center_id (optionnel)
            $selectedParam = $request->query('center_id');
            if ($selectedParam === 'all' || $selectedParam === '0') {
                $centerId = null;
            } else {
                $centerId = $selectedParam !== null ? (int) $selectedParam : null;
            }
        }

        $center = $centerId ? Center::query()->find($centerId) : null;

        // Si un centre spécifique est sélectionné (ou si l'utilisateur est Admin/Secrétaire de centre),
        // on charge les professeurs de ce centre.
        // Si "Tous les centres" ou aucun centre précis n'est fixé, on charge l'ensemble des professeurs.
        $centerTeachers = Teacher::query()
            ->with('formation')
            ->when($centerId !== null, fn ($q) => $q->where('center_id', $centerId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('formations-generales.create-custom', [
            'center' => $center,
            'centers' => $centers,
            'centerTeachers' => $centerTeachers,
        ]);
    }

    /**
     * Enregistre une nouvelle formation générale personnalisée dans le centre actif ou pour tous les centres.
     * is_official = true et official_code = NULL : mêmes règles métier que les
     * six officielles (≤2 classes, 1 responsable) mais hors du DomainRegistry.
     * Le center_id peut être NULL si l'option « Tous les centres » est choisie par le Super Admin.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Formation::class);

        $authUser = $request->user();

        // Centre forcé : Admin Centre / Secrétaire = le sien ; Super Admin = sélection explicite ou "all".
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $centerId = (int) $authUser->center_id;
        } else {
            $inputCenter = $request->input('center_id');
            if ($inputCenter === null || $inputCenter === '') {
                return redirect()
                    ->route('formations-generales.index')
                    ->with('error', __('Veuillez sélectionner un centre ou l\'option « Tous les centres ».'));
            }

            if ($inputCenter === 'all' || $inputCenter === '0') {
                $centerId = null;
            } else {
                $centerId = (int) $inputCenter;
                if (! Center::query()->whereKey($centerId)->exists()) {
                    return redirect()
                        ->route('formations-generales.index')
                        ->with('error', __('Centre sélectionné invalide.'));
                }
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('formations', 'name')
                    ->where(function ($query) use ($centerId) {
                        if ($centerId === null) {
                            return $query->whereNull('center_id');
                        }
                        return $query->where('center_id', $centerId);
                    })
                    ->where('is_official', true)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:520'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'annual_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'status' => ['required', 'in:active,inactive'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        // Professeur du même centre obligatoirement si la formation est rattachée à un centre précis.
        if (! empty($data['teacher_id'])) {
            $teacher = Teacher::query()->findOrFail($data['teacher_id']);
            if ($centerId !== null && (int) $teacher->center_id !== $centerId) {
                return back()
                    ->withInput()
                    ->withErrors(['teacher_id' => __('Ce professeur n\'appartient pas au centre sélectionné.')]);
            }
        }

        $codePrefix = 'ALL';
        if ($centerId !== null) {
            $center = Center::query()->find($centerId);
            $codePrefix = $center?->code ?? (string) $centerId;
        }

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('formations', 'public')
            : null;

        $formation = Formation::query()->create([
            'center_id' => $centerId,
            'name' => $data['name'],
            'code' => strtoupper(sprintf('CF-%s-%s', $codePrefix, Str::random(6))),
            'description' => $data['description'] ?? null,
            'duration_weeks' => $data['duration_weeks'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'annual_price' => $data['annual_price'],
            'currency' => $data['currency'] ?? default_currency(),
            'status' => $data['status'],
            'is_official' => true,
            'official_code' => null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'image_path' => $imagePath,
        ]);

        return redirect()
            ->route('formations-generales.index', $centerId ? ['center_id' => $centerId] : [])
            ->with('success', __('La formation « :name » a été ajoutée avec succès.', ['name' => $formation->name]));
    }

    /**
     * Formulaire de modification d'une formation personnalisée (nom inclus).
     * Réutilise la vue de configuration ; le nom n'est éditable que si
     * official_code est NULL (règle serveur reprise dans la vue).
     */
    public function editCustom(Request $request, Formation $formation): View
    {
        $this->authorizeOfficial($request, $formation, 'update');
        abort_unless($formation->official_code === null, 404);

        $formation->load(['teacher', 'center']);
        $centers = Center::query()->orderBy('name')->get();

        $centerTeachers = Teacher::query()
            ->with('formation')
            ->when($formation->center_id !== null, fn ($q) => $q->where('center_id', $formation->center_id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('formations-generales.create-custom', [
            'formation' => $formation,
            'center' => $formation->center,
            'centers' => $centers,
            'centerTeachers' => $centerTeachers,
        ]);
    }

    /**
     * Met à jour une formation personnalisée (tous les champs métier).
     */
    public function updateCustom(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorizeOfficial($request, $formation, 'update');
        abort_unless($formation->official_code === null, 404);

        $authUser = $request->user();
        $targetCenterId = $formation->center_id;

        // Si Super Admin modifie le centre concerné
        if ($authUser->isSuperAdmin() && $request->has('center_id')) {
            $inputCenter = $request->input('center_id');
            if ($inputCenter === null || $inputCenter === '') {
                return back()
                    ->withInput()
                    ->withErrors(['center_id' => __('Veuillez sélectionner un centre ou l\'option « Tous les centres ».')]);
            }
            if ($inputCenter === 'all' || $inputCenter === '0') {
                $targetCenterId = null;
            } else {
                $targetCenterId = (int) $inputCenter;
                if (! Center::query()->whereKey($targetCenterId)->exists()) {
                    return back()
                        ->withInput()
                        ->withErrors(['center_id' => __('Centre sélectionné invalide.')]);
                }
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('formations', 'name')
                    ->where(function ($query) use ($targetCenterId) {
                        if ($targetCenterId === null) {
                            return $query->whereNull('center_id');
                        }
                        return $query->where('center_id', $targetCenterId);
                    })
                    ->where('is_official', true)
                    ->ignore($formation->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:520'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'annual_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'status' => ['required', 'in:active,inactive'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if (! empty($data['teacher_id'])) {
            $teacher = Teacher::query()->findOrFail($data['teacher_id']);
            if ($targetCenterId !== null && (int) $teacher->center_id !== (int) $targetCenterId) {
                return back()
                    ->withInput()
                    ->withErrors(['teacher_id' => __('Ce professeur n\'appartient pas au centre sélectionné.')]);
            }
        }

        if ($request->hasFile('image')) {
            // Remplacement : on efface l'ancien fichier après upload réussi.
            $oldPath = $formation->image_path;
            $formation->image_path = $request->file('image')->store('formations', 'public');
            if ($oldPath && $oldPath !== $formation->image_path) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $formation->fill([
            'center_id' => $targetCenterId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_weeks' => $data['duration_weeks'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'annual_price' => $data['annual_price'],
            'currency' => $data['currency'] ?? $formation->currency,
            'status' => $data['status'],
            'teacher_id' => $data['teacher_id'] ?? null,
        ])->save();

        return redirect()
            ->route('formations-generales.index', $formation->center_id ? ['center_id' => $formation->center_id] : [])
            ->with('success', __('La formation « :name » a été mise à jour.', ['name' => $formation->name]));
    }

    /**
     * Supprime une formation personnalisée (jamais les six officielles),
     * uniquement si elle n'a plus de classes ni d'inscriptions actives.
     */
    public function destroyCustom(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorizeOfficial($request, $formation, 'delete');
        abort_unless($formation->official_code === null, 404);

        if ($formation->perimeterClasses()->exists() || $formation->perimeterEnrollments()->whereIn('status', ['accepted', 'pending', 'under_review'])->exists()) {
            return back()->with('error', __('Impossible de supprimer une formation qui possède encore des classes ou des inscriptions actives.'));
        }

        $centerId = (int) $formation->center_id;
        $name = $formation->name;

        if ($formation->image_path) {
            Storage::disk('public')->delete($formation->image_path);
        }

        $formation->delete();

        return redirect()
            ->route('formations-generales.index', ['center_id' => $centerId])
            ->with('success', __('La formation « :name » a été supprimée.', ['name' => $name]));
    }

    /**
     * Active un emplacement officiel encore vierge pour le centre ciblé.
     * L'objet Formation créé est immédiatement renvoyé vers son édition,
     * sans jamais dépasser les six codes canoniques (première vérification
     * via DomainRegistry::isValid()).
     */
    public function activate(Request $request): RedirectResponse
    {
        Gate::authorize('create', Formation::class);

        $authUser = $request->user();

        $data = $request->validate([
            'official_code' => ['required', 'string', 'max:100'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ]);

        // L'Admin Centre / Secrétaire force son propre centre ; le Super Admin peut cibler.
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $centerId = (int) $authUser->center_id;
        } else {
            $centerId = (int) ($data['center_id'] ?? 0);
            if ($centerId <= 0) {
                $centerId = (int) (Center::query()->orderBy('id')->value('id') ?? 0);
            }
        }

        if ($centerId <= 0 || ! DomainRegistry::isValid($data['official_code'])) {
            return redirect()
                ->route('formations-generales.picker', ['center_id' => $centerId ?: null])
                ->with('error', __('Emplacement de formation officielle invalide.'));
        }

        $domain = DomainRegistry::find($data['official_code']);
        $center = Center::query()->findOrFail($centerId);

        // Idempotent : si la formation officielle existe déjà (courses race,
        // ré-exécution), on ouvre directement son édition — pas de doublon.
        $formation = Formation::query()->firstOrCreate(
            [
                'center_id' => $centerId,
                'official_code' => $data['official_code'],
                'is_official' => true,
            ],
            [
                'domain' => $data['official_code'],
                'name' => $domain?->name ?? $data['official_code'],
                'code' => sprintf('OF-%s-%s', strtoupper($center->code), strtoupper(str_replace('-', '_', $data['official_code']))),
                'status' => 'active',
                'teacher_id' => null,
                'currency' => default_currency(),
            ]
        );

        return redirect()->route('formations-generales.edit', $formation);
    }

    /**
     * Formulaire de configuration d'une formation générale officielle.
     * Prix officiel, durée, description, statut et professeur responsable.
     */
    public function edit(Request $request, Formation $formation): View
    {
        $this->authorizeOfficial($request, $formation, 'update');

        $formation->load(['teacher', 'center']);

        $centerTeachers = Teacher::query()
            ->with('formation')
            ->where('center_id', $formation->center_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('formations-generales.configure', [
            'formation' => $formation,
            'domain' => DomainRegistry::find($formation->official_code),
            'centerTeachers' => $centerTeachers,
        ]);
    }

    /**
     * Enregistre la configuration d'une formation (officielle ou personnalisée).
     * Ne crée JAMAIS de nouvelle ligne : agit uniquement sur la formation
     * déjà identifiée par {formation} (404 sinon, cf. authorizeOfficial).
     * Le `center_id` n'est jamais accepté depuis le client, ce qui empêche
     * tout cross-center. Une formation PERSONNALISÉE peut aussi voir son nom
     * et sa description mis à jour ici.
     */
    public function update(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorizeOfficial($request, $formation, 'update');

        $isCustom = $formation->official_code === null;

        $data = $request->validate([
            'name' => [$isCustom ? 'required' : 'nullable', 'string', 'max:255',
                Rule::unique('formations', 'name')
                    ->where('center_id', $formation->center_id)
                    ->where('is_official', true)
                    ->ignore($formation->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'annual_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'duration_hours' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'duration_weeks' => ['nullable', 'integer', 'min:0', 'max:520'],
            'status' => ['required', 'in:active,inactive'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);

        // Le professeur doit appartenir au même centre que la formation
        // (même règle que OfficialFormationService::assignResponsible()).
        if (! empty($data['teacher_id'])) {
            $teacher = Teacher::query()->findOrFail($data['teacher_id']);
            if ((int) $teacher->center_id !== (int) $formation->center_id) {
                return back()
                    ->withInput()
                    ->withErrors(['teacher_id' => __('Ce professeur n\'appartient pas au centre de cette formation.')]);
            }
        }

        // On ne touche ni au code, ni au center_id, ni à official_code, ni à
        // is_official : la configuration reste strictement cantonnée aux champs
        // métier affichés. Le nom n'est modifiable que pour les personnalisées.
        $formation->fill(array_filter([
            'name' => $isCustom ? ($data['name'] ?? null) : null,
            'description' => $data['description'] ?? $formation->description,
            'annual_price' => $data['annual_price'] ?? null,
            'currency' => $data['currency'] ?? $formation->currency,
            'duration_hours' => $data['duration_hours'] ?? null,
            'duration_weeks' => $data['duration_weeks'] ?? null,
            'status' => $data['status'],
            'teacher_id' => $data['teacher_id'] ?? null,
        ], fn ($v) => $v !== null))->save();

        return redirect()
            ->route('formations-generales.show', $formation)
            ->with('success', __('La formation a été mise à jour.'));
    }

    /**
     * Dashboard d'une formation générale (officielle ou personnalisée).
     * Ouvre directement le tableau de bord avec indicateurs réels, étudiants, classes, présences, certificats.
     */
    public function show(Request $request, Formation $formation, DomainDashboardData $dashboardService): View
    {
        $this->authorizeOfficial($request, $formation, 'view');

        // Mettre en session l'espace de la formation sélectionnée
        session(['active_formation_id' => $formation->id]);
        if ($formation->official_code) {
            session(['active_domain' => $formation->official_code]);
        } else {
            session()->forget('active_domain');
        }

        $formation->load(['center', 'teacher']);

        $data = $dashboardService->buildForFormation($formation);

        $centers = Center::orderBy('name')->get();
        $domain = $formation->official_code ?? ('formation-' . $formation->id);
        $domainLabel = $formation->translated('name') ?? $formation->name;

        // Classes de la formation pour gestion éventuelle
        $classes = $formation->perimeterClasses()->orderBy('code')->get();
        $centerTeachers = Teacher::query()
            ->with('formation')
            ->where('center_id', $formation->center_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('super-admin.domain.dashboard', array_merge($data, [
            'formation'   => $formation,
            'domain'      => $domain,
            'domainLabel' => $domainLabel,
            'classes'     => $classes,
            'centerTeachers' => $centerTeachers,
            'centers'     => $centers,
            'centerId'    => $formation->center_id,
        ]));
    }

    /**
     * Ajoute une classe à une formation officielle (plafond de 2, verrou de concurrence).
     */
    public function storeClass(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorizeOfficial($request, $formation, 'update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:classes,code'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Le centre est dérivé de la formation (jamais fourni par le client).
        $data['center_id'] = $formation->center_id;
        $data['status'] = $data['status'] ?? 'active';

        try {
            $class = $this->formations->createClass($formation, $data);
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('formations-generales.show', $formation)
            ->with('success', __('La classe « :name » a été ajoutée à la formation.', ['name' => $class->name]));
    }

    /**
     * Assigne (ou remplace) le professeur responsable : un seul, même centre.
     * Le remplacement conserve l'historique (aucune suppression de liens existants).
     */
    public function assignTeacher(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorizeOfficial($request, $formation, 'update');

        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
        ]);

        $teacher = Teacher::query()->findOrFail($data['teacher_id']);

        try {
            $this->formations->assignResponsible($formation, $teacher);
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Le professeur responsable a été assigné à la formation.'));
    }

    /**
     * Retire le professeur responsable (sans toucher aux données historiques).
     */
    public function detachTeacher(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorizeOfficial($request, $formation, 'update');

        $this->formations->detachResponsible($formation);

        return back()->with('success', __('Le professeur responsable a été retiré.'));
    }

    /**
     * Autorise l'accès et garantit que la formation est bien officielle (404 sinon :
     * cette surface ne doit jamais exposer les contraintes neuves aux héritées).
     */
    private function authorizeOfficial(Request $request, Formation $formation, string $ability): void
    {
        abort_unless($formation->isOfficial(), 404);

        Gate::authorize($ability, $formation);
    }

    /**
     * Route du dashboard domaine historique selon le rôle (accès préservé si valide).
     */
    private function domainDashboardRoute(Request $request, ?string $slug): ?string
    {
        if (empty($slug) || ! DomainRegistry::isValid($slug)) {
            return null;
        }

        $isSuper = $request->user()->isSuperAdmin();

        return $isSuper
            ? route('super-admin.domain.dashboard', $slug)
            : route('center-admin.domain.dashboard', $slug);
    }
}
