<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Support\AcademicPerimeter;
use Illuminate\Support\Collection;

/**
 * Situation financière PERSONNELLE d'un élève (paiements) et d'un professeur
 * (salaires).
 *
 * Ce service ne crée pas un second système financier : il lit uniquement les
 * données du module Finance existant (inscriptions, paiements, factures/
 * échéances, fiches de salaire) et les recalcule à la volée. Toute opération
 * enregistrée par un administrateur est donc immédiatement reflétée ici, sans
 * compteur dénormalisé à rafraîchir.
 *
 * Les montants sont toujours rattachés à l'utilisateur authentifié (via les
 * modèles Student/Teacher résolus par les services de portail) ; aucun identifiant
 * fourni par le navigateur n'est utilisé.
 */
class PersonalFinanceService
{
    /**
     * Statuts d'inscription générant une situation financière visible par l'élève.
     * Aligné sur le module Finance administrateur (pas de divergence de périmètre).
     */
    private const BILLABLE_ENROLLMENT_STATUSES = ['accepted', 'pending', 'under_review'];

    /**
     * Situation financière complète de l'élève, par inscription + récapitulatif.
     *
     * @return array{
     *     enrollments: Collection<int, object>,
     *     total_expected: float,
     *     total_paid: float,
     *     total_remaining: float,
     *     currency: string,
     *     status: string,
     *     status_label: string,
     *     next_echeance: object|null,
     *     overdue_invoices: Collection<int, object>,
     *     has_alert: bool
     * }
     */
    public function studentSituation(?Student $student): array
    {
        $empty = [
            'enrollments' => collect(),
            'total_expected' => 0.0,
            'total_paid' => 0.0,
            'total_remaining' => 0.0,
            'currency' => default_currency(),
            'status' => 'none',
            'status_label' => __('Aucune situation financière'),
            'next_echeance' => null,
            'overdue_invoices' => collect(),
            'has_alert' => false,
        ];

        if (! $student) {
            return $empty;
        }

        $enrollments = Enrollment::query()
            ->with(['formation', 'center'])
            ->where('student_id', $student->id)
            ->where('center_id', $student->center_id)
            ->whereIn('status', self::BILLABLE_ENROLLMENT_STATUSES)
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get();

        $today = now()->startOfDay();

        $rows = collect();
        $allOverdueInvoices = collect();
        $nextEcheance = null;

        foreach ($enrollments as $enrollment) {
            $formation = $enrollment->formation;
            if (! $formation) {
                continue;
            }

            // Référence = prix final de l'inscription (réduction incluse), sinon
            // tarif officiel. Cohérent avec le module Finance administrateur.
            $expected = $enrollment->getPayableAmount();

            // Versements réellement encaissés pour CETTE inscription. Une
            // tranche rattachée à une inscription est comptée une seule fois ;
            // à défaut on retombe sur la formation, ce qui évite toute double
            // comptabilisation entre plusieurs formations d'un même élève.
            // La retombée couvre aussi les anciennes sous-formations de cette
            // formation générale : les montants hérités ne disparaissent pas.
            $formationIds = AcademicPerimeter::equivalentIds($enrollment->formation_id);
            $payments = Payment::query()
                ->where('center_id', $student->center_id)
                ->where('student_id', $student->id)
                ->where('status', 'paid')
                ->where(function ($q) use ($enrollment, $formationIds): void {
                    $q->where('enrollment_id', $enrollment->id)
                        ->orWhere(function ($q2) use ($formationIds): void {
                            $q2->whereNull('enrollment_id')
                                ->whereIn('formation_id', $formationIds);
                        });
                })
                ->orderByDesc('payment_date')
                ->get();

            $paid = (float) $payments->sum('amount');
            $remaining = max(0.0, $expected - $paid);
            $currency = $payments->first()?->currency ?? default_currency();

            // Échéances contractuelles : factures rattachées à l'inscription.
            $invoices = Invoice::query()
                ->with(['payments'])
                ->where('center_id', $student->center_id)
                ->where('student_id', $student->id)
                ->where(function ($q) use ($enrollment, $formationIds): void {
                    $q->where('enrollment_id', $enrollment->id)
                        ->orWhere(function ($q2) use ($formationIds): void {
                            $q2->whereNull('enrollment_id')
                                ->whereIn('formation_id', $formationIds);
                        });
                })
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->orderBy('due_date')
                ->get();

            $echeances = $invoices->map(function (Invoice $invoice) use ($today): object {
                $paidOnInvoice = $invoice->total_paid;
                $dueRemaining = max(0.0, (float) $invoice->total_amount - $paidOnInvoice);
                $settled = $dueRemaining <= 0.01 || $invoice->status === 'paid';

                $state = match (true) {
                    $settled => 'settled',
                    $invoice->due_date === null => 'no_date',
                    $invoice->due_date->isAfter($today) => 'upcoming',
                    default => 'overdue',
                };

                return (object) [
                    'invoice' => $invoice,
                    'amount' => (float) $invoice->total_amount,
                    'paid' => (float) $paidOnInvoice,
                    'remaining' => $dueRemaining,
                    'due_date' => $invoice->due_date,
                    'state' => $state,
                ];
            });

            $overdueHere = $echeances->where('state', 'overdue')->values();

            // Prochaine échéance à venir la plus proche (toutes inscriptions).
            foreach ($echeances->where('state', 'upcoming')->sortBy('due_date') as $echeance) {
                if ($nextEcheance === null || $echeance->due_date->lessThan($nextEcheance->due_date)) {
                    $nextEcheance = (object) [
                        'formation' => $formation->general_formation_name,
                        'invoice' => $echeance->invoice,
                        'amount' => $echeance->remaining,
                        'due_date' => $echeance->due_date,
                    ];
                }
            }

            if ($overdueHere->isNotEmpty()) {
                foreach ($overdueHere as $overdue) {
                    $allOverdueInvoices->push((object) [
                        'enrollment_id' => $enrollment->id,
                        'formation' => $formation->general_formation_name,
                        'invoice' => $overdue->invoice,
                        'amount' => $overdue->remaining,
                        'due_date' => $overdue->due_date,
                    ]);
                }
            }

            $status = match (true) {
                $remaining <= 0.01 => 'paid',
                $overdueHere->isNotEmpty() => 'overdue',
                $paid > 0 => 'partial',
                default => 'pending',
            };

            $rows->push((object) [
                'enrollment' => $enrollment,
                'formation' => $formation,
                'center' => $enrollment->center,
                'expected' => $expected,
                'paid' => $paid,
                'remaining' => $remaining,
                'currency' => $currency,
                'payments' => $payments,
                'echeances' => $echeances,
                'has_overdue' => $overdueHere->isNotEmpty(),
                'status' => $status,
                'status_label' => $this->studentStatusLabel($status),
            ]);
        }

        $totalExpected = (float) $rows->sum('expected');
        $totalPaid = (float) $rows->sum('paid');
        $totalRemaining = (float) $rows->sum('remaining');

        $globalStatus = match (true) {
            $rows->isEmpty() => 'none',
            $allOverdueInvoices->isNotEmpty() => 'overdue',
            $totalRemaining <= 0.01 => 'paid',
            $totalPaid > 0 => 'partial',
            default => 'pending',
        };

        return [
            'enrollments' => $rows,
            'total_expected' => $totalExpected,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'currency' => $rows->first()?->currency ?? default_currency(),
            'status' => $globalStatus,
            'status_label' => $this->studentStatusLabel($globalStatus),
            'next_echeance' => $nextEcheance,
            'overdue_invoices' => $allOverdueInvoices->values(),
            'has_alert' => $allOverdueInvoices->isNotEmpty(),
        ];
    }

    /**
     * Situation de salaire du professeur : historique + période courante + alertes.
     *
     * @return array{
     *     salaries: Collection<int, TeacherSalary>,
     *     current: TeacherSalary|null,
     *     total_expected: float,
     *     total_paid: float,
     *     total_remaining: float,
     *     currency: string,
     *     status: string,
     *     status_label: string,
     *     overdue_salaries: Collection<int, TeacherSalary>,
     *     has_alert: bool
     * }
     */
    public function teacherSituation(?Teacher $teacher): array
    {
        $empty = [
            'salaries' => collect(),
            'current' => null,
            'total_expected' => 0.0,
            'total_paid' => 0.0,
            'total_remaining' => 0.0,
            'currency' => default_currency(),
            'status' => 'none',
            'status_label' => __('Aucun salaire enregistré'),
            'overdue_salaries' => collect(),
            'has_alert' => false,
        ];

        if (! $teacher) {
            return $empty;
        }

        $salaries = TeacherSalary::query()
            ->where('teacher_id', $teacher->id)
            ->where('center_id', $teacher->center_id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        if ($salaries->isEmpty()) {
            return $empty;
        }

        // Période courante : fiche du mois/année en cours si elle existe,
        // sinon la plus récente.
        $current = $salaries->first(
            fn (TeacherSalary $salary): bool => (int) $salary->month === (int) now()->month
                && (int) $salary->year === (int) now()->year
        ) ?? $salaries->first();

        $overdue = $salaries->filter(fn (TeacherSalary $salary): bool => $salary->personal_status === 'overdue')->values();

        $totalExpected = (float) $salaries->sum('expected_amount');
        $totalPaid = (float) $salaries->sum('paid_amount');
        $totalRemaining = (float) $salaries->sum(fn (TeacherSalary $salary): float => $salary->remaining_amount);

        return [
            'salaries' => $salaries,
            'current' => $current,
            'total_expected' => $totalExpected,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'currency' => $current?->currency ?? default_currency(),
            'status' => $current?->personal_status ?? 'none',
            'status_label' => $current?->personal_status_label ?? __('Aucun salaire enregistré'),
            'overdue_salaries' => $overdue,
            'has_alert' => $overdue->isNotEmpty(),
        ];
    }

    private function studentStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => __('Payé'),
            'partial' => __('Partiellement payé'),
            'overdue' => __('En retard'),
            'pending' => __('En attente'),
            default => __('Aucune situation financière'),
        };
    }
}
