<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Formation;
use App\Models\Teacher;
use App\Support\AcademicPerimeter;
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
        $perimeter = $this->perimeter($authUser);
        $query = ClassRoom::with(['center', 'formation']);

        // Isolation multi-centres stricte
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Espace de formation actif + formations générales uniquement : la même
        // autorité que les menus déroulants ci-dessous, pour que le tableau et
        // les filtres décrivent toujours le même périmètre.
        $query = $perimeter->applyTo($query);

        // Filtre : recherche par nom
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filtre : code
        if ($code = $request->input('code')) {
            $query->where('code', 'like', "%{$code}%");
        }

        // Filtre : formation générale (les classes rattachées à ses alias
        // historiques font partie de la même formation).
        if ($formationId = $request->input('formation_id')) {
            $query->whereIn('formation_id', $perimeter->formationIds((int) $formationId));
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

        // Sélecteur de filtre : uniquement les formations générales actives.
        $formations = $perimeter->formationsQuery()->get();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('classes.index', compact('classes', 'centers', 'formations'));
    }

    /**
     * Formulaire de création d'une classe.
     * Seules les formations générales actives peuvent accueillir une nouvelle
     * classe (règle métier : maximum 2 classes par formation).
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', ClassRoom::class);

        $authUser = $request->user();

        // Catalogue des formations générales du centre autorisé. Toute la liste
        // est proposée, l'espace de formation actif ne bornant pas un formulaire
        // de création (comportement conservé).
        $formationQuery = $this->perimeter($authUser)->withoutActiveDomain()->formationsQuery();
        if ($authUser->isSuperAdmin()) {
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
        $centerId = ($authUser->isCenterAdmin() || $authUser->isSecretary()) ? $authUser->center_id : (int) $request->input('center_id');

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
     * Catalogue : les formations générales actives du centre de la classe. Une
     * classe encore rattachée à une ancienne sous-formation est présentée par sa
     * formation générale équivalente (même réalité métier) ; si aucune formation
     * générale n'existe pour elle, sa formation courante est conservée dans la
     * liste pour ne pas la perdre silencieusement.
     */
    public function edit(ClassRoom $class): View
    {
        Gate::authorize('update', $class);

        $authUser = request()->user();
        $class->load(['center', 'formation']);

        $centerId = $authUser->isSuperAdmin() ? $class->center_id : $authUser->center_id;

        $perimeter = $this->perimeter($authUser, (int) $centerId)->withoutActiveDomain();
        $formations = $perimeter->formationsQuery()->get();

        $currentFormationId = AcademicPerimeter::generalFormationId($class->formation_id) ?? $class->formation_id;

        if (! $formations->contains('id', $currentFormationId)) {
            $current = Formation::query()->find($currentFormationId)
                ?? Formation::query()->find($class->formation_id);

            if ($current) {
                $formations = $formations->push($current);
            }
        }

        return view('classes.edit', compact('class', 'formations', 'currentFormationId'));
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
