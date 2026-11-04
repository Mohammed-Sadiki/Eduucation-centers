<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Center;
use App\Models\Certificate;
use App\Models\ClassRoom;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentPortalService;
use App\Services\TeacherPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected StudentPortalService $studentPortal,
        protected TeacherPortalService $teacherPortal,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        // Super Admin et Admin Centre → nouvelle page d'accueil unifiée (4 cartes)
        if ($user->isSuperAdmin() || $user->isCenterAdmin()) {
            return redirect()->route('admin.home');
        }
        if (view()->exists('dashboard.'.$user->role)) {
            if ($user->isTeacher()) {
                return $this->teacherDashboard($user);
            }
            if ($user->isStudent()) {
                return $this->studentDashboard($user);
            }
        }

        return view('dashboard', compact('user'));
    }

    /**
     * Page d'accueil commune aux administrateurs (4 grandes cartes).
     * Accessible aux rôles : super_admin, center_admin.
     */
    public function adminHome(Request $request): View
    {
        return view('admin.home');
    }

    private function superAdminDashboard(Request $request): View
    {
        $stats = [
            'centers' => Center::count(),
            'users' => User::count(),
            'students' => Student::count(),
            'teachers' => Teacher::count(),
            'formations' => Formation::query()->current()->count(),
            'classes' => ClassRoom::count(),
            'enrollments' => Enrollment::count(),
            'rooms' => Room::count(),
            'schedules' => Schedule::count(),
            'payments_total' => Payment::where('status', 'paid')->sum('amount'),
            'pending_enrollments' => Enrollment::where('status', 'pending')->count(),
        ];
        $centers = Center::withCount(['students', 'teachers', 'generalFormations as formations_count', 'classes', 'enrollments'])->get();
        $recentEnrollments = Enrollment::with(['student', 'formation', 'center'])->latest()->take(10)->get();

        return view('dashboard.super_admin', compact('stats', 'centers', 'recentEnrollments'));
    }

    private function centerAdminDashboard(User $user): View
    {
        $cid = $user->center_id;
        $stats = [
            'students' => Student::where('center_id', $cid)->count(),
            'teachers' => Teacher::where('center_id', $cid)->count(),
            'formations' => Formation::where('center_id', $cid)->current()->count(),
            'classes' => ClassRoom::where('center_id', $cid)->count(),
            'rooms' => Room::where('center_id', $cid)->count(),
            'enrollments_pending' => Enrollment::where('center_id', $cid)->where('status', 'pending')->count(),
            'enrollments_accepted' => Enrollment::where('center_id', $cid)->where('status', 'accepted')->count(),
            'payments_total' => Payment::where('center_id', $cid)->where('status', 'paid')->sum('amount'),
            'invoices_pending' => Invoice::where('center_id', $cid)->whereIn('status', ['sent', 'overdue'])->sum('total_amount'),
            'documents' => Document::where('center_id', $cid)->where('status', 'active')->count(),
            'certificates' => Certificate::where('center_id', $cid)->where('status', 'issued')->count(),
        ];
        $upcoming = Schedule::where('center_id', $cid)->where('date', '>=', now()->toDateString())->where('status', 'scheduled')->with(['classRoom', 'teacher.user', 'room'])->orderBy('date')->orderBy('start_time')->take(5)->get();
        $recentEnrollments = Enrollment::where('center_id', $cid)->with(['student', 'formation'])->latest()->take(8)->get();
        $notifications = AppNotification::where('user_id', $user->id)->whereNull('read_at')->latest()->take(5)->get();

        return view('dashboard.center_admin', compact('stats', 'upcoming', 'recentEnrollments', 'notifications'));
    }

    private function teacherDashboard(User $user): View
    {
        return view('dashboard.teacher', $this->teacherPortal->dashboardData($user));
    }

    private function studentDashboard(User $user): View
    {
        return view('dashboard.student', $this->studentPortal->dashboardData($user));
    }
}
