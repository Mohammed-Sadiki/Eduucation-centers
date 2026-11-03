<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Formation;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClassController extends Controller
{
    /**
     * Liste des classes avec filtres et pagination.
     * Super Admin -> tous | Admin Centre -> uniquement son centre.
     *
     * Nouveau parcours « Centre → Formation → Classe » : le sélecteur de
     * filtre n'expose plus que les formations générales officielles actives
     * du périmètre autorisé. Les anciennes sous-formations legacy restent
     * accessibles pour la lecture historique, mais ne sont plus proposées.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ClassRoom::class);

        $authUser = $request->user();
        $query = ClassRoom::with(['center', 'formation']);

        // Isolation multi-centres stricte
        if ($authUser->isCenterAdmin()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Filtrage par domaine actif
        $query = $this->applyDomainScope($query);

        // Filtre : recherche par nom
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filtre : code
        if ($code = $request->input('code')) {
            $query->where('code', 'like', "%{$code}%");
        }

        // Filtre : formation
        if ($formationId = $request->input('formation_id')) {
            $query->where('formation_id', $formationId);
        }

        // Filtre : statut
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtre : centre (Super Admin seulement)
        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        $classes = $query->latest('id')->paginate(15)->withQueryString();

        // Sélecteur de filtre : uniquement les formations officielles actives.
        $centerIds = $authUser->isCenterAdmin() ? [(int) $authUser->center_id] : null;
        $formations = collect(\App\Models\Formation::selectionForCenters($centerIds))
            ->map(fn (array $f): object => (object) ['id' => $f['id'], 'name' => $f['name']])
            ->values();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('classes.index', compact('classes', 'centers', 'formations'));
    }

    /**
     * Formulaire de création d'une classe.
     * Seules les six formations générales officielles actives peuvent accueillir
     * une nouvelle classe (règle métier §4 : max. 2 classes par formation).
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', ClassRoom::class);

        $authUser = $request->user();

        // Catalogue réduit aux six formations officielles actives du périmètre.
        $formationQuery = Formation::query()
            ->where('is_official', true)
            ->where('status', 'active')
            ->orderBy('name');
        if ($authUser->isCenterAdmin()) {
            $formationQuery->where('center_id', $authUser->center_id);
        } else {
            $formationQuery->with('center');
        }
        $formations = $formationQuery->get();

        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('classes.create', compact('centers', 'formations'));
    }

    /**
     * Enregistrer une nouvelle classe.
     */
    public function store(StoreClassRequest $request): RedirectResponse
    {
        $authUser = $request->user();

        // Détermination stricte du center_id côté serveur (jamais faire confiance au client)
        $centerId = $authUser->isCenterAdmin() ? $authUser->center_id : (int) $request->input('center_id');

        $data = $request->validated();
        $data['center_id'] = $centerId;

        $classRoom = ClassRoom::create($data);

        return redirect()
            ->route('classes.show', $classRoom)
            ->with('success', __('La classe \\":name\\" (:code) a été créée avec succès.', ['name' => $classRoom->name, 'code' => $classRoom->code]));
    }

    /**
     * Consulter une classe.
     */
    public function show(ClassRoom $class): View
    {
        Gate::authorize('view', $class);

        $class->load(['center', 'formation', 'teachers.user']);

        // Récupérer les professeurs actifs du centre qui ne sont pas encore affectés à cette classe
        $availableTeachers = Teacher::where('center_id', $class->center_id)
            ->where('status', 'active')
            ->whereNotIn('id', $class->teachers->pluck('id'))
            ->orderBy('last_name')
            ->get();

        return view('classes.show', compact('class', 'availableTeachers'));
    }

    /**
     * Formulaire d'édition d'une classe.
     * Filtre : uniquement les formations officielles actives du centre, plus
     * la formation courante si elle est historique (legacy) pour ne pas la
     * perdre silencieusement lors d'une modification d'une classe existante.
     */
    public function edit(ClassRoom $class): View
    {
        Gate::authorize('update', $class);

        $authUser = request()->user();
        $class->load(['center', 'formation']);

        $centerId = $authUser->isSuperAdmin() ? $class->center_id : $authUser->center_id;

        $formations = Formation::query()
            ->where('center_id', $centerId)
            ->where(function ($q) use ($class) {
                $q->where('is_official', true)
                    ->orWhere('id', $class->formation_id);
            })
            ->orderByDesc('is_official')
            ->orderBy('name')
            ->get();

        return view('classes.edit', compact('class', 'formations'));
    }

    /**
     * Mettre à jour une classe.
     */
    public function update(UpdateClassRequest $request, ClassRoom $class): RedirectResponse
    {
        $class->update($request->validated());

        return redirect()
            ->route('classes.show', $class)
            ->with('success', __('La classe \\":name\\" a été mise à jour avec succès.', ['name' => $class->name]));
    }

    /**
     * Activer ou désactiver une classe.
     */
    public function toggleStatus(Request $request, ClassRoom $class): RedirectResponse
    {
        Gate::authorize('toggleStatus', $class);

        $newStatus = $class->isActive() ? 'inactive' : 'active';
        $class->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? __('La classe \\":name\\" a été activée.', ['name' => $class->name])
            : __('La classe \\":name\\" a été désactivée.', ['name' => $class->name]);

        return back()->with('success', $message);
    }

    /**
     * Supprimer une classe si aucune dépendance ne l'empêche.
     */
    public function destroy(ClassRoom $class): RedirectResponse
    {
        Gate::authorize('delete', $class);

        // Protection contre les dépendances futures (inscriptions, présences, etc.)
        if (method_exists($class, 'enrollments') && $class->enrollments()->exists()) {
            return back()->with('error', __('Impossible de supprimer la classe : des inscriptions y sont associées.'));
        }

        $name = $class->name;
        $class->delete();

        return redirect()
            ->route('classes.index')
            ->with('success', __('La classe \\":name\\" a été supprimée avec succès.', ['name' => $name]));
    }
}
