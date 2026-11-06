<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Center;
use App\Models\ContactMessage;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Support\AcademicPerimeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function home(): View
    {
        $centers = Center::where('status', 'active')->withCount(['generalFormations as formations_count', 'classes', 'students'])->get();
        
        // Formations populaires : formations actives réelles ordonnées par nombre d'inscriptions
        $formations = AcademicPerimeter::currentFormationsQuery()
            ->with(['center'])
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->orderBy('name')
            ->take(6)
            ->get();

        $stats = [
            'centers' => Center::where('status', 'active')->count(),
            'students' => Student::where('status', 'active')->count(),
            'formations' => AcademicPerimeter::currentFormationsQuery()->count(),
        ];
        $announcements = Announcement::where('status', 'published')->where('is_public', true)->latest('published_at')->take(3)->get();
        $viewName = view()->exists('public.home') ? 'public.home' : 'home';

        return view($viewName, compact('centers', 'formations', 'stats', 'announcements'));
    }

    public function centers(): View
    {
        $centers = Center::where('status', 'active')->withCount(['generalFormations as formations_count', 'teachers', 'students'])->get();

        return view('public.centers', compact('centers'));
    }

    public function centerShow(Center $center): View
    {
        abort_if($center->status !== 'active', 404);
        $formations = AcademicPerimeter::currentFormationsQuery($center->id)->get();

        return view('public.center-show', compact('center', 'formations'));
    }

    public function formations(Request $request): View
    {
        $query = AcademicPerimeter::currentFormationsQuery()->with('center');
        if ($centerId = $request->input('center_id')) {
            $query->where('center_id', $centerId);
        }
        $formations = $query->paginate(12)->withQueryString();
        $centers = Center::where('status', 'active')->orderBy('name')->get();

        return view('public.formations', compact('formations', 'centers'));
    }

    public function formationShow(Formation $formation): View
    {
        abort_if($formation->status !== 'active', 404);
        // Une ancienne sous-formation n'est plus une page publique : elle est
        // remplacée par sa formation générale équivalente.
        abort_unless(AcademicPerimeter::isCurrentFormation($formation), 404);
        $formation->load('center');
        // Les classes affichées sont celles du périmètre de la formation générale
        // (alias historiques inclus) : autorité unique AcademicPerimeter, jamais
        // un filtrage fait à la main dans la vue.
        $formation->setRelation('classes', $formation->perimeterClasses()->get());

        return view('public.formation-show', compact('formation'));
    }

    public function announcements(Request $request): View
    {
        $announcements = Announcement::where('status', 'published')->where('is_public', true)
            ->latest('published_at')->paginate(10)->withQueryString();

        return view('public.announcements', compact('announcements'));
    }

    public function announcementShow(string $slug): View
    {
        $announcement = Announcement::where('slug', $slug)->where('status', 'published')->where('is_public', true)->firstOrFail();

        return view('public.announcement-show', compact('announcement'));
    }

    public function contactForm(): View
    {
        $centers = Center::where('status', 'active')->orderBy('name')->get();

        return view('public.contact', compact('centers'));
    }

    public function contactStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:20', 'max:3000'],
        ]);
        $data['ip_address'] = $request->ip();
        ContactMessage::create($data);

        return back()->with('success', __('Votre message a été envoyé. Nous vous répondrons dans les plus brefs délais.'));
    }

    public function applyForm(Request $request): View
    {
        $centers = Center::where('status', 'active')->orderBy('name')->get();
        $preselectedFormationId = $request->input('formation_id');
        $preselectedCenterId = $request->input('center_id');
        $formations = AcademicPerimeter::currentFormationsQuery()->with('center')->get();

        return view('public.apply', compact('centers', 'formations', 'preselectedFormationId', 'preselectedCenterId'));
    }

    public function applyStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'center_id' => ['required', 'integer', 'exists:centers,id'],
            'formation_id' => ['required', 'integer', 'exists:formations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $center = Center::findOrFail($data['center_id']);
        $formation = Formation::findOrFail($data['formation_id']);
        if ($formation->center_id !== (int) $data['center_id']) {
            return back()->withErrors(['formation_id' => __('Cette formation n\'appartient pas à ce centre.')])->withInput();
        }
        // Architecture obligatoire : une candidature ne peut viser qu'une
        // formation générale active, jamais une ancienne sous-formation.
        if ($formation->status !== 'active' || ! AcademicPerimeter::isCurrentFormation($formation)) {
            return back()->withErrors(['formation_id' => __('La formation sélectionnée n\'est plus proposée. Choisissez la formation générale équivalente.')])->withInput();
        }
        // Création directe du dossier étudiant sans compte utilisateur
        $student = Student::create([
            'user_id' => null,
            'center_id' => $center->id,
            'student_number' => Student::generateStudentNumber($center),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'date_of_birth' => $data['date_of_birth'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'gender' => 'other',
            'nationality' => '',
            'status' => 'active',
        ]);
        Enrollment::create([
            'center_id' => $center->id,
            'student_id' => $student->id,
            'formation_id' => $formation->id,
            'registration_number' => Enrollment::generateRegistrationNumber($center),
            'application_date' => now()->toDateString(),
            'status' => Enrollment::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('public.apply.success')->with('success', __('Votre candidature a été soumise! Numéro étudiant: :student_number', ['student_number' => $student->student_number]));
    }

    public function applySuccess(): View
    {
        return view('public.apply-success');
    }
}
