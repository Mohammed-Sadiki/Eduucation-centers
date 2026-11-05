<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Document;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    private const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    private const MAX_SIZE_KB = 10240; // 10 MB

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Document::class);
        $user = $request->user();
        $query = Document::query()->with(['student', 'uploader']);
        if ($user->isCenterAdmin() || $user->isSecretary()) {
            $query->where('center_id', $user->center_id);
        } elseif ($user->isStudent()) {
            $query->where('student_id', $user->student?->id)->where('center_id', $user->center_id)->where('status', 'active');
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($studentId = $request->input('student_id')) {
            $query->where('student_id', $studentId);
        }
        $documents = $query->latest()->paginate(20)->withQueryString();
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('documents.index', compact('documents', 'centers'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Document::class);
        $user = $request->user();
        $centerId = $user->isSuperAdmin() ? $request->input('center_id') : $user->center_id;
        $students = Student::when($centerId, fn ($q) => $q->where('center_id', $centerId))->where('status', 'active')->orderBy('last_name')->get();
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();
        $preselectedStudentId = $request->input('student_id');

        return view('documents.create', compact('students', 'centers', 'centerId', 'preselectedStudentId'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Document::class);
        $user = $request->user();
        $centerId = $user->isSuperAdmin() ? (int) $request->input('center_id') : $user->center_id;
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'type' => ['required', 'in:identity,diploma,certificate,attestation,administrative,other'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:'.self::MAX_SIZE_KB, 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);
        $student = Student::findOrFail($data['student_id']);
        if ($student->center_id !== $centerId) {
            abort(403);
        }
        $file = $request->file('file');
        $safeName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("documents/{$centerId}/{$student->id}", $safeName, 'private');
        Document::create([
            'center_id' => $centerId,
            'student_id' => $student->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
            'status' => 'active',
        ]);

        return redirect()->route('documents.index', ['student_id' => $student->id])->with('success', __('Document \\":title\\" uploadé.', ['title' => $data['title']]));
    }

    public function show(Document $document): View
    {
        Gate::authorize('view', $document);
        $document->load(['student.user', 'uploader']);

        return view('documents.show', compact('document'));
    }

    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('download', $document);
        if (! Storage::disk('private')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('private')->download($document->file_path, $document->original_name);
    }

    /**
     * Ouvre le vrai fichier dans le navigateur (aperçu / impression).
     *
     * Seuls les formats affichables nativement (PDF et images) sont exposés
     * inline. Les autres documents restent téléchargeables uniquement.
     */
    public function preview(Document $document): Response
    {
        Gate::authorize('download', $document);

        abort_unless(
            $document->file_path && Storage::disk('private')->exists($document->file_path),
            404
        );

        $mimeType = $document->mime_type ?: Storage::disk('private')->mimeType($document->file_path);
        $printableMimes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];

        abort_unless(in_array($mimeType, $printableMimes, true), 415);

        $filename = $document->original_name ?: basename($document->file_path);

        return response(Storage::disk('private')->get($document->file_path), 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);
        Storage::disk('private')->delete($document->file_path);
        $studentId = $document->student_id;
        $document->delete();

        return redirect()->route('documents.index', ['student_id' => $studentId])->with('success', __('Document supprimé.'));
    }
}
