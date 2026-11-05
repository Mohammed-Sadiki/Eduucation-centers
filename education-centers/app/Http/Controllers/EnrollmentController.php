<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Models\Center;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\EnrollmentStatusService;
use App\Support\AcademicPerimeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentStatusService $statusService
    ) {}

    /**
     * Liste des inscriptions avec filtres.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Enrollment::class);

        $authUser = $request->user();
        $perimeter = $this->perimeter($authUser, $authUser->isSuperAdmin() ? (int) $request->input('center_id') : null);

        $query = Enrollment::query()->with([
            'center',
            'student.user',
            'formation',
            'classRoom',
        ]);

        // Isolation par centre
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Périmètre pédagogique unique : formations générales actives du centre
        // autorisé + domaine actif. Le tableau suit donc exactement les filtres.
        $query = $perimeter->applyTo($query);

        // Filtre : centre pour Super Admin
        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        // Filtre : recherche étudiant ou numéro d'inscription
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('registration_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere('student_number', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Filtre : statut
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtre : formation générale. Les données des anciennes sous-formations
        // rattachées à cette formation sont incluses (même jeu que le déroulant).
        $selectedFormationId = $request->input('formation_id') ? (int) $request->input('formation_id') : null;
        if ($selectedFormationId) {
            $query->whereIn('formation_id', $perimeter->formationIds($selectedFormationId));
        }

        // Filtre : classe
        if ($classId = $request->input('class_id')) {
            $query->where('class_id', $classId);
        }

        // Filtre : date de candidature
        if ($date = $request->input('date')) {
            $query->whereDate('application_date', $date);
        }

        $enrollments = $query->latest('id')->paginate(15)->withQueryString();

        // Données pour les filtres : une seule autorité. Les classes proposées
        // appartiennent à la formation sélectionnée quand elle l'est.
        $formations = $perimeter->formationsQuery()->get();
        $classes = $perimeter->classesForSelect(
            $selectedFormationId ? $perimeter->classesQuery($selectedFormationId)->pluck('id')->all() : null
        );
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('enrollments.index', compact('enrollments', 'formations', 'classes', 'centers'));
    }

    /**
     * Formulaire de création d'une inscription.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Enrollment::class);

        $authUser = $request->user();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        $selectedCenterId = ($authUser->isCenterAdmin() || $authUser->isSecretary())
            ? $authUser->center_id
            : ($request->input('center_id') ?? $centers->first()?->id);

        $students = Student::query()
            ->when($selectedCenterId, fn ($q) => $q->where('center_id', $selectedCenterId))
            ->where('status', 'active')
            ->orderBy('last_name')
            ->get();

        // Nouveau parcours : seules les formations générales actives du centre
        // sont proposées à l'inscription. Les sous-formations héritées ne sont
        // jamais un choix : leurs classes apparaissent sous leur formation
        // générale, et le champ « classe » suit la formation sélectionnée.
        $perimeter = $this->perimeter($authUser, $selectedCenterId ? (int) $selectedCenterId : null);
        $formations = $perimeter->formationsQuery()->get();
        $classes = $perimeter->classesForSelect(activeOnly: true);

        $preselectedStudentId = $request->input('student_id');
        $preselectedFormationId = $request->input('formation_id');
        $preselectedClassId = $request->input('class_id');

        return view('enrollments.create', compact(
            'centers',
            'students',
            'formations',
            'classes',
            'selectedCenterId',
            'preselectedStudentId',
            'preselectedFormationId',
            'preselectedClassId'
        ));
    }

    /**
     * Enregistrer une nouvelle inscription.
     */
    public function store(StoreEnrollmentRequest $request): RedirectResponse
    {
        $authUser = $request->user();

        // Détermination stricte du center_id côté serveur
        $centerId = ($authUser->isCenterAdmin() || $authUser->isSecretary()) ? $authUser->center_id : (int) $request->input('center_id');
        $center = Center::findOrFail($centerId);

        $data = $request->validated();
        $data['center_id'] = $centerId;
        $data['status'] = Enrollment::STATUS_PENDING; // Statut initial obligatoire
        $data['registration_number'] = Enrollment::generateRegistrationNumber($center);
        $data['created_by'] = $authUser->id;

        $enrollment = Enrollment::create($data);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', __('L\'inscription :registration_number a été créée avec succès en attente de traitement.', ['registration_number' => $enrollment->registration_number]));
    }

    /**
     * Fiche d'une inscription.
     */
    public function show(Enrollment $enrollment): View
    {
        Gate::authorize('view', $enrollment);

        $enrollment->load([
            'center',
            'student.user',
            'formation',
            'classRoom.teachers.user',
        ]);

        // Classes disponibles pour l'affectation lors du passage à 'accepted' :
        // celles de la formation générale de l'inscription (alias historiques
        // inclus), dans le centre de l'inscription.
        $availableClasses = $this->perimeter(request()->user(), $enrollment->center_id)
            ->classesQuery($enrollment->formation_id)
            ->where('status', 'active')
            ->get();

        return view('enrollments.show', compact('enrollment', 'availableClasses'));
    }

    /**
     * Formulaire d'édition.
     */
    public function edit(Enrollment $enrollment): View
    {
        Gate::authorize('update', $enrollment);

        $enrollment->load(['center', 'student', 'formation', 'classRoom']);
        $authUser = request()->user();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        $students = Student::where('center_id', $enrollment->center_id)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->get();

        // Édition : le choix reste limité aux formations générales actives du
        // centre. L'inscription héritée est présentée par sa formation générale
        // (une ancienne sous-formation n'est jamais une option).
        $perimeter = $this->perimeter($authUser, $enrollment->center_id);
        $formations = $perimeter->formationsQuery()->get();
        $selectedFormationId = AcademicPerimeter::generalFormationId($enrollment->formation_id) ?? $enrollment->formation_id;

        // Classes de la formation de l'inscription : celles de sa formation
        // générale, alias historiques compris.
        $classes = $perimeter->classesForSelect(
            $perimeter->classesQuery($enrollment->formation_id)->pluck('id')->all()
        );

        return view('enrollments.edit', compact('enrollment', 'centers', 'students', 'formations', 'classes', 'selectedFormationId'));
    }

    /**
     * Mettre à jour une inscription.
     */
    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment): RedirectResponse
    {
        Gate::authorize('update', $enrollment);

        $authUser = $request->user();
        $data = $request->validated();

        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            unset($data['center_id']);
        }

        $enrollment->update($data);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', __('L\'inscription :registration_number a été mise à jour.', ['registration_number' => $enrollment->registration_number]));
    }

    /**
     * Transition de statut via le service métier.
     */
    public function changeStatus(Request $request, Enrollment $enrollment): RedirectResponse
    {
        Gate::authorize('changeStatus', $enrollment);

        $request->validate([
            'status' => ['required', 'string', 'in:pending,under_review,accepted,rejected,cancelled'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);

        $newStatus = $request->input('status');
        $decisionNotes = $request->input('decision_notes');
        $assignClassId = $request->filled('class_id') ? (int) $request->input('class_id') : null;

        $this->statusService->changeStatus($enrollment, $newStatus, $decisionNotes, $assignClassId);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', __('Le statut de l\'inscription a été mis à jour vers \\":status_label\\".', ['status_label' => $enrollment->fresh()->status_label]));
    }

    /**
     * Supprimer une inscription.
     */
    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        Gate::authorize('delete', $enrollment);

        $regNumber = $enrollment->registration_number;
        $enrollment->delete();

        return redirect()
            ->route('enrollments.index')
            ->with('success', __('L\'inscription :regNumber a été supprimée avec succès.', ['regNumber' => $regNumber]));
    }
}
