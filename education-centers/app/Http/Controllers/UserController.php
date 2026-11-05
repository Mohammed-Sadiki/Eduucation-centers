<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Center;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Liste des utilisateurs avec recherche, filtres et pagination.
     * Super Admin → tous | Admin Centre → uniquement son centre.
     * Seuls les rôles autorisés (super_admin, center_admin, teacher) sont affichés.
     * Les élèves ne doivent JAMAIS apparaître.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $authUser = $request->user();
        $allowedRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_CENTER_ADMIN, User::ROLE_SECRETARY, User::ROLE_TEACHER];

        $query = User::with('center')->whereIn('role', $allowedRoles);

        // Isolation multi-centres
        if ($authUser->isCenterAdmin()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Calcul du total des utilisateurs du périmètre (avant les filtres de recherche)
        $totalUsers = (clone $query)->count();

        // Filtre : recherche par nom ou email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtre : rôle (restreint aux rôles d'utilisateurs autorisés)
        if ($role = $request->input('role')) {
            if (in_array($role, $allowedRoles, true)) {
                $query->where('role', $role);
            }
        }

        // Filtre : statut
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtre : centre (Super Admin seulement)
        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('users.index', compact('users', 'centers', 'totalUsers'));
    }

    /**
     * Formulaire de création d'un utilisateur.
     * Important : depuis ce formulaire, il doit être possible de créer UNIQUEMENT des comptes Professeurs.
     * Nouveau parcours : une seule étape « Formation générale » (aucun domaine).
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        $authUser = $request->user();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        // Catalogue : uniquement les six formations générales officielles actives
        // du (ou des) centre(s) autorisé(s). Aucune sous-formation legacy.
        $centerIds = $authUser->isCenterAdmin()
            ? [(int) $authUser->center_id]
            : null;

        $formationsData = collect(\App\Models\Formation::selectionForCenters($centerIds))
            ->map(fn (array $f): array => [
                'id'        => $f['id'],
                'center_id' => $f['center_id'],
                'name'      => $f['name'],
                'code'      => $f['code'],
            ])
            ->values();

        return view('users.create', compact('centers', 'formationsData'));
    }

    /**
     * Enregistre un nouvel utilisateur.
     * Sécurité : création uniquement de compte Professeur, vérifié côté serveur.
     * Le centre est forcé automatiquement pour un Admin Centre.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $authUser = $request->user();
        if (! $authUser->isSuperAdmin()) {
            abort(403, __('Seul le Super Admin est autorisé à créer des comptes utilisateurs ou professeurs.'));
        }

        $data = $request->validated();
        $domainSlugs = $data['domains'] ?? [];
        unset($data['domains']);

        // Nouveau parcours : le formulaire ne transmet plus de `domain`.
        // On dérive la métadonnée technique depuis la formation officielle
        // sélectionnée, pour cohérence des scopes internes (HasDomainScope) et
        // des filtres historiques, sans réexposer le domaine à l'utilisateur.
        if (empty($data['domain']) && ! empty($data['formation_id'])) {
            $formation = \App\Models\Formation::find($data['formation_id']);
            if ($formation) {
                $data['domain'] = $formation->official_code ?: $formation->domain;
            }
        }

        // Rôle : le Super Admin peut créer un professeur, une secrétaire ou un responsable de centre
        if ($authUser->isSuperAdmin()) {
            $data['role'] = $request->input('role', User::ROLE_TEACHER);
        } else {
            $data['role'] = User::ROLE_TEACHER;
        }

        // Hasher le mot de passe
        $data['password'] = Hash::make($data['password']);

        // Sécurité multi-centres : forcer le center_id pour Admin Centre
        if ($authUser->isCenterAdmin()) {
            $data['center_id'] = $authUser->center_id;
        } else {
            // Super Admin : pour tout rôle autre que super_admin, un center_id est requis
            if ($data['role'] !== User::ROLE_SUPER_ADMIN && empty($data['center_id'])) {
                return back()->withErrors(['center_id' => __('Le centre de rattachement est obligatoire.')])->withInput();
            }
        }

        $user = User::create($data);

        // Si domain est spécifié et que domainSlugs est vide, utiliser domain
        if (! empty($data['domain'])) {
            $domainSlugs = array_unique(array_merge($domainSlugs, [$data['domain']]));
        }

        // Enregistrer les domaines d'enseignement sélectionnés
        if (! empty($domainSlugs)) {
            $user->syncTeachingDomains($domainSlugs);
        }

        return redirect()
            ->route('users.index')
            ->with('success', __('Le compte utilisateur « :name » a été créé avec succès.', ['name' => $user->name]));
    }

    /**
     * Affiche le détail d'un utilisateur.
     */
    public function show(Request $request, User $user): View
    {
        Gate::authorize('view', $user);

        return view('users.show', compact('user'));
    }

    /**
     * Formulaire de modification d'un utilisateur.
     */
    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        $authUser = $request->user();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();
        $allowedRoles = $this->getAllowedRoles($authUser, $user);

        // Catalogue : uniquement les six formations générales officielles actives.
        $centerIds = $authUser->isCenterAdmin()
            ? [(int) $authUser->center_id]
            : null;

        $formationsData = collect(\App\Models\Formation::selectionForCenters($centerIds))
            ->map(fn (array $f): array => [
                'id'        => $f['id'],
                'center_id' => $f['center_id'],
                'name'      => $f['name'],
                'code'      => $f['code'],
            ])
            ->values();

        return view('users.edit', compact('user', 'centers', 'allowedRoles', 'formationsData'));
    }

    /**
     * Met à jour un utilisateur.
     * Sécurité : le center_id est contrôlé côté serveur.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $authUser = $request->user();
        $data = $request->validated();
        $domainSlugs = $data['domains'] ?? null;
        unset($data['domains']);

        // Gestion du mot de passe (facultatif à la modification)
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // Sécurité multi-centres : Admin Centre ne peut pas changer le centre
        if ($authUser->isCenterAdmin()) {
            // Forcer le center_id du centre de l'admin
            $data['center_id'] = $authUser->center_id;

            // Admin Centre ne peut pas attribuer un rôle super_admin ou center_admin
            if (in_array($data['role'], [User::ROLE_SUPER_ADMIN, User::ROLE_CENTER_ADMIN])) {
                abort(403, __('Vous n\'êtes pas autorisé à attribuer ce rôle.'));
            }
        } else {
            // Super Admin : center_id = null si rôle super_admin
            if ($data['role'] === User::ROLE_SUPER_ADMIN) {
                $data['center_id'] = null;
            }
        }

        $user->update($data);

        // Si dossier Teacher associé, mettre à jour son domain et formation_id
        if ($user->teacher) {
            $teacherUpdates = [];
            if (isset($data['domain'])) {
                $teacherUpdates['domain'] = $data['domain'];
            }
            if (array_key_exists('formation_id', $data)) {
                $teacherUpdates['formation_id'] = $data['formation_id'];
            }
            if (! empty($teacherUpdates)) {
                $user->teacher->update($teacherUpdates);
            }
        }

        // Mise à jour des domaines d'enseignement si fournis ou si rôle professeur
        if (! empty($data['domain'])) {
            $domainSlugs = array_unique(array_merge((array) ($domainSlugs ?? []), [$data['domain']]));
        }

        if ($domainSlugs !== null) {
            $user->syncTeachingDomains($domainSlugs);
        }

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('L\'utilisateur « :name » a été mis à jour avec succès.', ['name' => $user->name]));
    }

    /**
     * Supprime un utilisateur (Super Admin uniquement, après contrôle).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $userName = $user->name;
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', __('L\'utilisateur « :userName » a été supprimé.', ['userName' => $userName]));
    }

    /**
     * Bascule le statut d'un utilisateur (active ↔ inactive).
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('toggleStatus', $user);

        $user->status = $user->isActive() ? User::STATUS_INACTIVE : User::STATUS_ACTIVE;
        $user->save();

        $label = $user->isActive() ? __('activé') : __('désactivé');

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('L\'utilisateur « :name » a été :label.', ['name' => $user->name, 'label' => $label]));
    }

    /**
     * Retourne les rôles autorisés pour la modification selon le rôle du demandeur.
     */
    private function getAllowedRoles(User $authUser, ?User $targetUser = null): array
    {
        if ($authUser->isSuperAdmin()) {
            return [
                User::ROLE_SUPER_ADMIN => __('Super Admin'),
                User::ROLE_CENTER_ADMIN => __('Responsable Centre'),
                User::ROLE_SECRETARY => __('Secrétaire'),
                User::ROLE_TEACHER => __('Professeur'),
            ];
        }

        // Admin Centre
        return [
            User::ROLE_TEACHER => __('Professeur'),
        ];
    }
}
