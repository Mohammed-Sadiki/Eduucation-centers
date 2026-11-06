<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormationRequest;
use App\Http\Requests\UpdateFormationRequest;
use App\Models\Center;
use App\Models\Formation;
use App\Support\DomainRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FormationController extends Controller
{
    /**
     * Liste des formations avec filtres et pagination.
     * Super Admin -> tous les centres | Admin Centre -> uniquement son centre.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Formation::class);

        $authUser = $request->user();
        $query = Formation::with('center');

        // Isolation multi-centres stricte
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Filtrage par espace de formation actif + architecture obligatoire :
        // la liste ne présente que les formations du fonctionnement courant
        // (formations générales actives), jamais les anciennes sous-formations
        // devenues alias techniques d'une formation générale. Autorité unique.
        $query = $this->perimeter($authUser)->applyTo($query);

        if ($formationId = $request->input('formation_id')) {
            $query->where('id', $formationId);
        }

        // Filtre : recherche par nom
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filtre : code
        if ($code = $request->input('code')) {
            $query->where('code', 'like', "%{$code}%");
        }

        // Filtre : statut
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtre : centre (Super Admin seulement)
        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        $formations = $query->latest('id')->paginate(15)->withQueryString();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('formations.index', compact('formations', 'centers'));
    }

    /**
     * Formulaire indépendant de création d'une formation, centralisé depuis la
     * page « Domaines de formation ». Cascade : centre (Super Admin) → domaine.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Formation::class);

        $authUser = $request->user();

        // Liste des centres : sélectionnable uniquement par le Super Admin.
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        // Catalogue des domaines (commun aux centres, source de vérité `domains`,
        // repli canonique si la table est vide — ex. suite de tests).
        $domains = ($authUser->isCenterAdmin() || $authUser->isSecretary())
            ? DomainRegistry::visibleForCenter((int) $authUser->center_id)
            : DomainRegistry::visible();

        return view('formations.create', compact('centers', 'domains'));
    }

    /**
     * Enregistrer une nouvelle formation.
     */
    public function store(StoreFormationRequest $request): RedirectResponse
    {
        $authUser = $request->user();

        // Détermination stricte du center_id côté serveur (jamais faire confiance au client)
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $centerId = (int) $authUser->center_id;
        } else {
            $inputCenter = $request->input('center_id');
            $centerId = ($inputCenter === 'all' || $inputCenter === '0' || $inputCenter === null) ? null : (int) $inputCenter;
        }

        $data = $request->validated();
        $data['center_id'] = $centerId;

        $formation = Formation::create($data);

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', __('La formation « :name » (:code) a été créée avec succès.', ['name' => $formation->name, 'code' => $formation->code]));
    }

    /**
     * Consulter une formation.
     */
    public function show(Formation $formation): View
    {
        Gate::authorize('view', $formation);

        $formation->load('center');

        return view('formations.show', compact('formation'));
    }

    /**
     * Formulaire d'édition d'une formation.
     */
    public function edit(Formation $formation): View
    {
        Gate::authorize('update', $formation);

        $formation->load('center');
        $centers = auth()->user()->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('formations.edit', compact('formation', 'centers'));
    }

    /**
     * Mettre à jour une formation.
     */
    public function update(UpdateFormationRequest $request, Formation $formation): RedirectResponse
    {
        $authUser = $request->user();
        $data = $request->validated();

        if ($authUser->isSuperAdmin() && $request->has('center_id')) {
            $inputCenter = $request->input('center_id');
            $data['center_id'] = ($inputCenter === 'all' || $inputCenter === '0' || $inputCenter === null || $inputCenter === '')
                ? null
                : (int) $inputCenter;
        }

        $formation->update($data);

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', __('La formation « :name » a été mise à jour avec succès.', ['name' => $formation->name]));
    }

    /**
     * Activer ou désactiver une formation.
     */
    public function toggleStatus(Request $request, Formation $formation): RedirectResponse
    {
        Gate::authorize('toggleStatus', $formation);

        $newStatus = $formation->isActive() ? 'inactive' : 'active';
        $formation->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? __('La formation « :name » a été activée.', ['name' => $formation->name])
            : __('La formation « :name » a été désactivée.', ['name' => $formation->name]);

        return back()->with('success', $message);
    }

    /**
     * Supprimer une formation si aucune donnée dépendante ne l'empêche.
     */
    public function destroy(Formation $formation): RedirectResponse
    {
        Gate::authorize('delete', $formation);

        // Vérification de sécurité pour les étapes futures (classes, inscriptions, etc.)
        // À cette étape, pas encore de relation classes/enrollments
        if (method_exists($formation, 'classes') && $formation->classes()->exists()) {
            return back()->with('error', __('Impossible de supprimer la formation : des classes y sont associées.'));
        }

        $name = $formation->name;
        $formation->delete();

        return redirect()
            ->route('formations.index')
            ->with('success', __('La formation « :name » a été supprimée avec succès.', ['name' => $name]));
    }
}
