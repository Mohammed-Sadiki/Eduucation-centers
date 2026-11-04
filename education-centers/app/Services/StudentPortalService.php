<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\ClassRoom;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Formation;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Support\AcademicPerimeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Contexte et requêtes de l'espace élève.
 *
 * Toutes les méthodes partent de l'utilisateur authentifié puis du dossier
 * étudiant lié. Aucun identifiant fourni par le navigateur n'est utilisé pour
 * déterminer l'élève, son centre ou ses classes.
 */
class StudentPortalService
{
    public function __construct(protected PersonalFinanceService $finance) {}

    /**
     * Statuts d'évaluation visibles par un élève.
     */
    public const PUBLISHED_EVALUATION_STATUSES = ['published', 'closed'];

    /**
     * Résout le dossier étudiant de l'utilisateur connecté.
     *
     * Le centre du dossier doit correspondre au centre du compte utilisateur.
     * Un élève sans dossier (données incomplètes) obtient un portail vide au
     * lieu d'un accès aux données d'un autre étudiant.
     */
    public function studentFor(User $user): ?Student
    {
        abort_unless($user->isStudent(), 403);

        if (! $user->center_id) {
            return null;
        }

        return Student::query()
            ->where('user_id', $user->id)
            ->where('center_id', $user->center_id)
            ->with('center')
            ->first();
    }

    /**
     * Toutes les inscriptions réelles de l'élève (tous statuts confondus).
     *
     * @return EloquentCollection<int, Enrollment>
     */
    public function enrollments(?Student $student): EloquentCollection
    {
        if (! $student) {
            return new EloquentCollection;
        }

        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('center_id', $student->center_id)
            ->with([
                'formation.domainModel',
                'classRoom.formation',
                'classRoom.teachers.user',
                'center',
            ])
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->formation !== null
                && (int) $enrollment->formation->center_id === (int) $student->center_id)
            ->values();
    }

    /**
     * Inscriptions acceptées et cohérentes avec la classe/formation/centre.
     *
     * @param  EloquentCollection<int, Enrollment>|null  $enrollments
     * @return EloquentCollection<int, Enrollment>
     */
    public function acceptedEnrollments(?Student $student, ?EloquentCollection $enrollments = null): EloquentCollection
    {
        if (! $student) {
            return new EloquentCollection;
        }

        $source = $enrollments ?? $this->enrollments($student);

        return $source
            ->filter(function (Enrollment $enrollment) use ($student): bool {
                $class = $enrollment->classRoom;

                return $enrollment->status === Enrollment::STATUS_ACCEPTED
                    && $enrollment->class_id !== null
                    && $class !== null
                    && (int) $class->center_id === (int) $student->center_id
                    // L'inscription et la classe doivent décrire la même
                    // formation générale : une ancienne sous-formation et sa
                    // formation générale sont équivalentes (transition de données).
                    && AcademicPerimeter::sameGeneralFormation($class->formation_id, $enrollment->formation_id);
            })
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Enrollment>  $acceptedEnrollments
     * @return Collection<int, int>
     */
    public function classIds(EloquentCollection $acceptedEnrollments): Collection
    {
        return $acceptedEnrollments
            ->pluck('class_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Enrollment>  $acceptedEnrollments
     * @return Collection<int, int>
     */
    public function formationIds(EloquentCollection $acceptedEnrollments): Collection
    {
        return $acceptedEnrollments
            ->pluck('formation_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Enrollment>  $acceptedEnrollments
     * @return Collection<int, ClassRoom>
     */
    public function classes(EloquentCollection $acceptedEnrollments): Collection
    {
        return $acceptedEnrollments
            ->pluck('classRoom')
            ->filter()
            ->unique(fn ($class): int => (int) $class->id)
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Enrollment>  $acceptedEnrollments
     * @return Collection<int, Formation>
     */
    public function formations(EloquentCollection $acceptedEnrollments): Collection
    {
        return $acceptedEnrollments
            ->pluck('formation')
            ->filter()
            ->unique(fn ($formation): int => (int) $formation->id)
            ->sortBy('name')
            ->values();
    }

    /**
     * Requête de base des séances de l'élève.
     *
     * @param  Collection<int, int>  $classIds
     */
    public function schedulesQuery(?Student $student, Collection $classIds): Builder
    {
        if (! $student || $classIds->isEmpty()) {
            return Schedule::query()->whereRaw('1 = 0');
        }

        return Schedule::query()
            ->where('center_id', $student->center_id)
            ->whereIn('class_id', $classIds->all());
    }

    /**
     * Requête de base des présences de l'élève.
     *
     * @param  Collection<int, int>  $classIds
     */
    public function attendancesQuery(?Student $student, Collection $classIds): Builder
    {
        if (! $student || $classIds->isEmpty()) {
            return Attendance::query()->whereRaw('1 = 0');
        }

        return Attendance::query()
            ->where('student_id', $student->id)
            ->where('center_id', $student->center_id)
            ->whereIn('class_id', $classIds->all());
    }

    /**
     * Requête de base des évaluations publiées de l'élève.
     *
     * @param  Collection<int, int>  $classIds
     */
    public function publishedEvaluationsQuery(?Student $student, Collection $classIds): Builder
    {
        if (! $student || $classIds->isEmpty()) {
            return Evaluation::query()->whereRaw('1 = 0');
        }

        return Evaluation::query()
            ->where('center_id', $student->center_id)
            ->whereIn('class_id', $classIds->all())
            ->whereIn('status', self::PUBLISHED_EVALUATION_STATUSES);
    }

    /**
     * Requête de base des notes personnelles publiées de l'élève.
     *
     * @param  Collection<int, int>  $classIds
     */
    public function publishedGradesQuery(?Student $student, Collection $classIds): Builder
    {
        if (! $student || $classIds->isEmpty()) {
            return Grade::query()->whereRaw('1 = 0');
        }

        return Grade::query()
            ->where('student_id', $student->id)
            ->where('center_id', $student->center_id)
            ->whereHas('evaluation', function (Builder $query) use ($student, $classIds): void {
                $query->where('center_id', $student->center_id)
                    ->whereIn('class_id', $classIds->all())
                    ->whereIn('status', self::PUBLISHED_EVALUATION_STATUSES);
            });
    }

    /**
     * Documents actifs de l'élève.
     */
    public function documentsQuery(?Student $student): Builder
    {
        if (! $student) {
            return Document::query()->whereRaw('1 = 0');
        }

        return Document::query()
            ->where('student_id', $student->id)
            ->where('center_id', $student->center_id)
            ->where('status', 'active');
    }

    /**
     * Certificats émis de l'élève.
     */
    public function certificatesQuery(?Student $student): Builder
    {
        if (! $student) {
            return Certificate::query()->whereRaw('1 = 0');
        }

        return Certificate::query()
            ->where('student_id', $student->id)
            ->where('center_id', $student->center_id)
            ->where('status', 'issued');
    }

    /**
     * Statistiques personnelles de présence.
     *
     * @param  Collection<int, int>  $classIds
     * @return array{present:int, absent:int, late:int, total:int, rate:float}
     */
    public function attendanceStats(?Student $student, Collection $classIds): array
    {
        $counts = $this->attendancesQuery($student, $classIds)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $late = (int) ($counts['late'] ?? 0);
        $total = $present + $absent + $late;
        $rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0.0;

        return compact('present', 'absent', 'late', 'total', 'rate');
    }

    /**
     * Moyenne personnelle normalisée sur 20, pondérée par les coefficients.
     *
     * @param  Collection<int, int>  $classIds
     */
    public function studentAverage(?Student $student, Collection $classIds): ?float
    {
        $grades = $this->publishedGradesQuery($student, $classIds)
            ->with('evaluation:id,max_grade,coefficient')
            ->get();

        $weightedTotal = 0.0;
        $totalWeight = 0.0;

        foreach ($grades as $grade) {
            $maxGrade = (float) ($grade->evaluation?->max_grade ?? 0);

            if ($grade->is_absent || $grade->grade === null || $maxGrade <= 0) {
                continue;
            }

            $coefficient = max(0.1, (float) ($grade->evaluation?->coefficient ?? 1));
            $weightedTotal += (((float) $grade->grade / $maxGrade) * 20) * $coefficient;
            $totalWeight += $coefficient;
        }

        return $totalWeight > 0 ? round($weightedTotal / $totalWeight, 2) : null;
    }

    /**
     * Données du tableau de bord personnel.
     *
     * @return array<string, mixed>
     */
    public function dashboardData(User $user): array
    {
        $student = $this->studentFor($user);
        $enrollments = $this->enrollments($student);
        $acceptedEnrollments = $this->acceptedEnrollments($student, $enrollments);
        $classIds = $this->classIds($acceptedEnrollments);
        $formationIds = $this->formationIds($acceptedEnrollments);

        $upcomingQuery = $this->schedulesQuery($student, $classIds)
            ->where('date', '>=', now()->toDateString())
            ->where('status', 'scheduled');

        $upcomingCount = (clone $upcomingQuery)->count();

        $nextSession = (clone $upcomingQuery)
            ->with(['classRoom.formation', 'teacher.user', 'room', 'center'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        $attendanceStats = $this->attendanceStats($student, $classIds);

        $latestGrades = $student
            ? $this->publishedGradesQuery($student, $classIds)
                ->with([
                    'evaluation.classRoom',
                    'evaluation.formation',
                ])
                ->orderByDesc('graded_at')
                ->orderByDesc('id')
                ->take(5)
                ->get()
            : new EloquentCollection;

        $notifications = AppNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $unreadNotificationsCount = AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $documents = $this->documentsQuery($student)
            ->latest()
            ->take(5)
            ->get();

        $certificates = $this->certificatesQuery($student)
            ->latest('issued_date')
            ->take(5)
            ->get();

        return [
            'student' => $student,
            'enrollments' => $enrollments,
            'acceptedEnrollments' => $acceptedEnrollments,
            'formations' => $this->formations($acceptedEnrollments),
            'formationIds' => $formationIds,
            'classes' => $this->classes($acceptedEnrollments),
            'classIds' => $classIds,
            'upcomingCount' => $upcomingCount,
            'nextSession' => $nextSession,
            'attendanceStats' => $attendanceStats,
            'latestGrades' => $latestGrades,
            'average' => $this->studentAverage($student, $classIds),
            'notifications' => $notifications,
            'unreadNotificationsCount' => $unreadNotificationsCount,
            'documents' => $documents,
            'certificates' => $certificates,
            'finance' => $this->finance->studentSituation($student),
        ];
    }
}
