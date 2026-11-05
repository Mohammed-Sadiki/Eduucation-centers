<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Center;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\TeacherPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(protected TeacherPortalService $portal) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Attendance::class);
        $user = $request->user();
        $perimeter = $this->perimeter($user);
        $query = Attendance::query()->with(['student', 'classRoom', 'schedule']);
        $teacher = null;
        if ($user->isCenterAdmin() || $user->isSecretary()) {
            $query->where('center_id', $user->center_id);
        } elseif ($user->isTeacher()) {
            // Le professeur ne voit que les présences de ses classes réellement
            // affectées (class_teacher), y compris hors filtres UI.
            $teacher = $this->portal->teacherFor($user);
            $query->whereIn('class_id', $this->portal->classIds($teacher));
        } elseif ($user->isStudent()) {
            $studentId = $user->student?->id;
            $query->where('student_id', $studentId)->where('center_id', $user->center_id);
        }
        // Périmètre pédagogique unique : formations générales actives du centre
        // autorisé + domaine actif. Le tableau suit donc exactement le filtre.
        $query = $perimeter->applyTo($query);
        if ($formationId = $request->input('formation_id')) {
            $query->whereHas('classRoom', function ($q) use ($formationId) {
                $q->where('formation_id', $formationId);
            });
        }
        if ($classId = $request->input('class_id')) {
            $query->where('class_id', $classId);
        }
        if ($scheduleId = $request->input('schedule_id')) {
            $query->where('schedule_id', $scheduleId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('date', $date);
        }
        if ($studentId = $request->input('student_id')) {
            $query->where('student_id', $studentId);
        }
        $attendances = $query->latest('date')->paginate(20)->withQueryString();
        // Le filtre « Classe » propose uniquement les classes des formations
        // générales actives du périmètre, libellées « Formation · Groupe X ».
        if ($user->isTeacher()) {
            $classes = $perimeter->classesForSelect($this->portal->classIds($teacher));
        } else {
            $classes = $perimeter->classesForSelect();
        }
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('attendances.index', compact('attendances', 'classes', 'centers'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Attendance::class);
        $user = $request->user();
        $perimeter = $this->perimeter($user, $user->isSuperAdmin() ? $request->input('center_id') : null);
        $centerId = $user->isSuperAdmin() ? $request->input('center_id') : $user->center_id;
        $scheduleId = $request->input('schedule_id');
        $schedule = $scheduleId ? Schedule::find($scheduleId) : null;
        if ($user->isTeacher()) {
            $teacher = $this->portal->teacherFor($user);
            // Un professeur ne saisit les présences que de ses propres séances,
            // dans ses classes affectées. Toute autre séance est refusée.
            if ($schedule) {
                $this->portal->assertScheduleAccess($teacher, $schedule);
            }
            $classes = $perimeter->classesForSelect($this->portal->classIds($teacher), activeOnly: true);
            $schedules = $perimeter->applyThroughFormation(
                $this->portal->schedulesQuery($teacher)->where('status', 'scheduled'),
                'classRoom.formation'
            )->with('classRoom')->orderBy('date')->orderBy('start_time')->get();
        } else {
            $classes = $perimeter->classesForSelect(activeOnly: true);
            $schedules = $perimeter->applyThroughFormation(
                Schedule::when($centerId, fn ($q) => $q->where('center_id', $centerId))->where('status', 'scheduled'),
                'classRoom.formation'
            )->with('classRoom')->orderBy('date')->orderBy('start_time')->get();
        }
        $students = collect();
        if ($schedule) {
            $enrolledStudentIds = Enrollment::where('class_id', $schedule->class_id)->where('status', 'accepted')->pluck('student_id');
            $students = Student::whereIn('id', $enrolledStudentIds)->where('status', 'active')->orderBy('last_name')->get();
        }
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('attendances.create', compact('classes', 'schedules', 'schedule', 'students', 'centers', 'centerId'));
    }

    public function storeSession(Request $request): RedirectResponse
    {
        Gate::authorize('create', Attendance::class);
        $user = $request->user();
        $perimeter = $this->perimeter($user, $user->isSuperAdmin() ? (int) $request->input('center_id') : null);
        $centerId = $user->isSuperAdmin() ? (int) $request->input('center_id') : $user->center_id;
        $request->validate([
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'attendances' => ['required', 'array'],
            'attendances.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'attendances.*.status' => ['required', 'in:present,absent,late'],
            'attendances.*.arrival_time' => ['nullable', 'date_format:H:i'],
            'attendances.*.late_minutes' => ['nullable', 'integer', 'min:0', 'max:999'],
            'attendances.*.comment' => ['nullable', 'string', 'max:500'],
        ]);
        $schedule = Schedule::findOrFail($request->schedule_id);
        if ($schedule->center_id !== $centerId) {
            abort(403);
        }
        if ($user->isTeacher()) {
            // Refusé même si l'identifiant de séance est manipulé dans l'URL
            // ou le formulaire : la séance doit être au professeur et dans une
            // classe qui lui est affectée.
            $this->portal->assertScheduleAccess($this->portal->teacherFor($user), $schedule);
        }
        // La séance doit porter une classe rattachée à une formation générale
        // active du périmètre autorisé : un `<select>` n'est jamais une sécurité.
        if ($schedule->class_id && ! $perimeter->allowsClass((int) $schedule->class_id)) {
            throw ValidationException::withMessages([
                'schedule_id' => __('La séance ne relève pas d\'une formation générale active de votre périmètre.'),
            ]);
        }
        $count = 0;
        foreach ($request->input('attendances', []) as $att) {
            Attendance::updateOrCreate(
                ['student_id' => $att['student_id'], 'schedule_id' => $schedule->id],
                [
                    'center_id' => $centerId,
                    'class_id' => $schedule->class_id,
                    'date' => $schedule->date,
                    'status' => $att['status'],
                    'arrival_time' => $att['arrival_time'] ?? null,
                    'late_minutes' => $att['late_minutes'] ?? null,
                    'comment' => $att['comment'] ?? null,
                    'recorded_by' => $user->id,
                ]
            );
            $count++;
        }

        return redirect()->route('attendances.index')->with('success', __('Présences enregistrées pour :count étudiant(s).', ['count' => $count]));
    }

    public function show(Attendance $attendance): View
    {
        Gate::authorize('view', $attendance);
        $attendance->load(['student.user', 'classRoom', 'schedule', 'recorder']);

        return view('attendances.show', compact('attendance'));
    }

    public function edit(Attendance $attendance): View
    {
        Gate::authorize('update', $attendance);
        $attendance->load(['student', 'classRoom', 'schedule']);

        return view('attendances.edit', compact('attendance'));
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        Gate::authorize('update', $attendance);
        $data = $request->validate([
            'status' => ['required', 'in:present,absent,late'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:999'],
            'justification' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'justification_status' => ['nullable', 'in:pending,approved,rejected'],
        ]);
        $attendance->update($data);

        return redirect()->route('attendances.show', $attendance)->with('success', __('Présence mise à jour.'));
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        Gate::authorize('delete', $attendance);
        $attendance->delete();

        return redirect()->route('attendances.index')->with('success', __('Présence supprimée.'));
    }

    public function getStudentsForSchedule(Request $request): JsonResponse
    {
        $scheduleId = $request->input('schedule_id');
        $schedule = Schedule::findOrFail($scheduleId);
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->center_id !== $schedule->center_id) {
            abort(403);
        }
        if ($user->isTeacher()) {
            $this->portal->assertScheduleAccess($this->portal->teacherFor($user), $schedule);
        }
        // Même périmètre que le formulaire : la liste des élèves ne peut porter
        // que sur une classe d'une formation générale active autorisée.
        if ($schedule->class_id && ! $this->perimeter($user, $schedule->center_id)->allowsClass((int) $schedule->class_id)) {
            abort(403);
        }
        $enrolledStudentIds = Enrollment::where('class_id', $schedule->class_id)->where('status', 'accepted')->pluck('student_id');
        $students = Student::whereIn('id', $enrolledStudentIds)->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'student_number']);
        $existing = Attendance::where('schedule_id', $scheduleId)->pluck('status', 'student_id');

        return response()->json(['students' => $students, 'existing' => $existing, 'schedule' => $schedule]);
    }
}
