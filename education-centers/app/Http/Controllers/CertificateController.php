<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function __construct(protected CertificateService $certificateService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Certificate::class);
        $user = $request->user();
        $query = Certificate::query()->with(['student', 'formation']);
        if ($user->isCenterAdmin() || $user->isSecretary()) {
            $query->where('center_id', $user->center_id);
        } elseif ($user->isStudent()) {
            $query->where('student_id', $user->student?->id)->where('center_id', $user->center_id);
        }
        // Filtrage par domaine actif (contexte domaine) — cohérent avec le
        // compte « Certificats délivrés » du tableau de bord de domaine.
        $query = $this->applyDomainScope($query);
        if ($formationId = $request->input('formation_id')) {
            $query->where('formation_id', $formationId);
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($studentId = $request->input('student_id')) {
            $query->where('student_id', $studentId);
        }
        $certificates = $query->latest('issued_date')->paginate(20)->withQueryString();
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('certificates.index', compact('certificates', 'centers'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Certificate::class);
        $user = $request->user();
        $centerId = $user->isSuperAdmin() ? $request->input('center_id') : $user->center_id;
        $students = Student::when($centerId, fn ($q) => $q->where('center_id', $centerId))->where('status', 'active')->orderBy('last_name')->get();
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();
        $preselectedStudentId = $request->input('student_id');

        return view('certificates.create', compact('students', 'centers', 'centerId', 'preselectedStudentId'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Certificate::class);
        $user = $request->user();
        $centerId = $user->isSuperAdmin() ? (int) $request->input('center_id') : $user->center_id;
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'type' => ['required', 'in:enrollment,completion,attendance,grades_report,receipt,other'],
            'enrollment_id' => ['nullable', 'integer', 'exists:enrollments,id'],
        ]);
        $student = Student::findOrFail($data['student_id']);
        if ($student->center_id !== $centerId) {
            abort(403);
        }
        $enrollment = isset($data['enrollment_id']) ? Enrollment::find($data['enrollment_id']) : null;
        $certificate = $this->certificateService->createAndGenerate($student, $data['type'], $enrollment, $user);

        // Certificat disponible → notifie l'Admin Centre concerné (jamais l'auteur).
        app(\App\Services\NotificationService::class)->notifyCenterAdmins(
            centerId: $centerId,
            type: 'certificate',
            title: __('Certificat disponible'),
            message: __('Certificat :number généré pour :name.', [
                'number' => $certificate->certificate_number,
                'name'   => $student->full_name,
            ]),
            link: route('certificates.show', $certificate),
            exceptUserId: $user->id,
            relatedType: Certificate::class,
            relatedId: $certificate->id,
        );

        return redirect()->route('certificates.show', $certificate)->with('success', __('Certificat :certificate_number généré.', ['certificate_number' => $certificate->certificate_number]));
    }

    public function show(Certificate $certificate): View
    {
        Gate::authorize('view', $certificate);
        $certificate->load(['student.user', 'formation', 'enrollment', 'issuer', 'center']);

        return view('certificates.show', compact('certificate'));
    }

    public function download(Certificate $certificate): StreamedResponse
    {
        Gate::authorize('view', $certificate);
        // Regenerate so the document uses the visitor's active locale (FR/AR).
        $this->certificateService->generate($certificate);
        $certificate->refresh();
        $filename = $certificate->certificate_number.'.pdf';

        return Storage::disk('private')->download($certificate->file_path, $filename);
    }

    /**
     * Open the exact same stored PDF inline (viewer / print). Content is
     * byte-identical to download(); only the disposition differs.
     */
    public function printPdf(Certificate $certificate): Response
    {
        Gate::authorize('view', $certificate);
        $this->certificateService->generate($certificate);
        $certificate->refresh();

        abort_unless(
            $certificate->file_path && Storage::disk('private')->exists($certificate->file_path),
            404
        );

        $content = Storage::disk('private')->get($certificate->file_path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$certificate->certificate_number.'.pdf"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function destroy(Certificate $certificate): RedirectResponse
    {
        Gate::authorize('delete', $certificate);
        if ($certificate->file_path) {
            Storage::disk('private')->delete($certificate->file_path);
        }
        $certificate->delete();

        return redirect()->route('certificates.index')->with('success', __('Certificat supprimé.'));
    }

    public function verify(Request $request): View
    {
        $token = $request->input('token');
        $certificate = null;
        if ($token) {
            $certificate = Certificate::where('verify_token', $token)->where('status', 'issued')->first();
        }

        return view('public.verify-certificate', compact('certificate', 'token'));
    }
}
