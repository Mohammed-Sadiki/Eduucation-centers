<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeacherRequest;
use App\Http\Requests\UpdateTeacherRequest;
use App\Models\Center;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeacherController extends Controller
{
    /**
     * Liste des dossiers professeurs avec recherche, filtres et pagination.
     * Super Admin -> tous | Admin Centre -> uniquement son centre.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Teacher::class);

        $authUser = $request->user();
        $query = Teacher::with(['user', 'center']);

        // Isolation multi-centres stricte
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Filtrage par domaine actif
        $query = $this->applyDomainScope($query);

        if ($formationId = $request->input('formation_id')) {
            $query->where(function ($q) use ($formationId) {
                $q->whereHas('responsibleFormations', fn ($rf) => $rf->where('id', $formationId))
                    ->orWhereHas('classRooms', fn ($cr) => $cr->where('formation_id', $formationId));
            });
        }

        // Filtre : recherche par numéro professeur
        if ($teacherNumber = $request->input('teacher_number')) {
            $query->where('teacher_number', 'like', "%{$teacherNumber}%");
        }

        // Filtre : recherche par nom / prénom
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Filtre : email (via user)
        if ($email = $request->input('email')) {
            $query->whereHas('user', function ($q) use ($email) {
                $q->where('email', 'like', "%{$email}%");
            });
        }

        // Filtre : statut
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtre : centre (Super Admin seulement)
        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        $teachers = $query->latest('id')->paginate(15)->withQueryString();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('teachers.index', compact('teachers', 'centers'));
    }

    /**
     * Formulaire de création d'un dossier professeur.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Teacher::class);

        $authUser = $request->user();

        // Récupérer les utilisateurs ayant le rôle 'teacher' sans dossier existant
        $usersQuery = User::where('role', User::ROLE_TEACHER)
            ->whereDoesntHave('teacher')
            ->orderBy('name');

        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $usersQuery->where('center_id', $authUser->center_id);
            $centers = collect();
        } else {
            // Super Admin
            $centers = Center::orderBy('name')->get();
        }

        $availableUsers = $usersQuery->get();

        return view('teachers.create', compact('availableUsers', 'centers'));
    }

    /**
     * Enregistrer un nouveau dossier professeur.
     */
    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        Gate::authorize('create', Teacher::class);

        $authUser = $request->user();
        $targetUser = User::findOrFail($request->validated('user_id'));

        // Détermination stricte du center_id côté serveur
        $centerId = ($authUser->isCenterAdmin() || $authUser->isSecretary()) ? $authUser->center_id : $targetUser->center_id;
        $center = Center::findOrFail($centerId);

        // Génération automatique du teacher_number côté serveur (ex: BER-T-000001)
        $teacherNumber = Teacher::generateTeacherNumber($center);

        $data = $request->validated();
        $data['center_id'] = $centerId;
        $data['teacher_number'] = $teacherNumber;

        $teacher = Teacher::create($data);

        return redirect()
            ->route('teachers.show', $teacher)
            ->with('success', __('Le dossier professeur :teacher_number (:full_name) a été créé avec succès.', ['teacher_number' => $teacher->teacher_number, 'full_name' => $teacher->full_name]));
    }

    /**
     * Consulter un dossier professeur.
     */
    public function show(Teacher $teacher): View
    {
        Gate::authorize('view', $teacher);

        $teacher->load(['user', 'center']);

        return view('teachers.show', compact('teacher'));
    }

    /**
     * Formulaire d'édition d'un dossier professeur.
     */
    public function edit(Teacher $teacher): View
    {
        Gate::authorize('update', $teacher);

        $teacher->load(['user', 'center']);

        return view('teachers.edit', compact('teacher'));
    }

    /**
     * Mettre à jour un dossier professeur.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $teacher->update($request->validated());

        return redirect()
            ->route('teachers.show', $teacher)
            ->with('success', __('Le dossier professeur :teacher_number a été mis à jour avec succès.', ['teacher_number' => $teacher->teacher_number]));
    }

    /**
     * Activer ou désactiver un dossier professeur.
     */
    public function toggleStatus(Request $request, Teacher $teacher): RedirectResponse
    {
        Gate::authorize('toggleStatus', $teacher);

        $newStatus = $teacher->isActive() ? 'inactive' : 'active';
        $teacher->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? __('Le dossier professeur :teacher_number a été activé.', ['teacher_number' => $teacher->teacher_number])
            : __('Le dossier professeur :teacher_number a été désactivé.', ['teacher_number' => $teacher->teacher_number]);

        return back()->with('success', $message);
    }

    /**
     * Supprimer un dossier professeur si nécessaire.
     */
    public function destroy(Teacher $teacher): RedirectResponse
    {
        Gate::authorize('delete', $teacher);

        $teacherNumber = $teacher->teacher_number;
        $teacher->delete();

        return redirect()
            ->route('teachers.index')
            ->with('success', __('Le dossier professeur :teacherNumber a été supprimé.', ['teacherNumber' => $teacherNumber]));
    }
}
