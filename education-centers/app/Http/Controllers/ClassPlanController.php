<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;

class ClassPlanController extends Controller
{
    /**
     * Affiche la page principale du plan de classe.
     * Charge les professeurs selon le rôle de l'utilisateur connecté.
     */
    public function index(Request $request): View
    {
        $authUser = $request->user();

        // Charger les professeurs scoped par centre pour Admin Centre
        $teachersQuery = Teacher::with(['user:id,name,center_id', 'center:id,name'])
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($authUser->isCenterAdmin()) {
            $teachersQuery->where('center_id', $authUser->center_id);
        }

        $teachers = $teachersQuery->get();

        // Professeur sélectionné
        $selectedTeacher = null;
        $classes = collect();
        $selectedClass = null;
        $students = collect();

        if ($request->filled('teacher_id')) {
            $teacherId = (int) $request->input('teacher_id');

            // Vérification que le professeur appartient bien au périmètre autorisé
            $selectedTeacher = $teachers->firstWhere('id', $teacherId);

            if ($selectedTeacher) {
                // Classes du professeur
                $classesQuery = $selectedTeacher->classes()
                    ->with('formation:id,name,domain')
                    ->orderBy('name');

                $classes = $classesQuery->get();

                // Sélection automatique si une seule classe, sinon via paramètre
                if ($classes->count() === 1) {
                    $selectedClass = $classes->first();
                } elseif ($request->filled('class_id')) {
                    $classId = (int) $request->input('class_id');
                    $selectedClass = $classes->firstWhere('id', $classId);
                }

                // Élèves de la classe sélectionnée
                if ($selectedClass) {
                    // Sécurité : vérifier que la classe appartient au même centre que le prof (pour Admin Centre)
                    if ($authUser->isCenterAdmin() && $selectedClass->center_id !== $authUser->center_id) {
                        abort(403);
                    }

                    $students = Enrollment::with('student:id,first_name,last_name,profile_photo_path,status')
                        ->where('class_id', $selectedClass->id)
                        ->where('status', Enrollment::STATUS_ACCEPTED)
                        ->select('id', 'student_id', 'class_id', 'status')
                        ->get()
                        ->pluck('student')
                        ->filter()
                        ->unique('id')
                        ->values();
                }
            }
        }

        return view('class-plan.index', compact(
            'teachers',
            'selectedTeacher',
            'classes',
            'selectedClass',
            'students'
        ));
    }

    /**
     * API JSON : retourne les classes d'un professeur donné.
     * Utilisé pour le chargement dynamique côté frontend.
     */
    public function getTeacherClasses(Request $request, int $teacherId): JsonResponse
    {
        $authUser = $request->user();

        $teacherQuery = Teacher::where('id', $teacherId)->where('status', 'active');

        // Restriction centre pour Admin Centre
        if ($authUser->isCenterAdmin()) {
            $teacherQuery->where('center_id', $authUser->center_id);
        }

        $teacher = $teacherQuery->first();

        if (! $teacher) {
            return response()->json(['error' => 'Professeur introuvable.'], 404);
        }

        $classes = $teacher->classes()
            ->with('formation:id,name,domain')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'formation' => $c->formation?->name,
                'status' => $c->status,
            ]);

        return response()->json(['classes' => $classes]);
    }

    /**
     * API JSON : retourne les élèves d'une classe donnée.
     */
    public function getClassStudents(Request $request, int $classId): JsonResponse
    {
        $authUser = $request->user();

        $classRoom = ClassRoom::find($classId);

        if (! $classRoom) {
            return response()->json(['error' => 'Classe introuvable.'], 404);
        }

        // Sécurité multi-centres
        if ($authUser->isCenterAdmin() && $classRoom->center_id !== $authUser->center_id) {
            return response()->json(['error' => 'Accès interdit.'], 403);
        }

        $students = Enrollment::with('student:id,first_name,last_name,profile_photo_path,status')
            ->where('class_id', $classId)
            ->where('status', Enrollment::STATUS_ACCEPTED)
            ->select('id', 'student_id', 'class_id', 'status')
            ->get()
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($s) => [
                'id' => $s->id,
                'full_name' => trim("{$s->first_name} {$s->last_name}"),
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                // Même mécanisme que la fiche de l'élève : le fichier vit sur le
                // disque privé, donc asset('storage/…') serait une image cassée.
                // profilePhotoUrl() null en l'absence de photo valide → l'avatar
                // par défaut est affiché.
                'profile_photo_url' => $s->profilePhotoUrl(),
                'status' => $s->status,
            ]);

        return response()->json(['students' => $students, 'count' => $students->count()]);
    }
}
