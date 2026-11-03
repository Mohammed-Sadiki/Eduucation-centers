<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Teacher;
use App\Services\OfficialFormationService;
use App\Support\DomainRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        $authUser = $request->user();

        if ($authUser->isCenterAdmin()) {
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

        return view('formations-generales.index', [
            'domains'      => $domains,
            'officialByCode' => $officialByCode,
            'centers'      => $centers,
            'centerId'     => $centerId,
        ]);
    }

    /**
     * Sélecteur de configuration : présente les SIX emplacements officiels
     * autorisés (Santé / Informatique / Hôtellerie / Maçonnerie /
     * Design intérieur / Design graphique) et leur état de configuration.
     *
     * Aucun septième slot n'est possible : la liste provient de
     * DomainRegistry::visible(), source unique du catalogue officiel.
     * Si l'emplacement est déjà configuré pour le centre, on redirige vers
     * son édition ; sinon on propose de l'activer (via activate()).
     */
    public function picker(Request $request): View
    {
        Gate::authorize('viewAny', Formation::class);

        $authUser = $request->user();

        if ($authUser->isCenterAdmin()) {
            $centerId = (int) $authUser->center_id;
            $centers  = collect();
        } else {
            $centers  = Center::orderBy('name')->get();
            $centerId = (int) $request->query('center_id', (string) ($centers->first()->id ?? 0));
        }

        $domains = DomainRegistry::visible();

        $officialByCode = Formation::query()
            ->official()
            ->where('center_id', $centerId)
            ->get()
            ->keyBy('official_code');

        return view('formations-generales.picker', [
            'domains'        => $domains,
            'officialByCode' => $officialByCode,
            'centers'        => $centers,
            'centerId'       => $centerId,
        ]);
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
            'center_id'     => ['nullable', 'integer', 'exists:centers,id'],
        ]);

        // L'Admin Centre force son propre centre ; le Super Admin peut cibler.
        if ($authUser->isCenterAdmin()) {
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
                'center_id'     => $centerId,
                'official_code' => $data['official_code'],
                'is_official'   => true,
            ],
            [
                'domain'      => $data['official_code'],
                'name'        => $domain?->name ?? $data['official_code'],
                'code'        => sprintf('OF-%s-%s', strtoupper($center->code), strtoupper(str_replace('-', '_', $data['official_code']))),
                'status'      => 'active',
                'teacher_id'  => null,
                'currency'    => default_currency(),
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
            ->where('center_id', $formation->center_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('formations-generales.configure', [
            'formation'      => $formation,
            'domain'         => DomainRegistry::find($formation->official_code),
            'centerTeachers' => $centerTeachers,
        ]);
    }

    /**
     * Enregistre la configuration d'une formation officielle.
     * Ne crée JAMAIS de nouvelle ligne : agit uniquement sur la formation
     * officielle déjà identifiée par {formation} (404 sinon, cf.
     * authorizeOfficial). Le `center_id` n'est jamais accepté depuis le
     * client, ce qui empêche tout cross-center.
     */
    public function update(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorizeOfficial($request, $formation, 'update');

        $data = $request->validate([
            'description'    => ['nullable', 'string', 'max:2000'],
            'annual_price'   => ['nullable', 'numeric', 'min:0'],
            'currency'       => ['nullable', 'string', 'max:8'],
            'duration_hours' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'duration_weeks' => ['nullable', 'integer', 'min:0', 'max:520'],
            'status'         => ['required', 'in:active,inactive'],
            'teacher_id'     => ['nullable', 'integer', 'exists:teachers,id'],
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

        // On ne touche ni au nom, ni au code, ni au center_id, ni à
        // official_code, ni à is_official : la configuration reste strictement
        // cantonnée aux champs métier affichés.
        $formation->fill([
            'description'    => $data['description']    ?? $formation->description,
            'annual_price'   => $data['annual_price']   ?? null,
            'currency'       => $data['currency']       ?? $formation->currency,
            'duration_hours' => $data['duration_hours'] ?? null,
            'duration_weeks' => $data['duration_weeks'] ?? null,
            'status'         => $data['status'],
            'teacher_id'     => $data['teacher_id']     ?? null,
        ])->save();

        return redirect()
            ->route('formations-generales.show', $formation)
            ->with('success', __('La formation officielle a été mise à jour.'));
    }

    /**
     * Espace d'une formation générale officielle : responsable, classes (0/2),
     * indicateurs, et accès aux données héritées non encore migrées.
     */
    public function show(Request $request, Formation $formation): View
    {
        $this->authorizeOfficial($request, $formation, 'view');

        $formation->load(['center', 'teacher']);

        $classes = $formation->classes()->orderBy('code')->get();

        $studentsCount = $formation->enrollments()
            ->whereIn('status', ['accepted', 'pending', 'under_review'])
            ->distinct('student_id')
            ->count('student_id');

        // Formations héritées (spécialités) du même centre/domaine : non migrées.
        $legacyFormations = Formation::query()
            ->where('center_id', $formation->center_id)
            ->where('domain', $formation->official_code)
            ->where('is_official', false)
            ->withCount(['classes', 'enrollments'])
            ->orderBy('name')
            ->get();

        // Professeurs du centre, proposés pour la responsabilité (aucune auto-assignation).
        $centerTeachers = Teacher::query()
            ->where('center_id', $formation->center_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('formations-generales.show', [
            'formation'      => $formation,
            'domain'         => DomainRegistry::find($formation->official_code),
            'classes'        => $classes,
            'studentsCount'  => $studentsCount,
            'legacyFormations' => $legacyFormations,
            'centerTeachers' => $centerTeachers,
            'domainDashboardRoute' => $this->domainDashboardRoute($request, (string) $formation->official_code),
            'maxClasses'     => Formation::MAX_CLASSES,
        ]);
    }

    /**
     * Ajoute une classe à une formation officielle (plafond de 2, verrou de concurrence).
     */
    public function storeClass(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorizeOfficial($request, $formation, 'update');

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['required', 'string', 'max:100', 'unique:classes,code'],
            'capacity'    => ['nullable', 'integer', 'min:1'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Le centre est dérivé de la formation (jamais fourni par le client).
        $data['center_id'] = $formation->center_id;
        $data['status'] = $data['status'] ?? 'active';

        try {
            $class = $this->formations->createClass($formation, $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
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
     * Route du dashboard domaine historique selon le rôle (accès préservé).
     */
    private function domainDashboardRoute(Request $request, string $slug): string
    {
        $isSuper = $request->user()->isSuperAdmin();

        return $isSuper
            ? route('super-admin.domain.dashboard', $slug)
            : route('center-admin.domain.dashboard', $slug);
    }
}
