<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\PersonalFinanceService;
use App\Services\StudentPortalService;
use App\Support\DomainRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Portail lecture seule de l'élève.
 *
 * Chaque page part de l'utilisateur authentifié. Les filtres reçus depuis
 * l'URL sont validés, puis confrontés au périmètre réel de l'élève avant toute
 * requête. Un identifiant étranger est refusé (403), jamais transformé en
 * accès.
 */
class StudentPortalController extends Controller
{
    private const EVALUATION_TYPES = ['exam', 'control', 'homework', 'oral', 'project', 'participation'];

    private const DOCUMENT_TYPES = ['identity', 'diploma', 'certificate', 'attestation', 'administrative', 'other'];

    private const CERTIFICATE_TYPES = ['enrollment', 'completion', 'attendance', 'grades_report', 'receipt', 'other'];

    public function __construct(protected StudentPortalService $portal) {}

    public function formations(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $this->guardStudentParameter($request, $student);

        $enrollments = $this->portal->enrollments($student);
        $acceptedEnrollments = $this->portal->acceptedEnrollments($student, $enrollments);

        return view('student.formations', [
            'student' => $student,
            'enrollments' => $enrollments,
            'formations' => $this->portal->formations($acceptedEnrollments),
            'classes' => $this->portal->classes($acceptedEnrollments),
            'domainLabels' => DomainRegistry::labels(),
        ]);
    }

    public function schedule(Request $request): View
    {
        Gate::authorize('viewAny', Schedule::class);

        $student = $this->portal->studentFor($request->user());
        $this->guardStudentParameter($request, $student);

        $enrollments = $this->portal->enrollments($student);
        $acceptedEnrollments = $this->portal->acceptedEnrollments($student, $enrollments);
        $classIds = $this->portal->classIds($acceptedEnrollments);
        $formationIds = $this->portal->formationIds($acceptedEnrollments);

        $filters = $request->validate([
            'formation_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
        ]);

        $formationId = isset($filters['formation_id']) ? (int) $filters['formation_id'] : null;
        $classId = isset($filters['class_id']) ? (int) $filters['class_id'] : null;

        if ($formationId) {
            abort_unless($formationIds->contains($formationId), 403);
        }

        if ($classId) {
            abort_unless($classIds->contains($classId), 403);
        }

        $query = $this->portal->schedulesQuery($student, $classIds)
            ->with(['classRoom.formation', 'teacher.user', 'room', 'center']);

        if ($formationId) {
            $query->whereHas('classRoom', fn ($classQuery) => $classQuery->where('formation_id', $formationId));
        }

        if ($classId) {
            $query->where('class_id', $classId);
        }

        $schedules = $query
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $events = $schedules->map(fn (Schedule $schedule): array => $this->scheduleEvent($schedule))->values();

        return view('student.schedule', [
            'student' => $student,
            'formations' => $this->portal->formations($acceptedEnrollments),
            'classes' => $this->portal->classes($acceptedEnrollments),
            'events' => $events,
            'schedules' => $schedules,
            'formationId' => $formationId,
            'classId' => $classId,
            'initialDate' => $this->initialScheduleDate($schedules),
            'locale' => app()->getLocale(),
            'dir' => current_dir(),
            'typeColors' => ScheduleController::TYPE_COLORS,
        ]);
    }

    public function attendances(Request $request): View
    {
        Gate::authorize('viewAny', Attendance::class);

        $student = $this->portal->studentFor($request->user());
        $this->guardStudentParameter($request, $student);

        $enrollments = $this->portal->enrollments($student);
        $acceptedEnrollments = $this->portal->acceptedEnrollments($student, $enrollments);
        $classIds = $this->portal->classIds($acceptedEnrollments);
        $classes = $this->portal->classes($acceptedEnrollments);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['present', 'absent', 'late'])],
            'date' => ['nullable', 'date'],
        ]);

        $classId = isset($filters['class_id']) ? (int) $filters['class_id'] : null;

        if ($classId) {
            abort_unless($classIds->contains($classId), 403);
        }

        $query = $this->portal->attendancesQuery($student, $classIds)
            ->with(['classRoom', 'schedule']);

        if ($classId) {
            $query->where('class_id', $classId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        $attendances = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('student.attendances', [
            'student' => $student,
            'attendances' => $attendances,
            'classes' => $classes,
            'stats' => $this->portal->attendanceStats($student, $classIds),
            'classId' => $classId,
        ]);
    }

    public function evaluations(Request $request): View
    {
        Gate::authorize('viewAny', Evaluation::class);

        $student = $this->portal->studentFor($request->user());
        $this->guardStudentParameter($request, $student);

        $enrollments = $this->portal->enrollments($student);
        $acceptedEnrollments = $this->portal->acceptedEnrollments($student, $enrollments);
        $classIds = $this->portal->classIds($acceptedEnrollments);
        $classes = $this->portal->classes($acceptedEnrollments);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(self::EVALUATION_TYPES)],
        ]);

        $classId = isset($filters['class_id']) ? (int) $filters['class_id'] : null;

        if ($classId) {
            abort_unless($classIds->contains($classId), 403);
        }

        $query = $this->portal->publishedEvaluationsQuery($student, $classIds)
            ->with([
                'classRoom',
                'formation',
                'teacher.user',
                'grades' => fn ($gradeQuery) => $student
                    ? $gradeQuery->where('student_id', $student->id)
                    : $gradeQuery->whereRaw('1 = 0'),
            ])
            ->withAvg('grades as grades_avg', 'grade');

        if ($classId) {
            $query->where('class_id', $classId);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $evaluations = $query
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('student.evaluations', [
            'student' => $student,
            'evaluations' => $evaluations,
            'classes' => $classes,
            'average' => $this->portal->studentAverage($student, $classIds),
            'classId' => $classId,
        ]);
    }

    public function documents(Request $request): View
    {
        Gate::authorize('viewAny', Document::class);

        $student = $this->portal->studentFor($request->user());
        $this->guardStudentParameter($request, $student);

        $filters = $request->validate([
            'type' => ['nullable', Rule::in(self::DOCUMENT_TYPES)],
        ]);

        $query = $this->portal->documentsQuery($student);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $documents = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('student.documents', [
            'student' => $student,
            'documents' => $documents,
        ]);
    }

    public function certificates(Request $request): View
    {
        Gate::authorize('viewAny', Certificate::class);

        $student = $this->portal->studentFor($request->user());
        $this->guardStudentParameter($request, $student);

        $filters = $request->validate([
            'type' => ['nullable', Rule::in(self::CERTIFICATE_TYPES)],
        ]);

        $query = $this->portal->certificatesQuery($student);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $certificates = $query
            ->with(['formation', 'enrollment'])
            ->latest('issued_date')
            ->paginate(20)
            ->withQueryString();

        return view('student.certificates', [
            'student' => $student,
            'certificates' => $certificates,
        ]);
    }

    /**
     * « Mes paiements » : situation financière personnelle de l'élève.
     *
     * Les données proviennent du module Finance (inscriptions, paiements,
     * factures/échéances). Le périmètre est dérivé de l'utilisateur connecté ;
     * aucun student_id du navigateur n'est utilisé pour les calculs.
     */
    public function payments(Request $request, PersonalFinanceService $finance): View
    {
        $student = $this->portal->studentFor($request->user());
        $this->guardStudentParameter($request, $student);

        return view('student.payments', [
            'student' => $student,
            'situation' => $finance->studentSituation($student),
        ]);
    }

    /**
     * Refuse explicitement un student_id étranger. Les pages élèves ne lisent
     * jamais ce paramètre : elles repartent toujours de l'utilisateur connecté.
     */
    private function guardStudentParameter(Request $request, ?Student $student): void
    {
        if (! $request->filled('student_id')) {
            return;
        }

        abort_unless(
            $student !== null && (int) $request->input('student_id') === (int) $student->id,
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleEvent(Schedule $schedule): array
    {
        $type = array_key_exists($schedule->type, ScheduleController::TYPE_COLORS)
            ? $schedule->type
            : 'other';

        $classNames = ['ec-fc-event', 'ec-fc-event--'.$type];

        if ($schedule->isCancelled()) {
            $classNames[] = 'ec-fc-event--cancelled';
        }

        return [
            'id' => (string) $schedule->id,
            'title' => $schedule->title ?: ($schedule->classRoom?->perimeter_label ?: __('Séance')),
            'start' => $schedule->date?->format('Y-m-d').'T'.substr((string) $schedule->start_time, 0, 5),
            'end' => $schedule->date?->format('Y-m-d').'T'.substr((string) $schedule->end_time, 0, 5),
            'allDay' => false,
            'classNames' => $classNames,
            'extendedProps' => [
                'type' => $type,
                'typeLabel' => $schedule->type_label,
                'color' => ScheduleController::TYPE_COLORS[$type],
                'status' => $schedule->status,
                'statusLabel' => match ($schedule->status) {
                    'scheduled' => __('Planifié'),
                    'completed' => __('Terminé'),
                    'cancelled' => __('Annulé'),
                    default => ucfirst((string) $schedule->status),
                },
                'date' => format_date($schedule->date, 'D d M Y'),
                'time' => $schedule->formatted_time,
                'className' => $schedule->classRoom?->perimeter_label,
                'formationName' => $schedule->classRoom?->general_formation_name,
                'teacherName' => $schedule->teacher?->full_name,
                'roomName' => $schedule->room?->name,
                'centerName' => $schedule->center?->name,
                'description' => $schedule->description,
            ],
        ];
    }

    /**
     * Positionne le calendrier sur la prochaine séance, sinon la plus récente.
     *
     * @param  Collection<int, Schedule>  $schedules
     */
    private function initialScheduleDate(Collection $schedules): string
    {
        $today = now()->toDateString();

        $upcoming = $schedules->first(
            fn (Schedule $schedule): bool => $schedule->date !== null
                && $schedule->date->toDateString() >= $today
        );

        $fallback = $schedules->last();

        return $upcoming?->date?->format('Y-m-d')
            ?? $fallback?->date?->format('Y-m-d')
            ?? $today;
    }
}
