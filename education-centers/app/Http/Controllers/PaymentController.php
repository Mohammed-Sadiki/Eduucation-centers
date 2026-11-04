<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Payment;
use App\Models\Student;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Payment::class);
        $user = $request->user();
        $query = Payment::query()->with(['student', 'enrollment.formation', 'recorder']);
        if ($user->isCenterAdmin()) {
            $query->where('center_id', $user->center_id);
        }
        // Filtrage par domaine actif (contexte domaine) — cohérent avec le
        // compte « Revenus (payés) » du tableau de bord de domaine.
        $query = $this->applyDomainScope($query);
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($studentId = $request->input('student_id')) {
            $query->where('student_id', $studentId);
        }
        if ($method = $request->input('method')) {
            $query->where('method', $method);
        }
        $payments = $query->latest('payment_date')->paginate(20)->withQueryString();
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();
        $centerId = $user->isCenterAdmin() ? $user->center_id : null;
        $students = Student::when($centerId, fn ($q) => $q->where('center_id', $centerId))->orderBy('last_name')->get();
        $totalPaid = (clone $query)->where('status', 'paid')->sum('amount');

        return view('payments.index', compact('payments', 'centers', 'students', 'totalPaid'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Payment::class);
        $user = $request->user();
        $centerId = $user->isSuperAdmin() ? $request->input('center_id') : $user->center_id;
        $students = Student::when($centerId, fn ($q) => $q->where('center_id', $centerId))->where('status', 'active')->orderBy('last_name')->get();
        $formations = $this->perimeter($user, $centerId ? (int) $centerId : null)->withoutActiveDomain()->formationsQuery()->get();
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();
        $preselectedStudentId = $request->input('student_id');
        $preselectedEnrollmentId = $request->input('enrollment_id');

        return view('payments.create', compact('students', 'formations', 'centers', 'centerId', 'preselectedStudentId', 'preselectedEnrollmentId'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Payment::class);
        $user = $request->user();
        $centerId = $user->isSuperAdmin() ? (int) $request->input('center_id') : $user->center_id;
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'enrollment_id' => ['nullable', 'integer', 'exists:enrollments,id'],
            'formation_id' => ['nullable', 'integer', 'exists:formations,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:10'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', 'in:cash,card,bank_transfer,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $student = Student::findOrFail($data['student_id']);
        if ($student->center_id !== $centerId) {
            abort(403);
        }
        $center = Center::findOrFail($centerId);
        $data['center_id'] = $centerId;
        $data['status'] = 'paid';
        $data['reference'] = Payment::generateReference($center);
        $data['recorded_by'] = $user->id;
        $payment = Payment::create($data);

        // Notification interne : paiement réellement encaissé (jamais annulé/échoué).
        app(NotificationService::class)->notifyCenterAdmins(
            centerId: $centerId,
            type: 'payment',
            title: __('Nouveau paiement encaissé'),
            message: __('Paiement de :amount encaissé pour :name.', [
                'amount' => format_price($payment->amount, $payment->currency),
                'name' => $student->full_name,
            ]),
            link: route('payments.show', $payment),
            exceptUserId: $user->id,
            relatedType: Payment::class,
            relatedId: $payment->id,
            metadata: ['amount' => (float) $payment->amount],
        );

        return redirect()->route('payments.show', $payment)->with('success', __('Paiement :reference enregistré.', ['reference' => $payment->reference]));
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);
        $payment->load(['student.user', 'enrollment.formation', 'invoice', 'recorder']);

        return view('payments.show', compact('payment'));
    }

    public function edit(Payment $payment): View
    {
        Gate::authorize('update', $payment);

        return view('payments.edit', compact('payment'));
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('update', $payment);
        $data = $request->validate([
            'status' => ['required', 'in:pending,paid,cancelled,refunded'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $payment->update($data);

        return redirect()->route('payments.show', $payment)->with('success', __('Paiement mis à jour.'));
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        Gate::authorize('delete', $payment);
        $payment->delete();

        return redirect()->route('payments.index')->with('success', __('Paiement supprimé.'));
    }
}
