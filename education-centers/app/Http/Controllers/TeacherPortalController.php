<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\Schedule;
use App\Services\PersonalFinanceService;
use App\Services\TeacherPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portail du professeur : ses classes, leurs étudiants et son emploi du temps.
 *
 * Chaque page repart de l'utilisateur authentifié. Les identifiants de classe
 * reçus depuis l'URL sont confrontés aux affectations `class_teacher` avant
 * toute lecture ; une classe étrangère est refusée (403).
 */
class TeacherPortalController extends Controller
{
    public function __construct(protected TeacherPortalService $portal) {}

    public function classes(Request $request): View
    {
        Gate::authorize('viewAny', ClassRoom::class);

        $teacher = $this->portal->teacherFor($request->user());
        $classes = $this->portal->classes($teacher);

        return view('teacher.classes', [
            'teacher' => $teacher,
            'center' => $teacher?->center ?? $request->user()->center,
            'classes' => $classes,
            'domainLabels' => $this->portal->domainLabels($teacher),
            'studentCount' => $this->portal->studentCount($teacher),
        ]);
    }

    public function showClass(Request $request, ClassRoom $class): View
    {
        $teacher = $this->portal->teacherFor($request->user());
        $this->portal->assertClassAccess($teacher, $class);
        Gate::authorize('view', $class);

        $class->load(['formation.domainModel', 'center', 'teachers.user']);

        $students = $this->portal->students($teacher, $class);
        $classIds = $this->portal->classIds($teacher);

        $schedules = $this->portal->schedulesQuery($teacher)
            ->where('class_id', $class->id)
            ->with(['room', 'center'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $evaluations = $this->portal->evaluationsQuery($teacher)
            ->where('class_id', $class->id)
            ->with('formation')
            ->latest('evaluation_date')
            ->take(10)
            ->get();

        return view('teacher.class', [
            'teacher' => $teacher,
            'class' => $class,
            'students' => $students,
            'schedules' => $schedules,
            'evaluations' => $evaluations,
            'domainLabel' => $class->formation
                ? ($this->portal->domainLabels($teacher)[$class->formation->domain]
                    ?? ucfirst(str_replace('-', ' ', (string) $class->formation->domain)))
                : null,
            'assignedClassCount' => $classIds->count(),
        ]);
    }

    public function schedule(Request $request): View
    {
        Gate::authorize('viewAny', Schedule::class);

        $teacher = $this->portal->teacherFor($request->user());
        $classes = $this->portal->classes($teacher);
        $classIds = $classes->pluck('id')->map(fn ($id): int => (int) $id)->values();

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
        ]);

        $classId = isset($filters['class_id']) ? (int) $filters['class_id'] : null;

        if ($classId) {
            abort_unless($classIds->contains($classId), 403);
        }

        $query = $this->portal->schedulesQuery($teacher)
            ->with(['classRoom.formation', 'room', 'center']);

        if ($classId) {
            $query->where('class_id', $classId);
        }

        $schedules = $query
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $events = $schedules
            ->map(fn (Schedule $schedule): array => $this->scheduleEvent($schedule))
            ->values();

        return view('teacher.schedule', [
            'teacher' => $teacher,
            'classes' => $classes,
            'schedules' => $schedules,
            'events' => $events,
            'classId' => $classId,
            'initialDate' => $this->initialScheduleDate($schedules),
            'locale' => app()->getLocale(),
            'dir' => current_dir(),
            'typeColors' => ScheduleController::TYPE_COLORS,
            'todayCount' => $schedules->filter(
                fn (Schedule $schedule): bool => $schedule->date?->toDateString() === now()->toDateString()
            )->count(),
        ]);
    }

    /**
     * « Mes salaires » : situation salariale personnelle du professeur.
     *
     * Lectures du module Finance (fiches de salaire). Le professeur est
     * dérivé de l'utilisateur authentifié ; aucun teacher_id du navigateur
     * n'est utilisé. Un professeur ne voit jamais la paie d'un confrère.
     */
    public function salaries(Request $request, PersonalFinanceService $finance): View
    {
        $teacher = $this->portal->teacherFor($request->user());

        if ($request->filled('teacher_id')) {
            abort_unless(
                $teacher !== null && (int) $request->input('teacher_id') === (int) $teacher->id,
                403
            );
        }

        return view('teacher.salaries', [
            'teacher' => $teacher,
            'center' => $teacher?->center ?? $request->user()->center,
            'situation' => $finance->teacherSituation($teacher),
        ]);
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
                'classCode' => $schedule->classRoom?->code,
                'formationName' => $schedule->classRoom?->general_formation_name,
                'roomName' => $schedule->room?->name,
                'roomCode' => $schedule->room?->code,
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
