<?php

namespace App\Services;

use App\Http\Controllers\SuperAdminDomainController;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Stagiaire;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinanceService
{
    /**
     * Prix annuels de démonstration par défaut par domaine.
     * Utilisés uniquement si aucune valeur personnalisée n'est enregistrée.
     */
    public const DEFAULT_ANNUAL_PRICES = [
        'sante' => 15000.00,
        'informatique' => 14000.00,
        'hotellerie' => 12000.00,
        'maconnerie' => 10000.00,
        'design-interieur' => 13000.00,
        'design-graphique' => 12000.00,
    ];

    /**
     * Salaires de démonstration par défaut si non renseigné.
     */
    public const DEFAULT_TEACHER_SALARY = 6000.00;

    /**
     * Années scolaires disponibles.
     */
    public const SCHOOL_YEARS = [
        '2026/2027',
        '2027/2028',
        '2028/2029',
    ];

    /**
     * Libellés des mois traduits dans la langue active.
     *
     * @return array<int, string>
     */
    public static function monthNames(): array
    {
        return [
            1 => __('Janvier'),
            2 => __('Février'),
            3 => __('Mars'),
            4 => __('Avril'),
            5 => __('Mai'),
            6 => __('Juin'),
            7 => __('Juillet'),
            8 => __('Août'),
            9 => __('Septembre'),
            10 => __('Octobre'),
            11 => __('Novembre'),
            12 => __('Décembre'),
        ];
    }

    /**
     * Retourne la liste des domaines valides.
     */
    public static function getDomains(): array
    {
        return SuperAdminDomainController::DOMAINS;
    }

    /**
     * Obtenir le prix annuel d'une formation (configurable, avec fallback démo).
     */
    public static function getFormationAnnualPrice(Formation $formation): float
    {
        if ($formation->annual_price !== null && (float) $formation->annual_price > 0) {
            return (float) $formation->annual_price;
        }

        if ($formation->price !== null && (float) $formation->price > 0) {
            return (float) $formation->price;
        }

        $domain = $formation->domain;

        return self::DEFAULT_ANNUAL_PRICES[$domain] ?? 12000.00;
    }

    /**
     * Obtenir le salaire mensuel d'un professeur (configurable, avec fallback).
     */
    public static function getTeacherMonthlySalary(Teacher $teacher): float
    {
        if ($teacher->monthly_salary !== null && (float) $teacher->monthly_salary > 0) {
            return (float) $teacher->monthly_salary;
        }

        return self::DEFAULT_TEACHER_SALARY;
    }

    /**
     * Calcule la situation financière des étudiants selon les filtres.
     *
     * @return Collection Collection de lignes pour le tableau des étudiants
     */
    public function getStudentFinancialRows(?string $domain = null, string $schoolYear = '2026/2027', ?int $centerId = null): Collection
    {
        // On récupère les inscriptions de l'année scolaire ciblée (ou toutes si 'all')
        $enrollmentsQuery = Enrollment::with(['student.user', 'formation.center', 'center', 'creator'])
            ->whereIn('status', ['accepted', 'pending', 'under_review']);

        if ($schoolYear !== 'all') {
            $enrollmentsQuery->where(function ($q) use ($schoolYear) {
                $q->where('school_year', $schoolYear)
                    ->orWhereNull('school_year');
            });
        }

        if ($centerId) {
            $enrollmentsQuery->where('center_id', $centerId);
        }

        if ($domain && $domain !== 'all') {
            $enrollmentsQuery->whereHas('formation', function ($q) use ($domain) {
                $q->where('domain', $domain);
            });
        }

        $enrollments = $enrollmentsQuery->get();

        // Récupérer tous les paiements étudiants filtrés par statut paid
        $paymentsQuery = Payment::with('recorder')->where('status', 'paid');
        if ($schoolYear !== 'all') {
            $paymentsQuery->where(function ($q) use ($schoolYear) {
                $q->where('school_year', $schoolYear)
                    ->orWhereNull('school_year');
            });
        }
        if ($centerId) {
            $paymentsQuery->where('center_id', $centerId);
        }
        if ($domain && $domain !== 'all') {
            $paymentsQuery->where(function ($q) use ($domain) {
                $q->where('domain', $domain)
                    ->orWhereHas('formation', fn ($f) => $f->where('domain', $domain));
            });
        }

        $paymentsGrouped = $paymentsQuery->get()->groupBy('student_id');

        $rows = collect();

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $formation = $enrollment->formation;
            if (! $student || ! $formation) {
                continue;
            }

            $formationDomain = $formation->domain ?? 'informatique';

            // Référence financière de l'inscription = PRIX FINAL accordé s'il a été
            // enregistré (réduction), sinon tarif officiel. Les anciennes
            // inscriptions (final_price null) conservent exactement le tarif
            // catalogue : aucun recalcul, aucune écrasement (§11).
            $expectedAmount = $enrollment->getPayableAmount();
            $officialAmount = $enrollment->officialPriceReference();
            $discountAmount = $enrollment->getDiscountAmount();

            // Additionner tous les paiements enregistrés pour cet étudiant (et cette formation si rattachée)
            $studentPayments = $paymentsGrouped->get($student->id, collect());
            $paidAmount = (float) $studentPayments->sum('amount');

            $remainingAmount = max(0.00, $expectedAmount - $paidAmount);

            // Statuts : Payé, Partiel, Non payé
            if ($paidAmount >= $expectedAmount && $expectedAmount > 0) {
                $status = 'paid';
                $statusLabel = __('Payé');
                $statusBadge = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
                $statusLabel = 'Partiel';
                $statusBadge = 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800';
            } else {
                $status = 'unpaid';
                $statusLabel = __('Non payé');
                $statusBadge = 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300 border border-rose-200 dark:border-rose-800';
            }

            $rows->push((object) [
                'enrollment' => $enrollment,
                'student' => $student,
                'formation' => $formation,
                'center' => $enrollment->center,
                'domain' => $formationDomain,
                'domain_label' => self::getDomains()[$formationDomain] ?? ucfirst($formationDomain),
                'annual_price' => $expectedAmount,
                'official_price' => $officialAmount,
                'discount_amount' => $discountAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_percentage' => $expectedAmount > 0 ? min(100.0, round(($paidAmount / $expectedAmount) * 100, 1)) : 0.0,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_badge' => $statusBadge,
                'payments' => $studentPayments,
                'last_payment' => $studentPayments->sortByDesc('payment_date')->first(),
            ]);
        }

        return $rows;
    }

    /**
     * Calcule la situation des salaires professeurs pour un mois/année donné.
     *
     * @return Collection Collection de lignes pour le tableau des professeurs
     */
    public function getTeacherSalaryRows(?string $domain = null, int $month = 10, int $year = 2026, string $schoolYear = '2026/2027', ?int $centerId = null): Collection
    {
        $teachersQuery = Teacher::with(['user', 'center'])->where('status', 'active');

        if ($centerId) {
            $teachersQuery->where('center_id', $centerId);
        }

        if ($domain && $domain !== 'all') {
            $teachersQuery->where('domain', $domain);
        }

        $teachers = $teachersQuery->get();

        // Récupérer les fiches de salaires créées pour ce mois et cette année
        $salaryRecords = TeacherSalary::where('month', $month)
            ->where('year', $year)
            ->when($centerId, fn ($q) => $q->where('center_id', $centerId))
            ->get()
            ->keyBy('teacher_id');

        $rows = collect();

        foreach ($teachers as $teacher) {
            $monthlySalary = self::getTeacherMonthlySalary($teacher);
            $record = $salaryRecords->get($teacher->id);

            $paidAmount = $record ? (float) $record->paid_amount : 0.00;
            $expected = $record ? (float) $record->expected_amount : $monthlySalary;
            if ($expected <= 0) {
                $expected = $monthlySalary;
            }

            $remaining = max(0.00, $expected - $paidAmount);

            if ($paidAmount >= $expected && $expected > 0) {
                $status = 'paid';
                $statusLabel = __('Payé');
                $statusBadge = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
                $statusLabel = 'Partiel';
                $statusBadge = 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800';
            } else {
                $status = 'unpaid';
                $statusLabel = __('Non payé');
                $statusBadge = 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300 border border-rose-200 dark:border-rose-800';
            }

            $tDomain = $teacher->domain ?? 'informatique';
            $paidPercentage = $expected > 0 ? min(100.0, round(($paidAmount / $expected) * 100, 1)) : 0.0;

            $rows->push((object) [
                'teacher' => $teacher,
                'center' => $teacher->center,
                'domain' => $tDomain,
                'domain_label' => self::getDomains()[$tDomain] ?? ucfirst($tDomain),
                'monthly_salary' => $expected,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remaining,
                'paid_percentage' => $paidPercentage,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_badge' => $statusBadge,
                'record' => $record,
            ]);
        }

        return $rows;
    }

    /**
     * Regroupe les versements réellement effectués par mois (accordéons mensuels).
     *
     * @return Collection Collection ordonnée par mois décroissant
     */
    public function getMonthlyPaymentGroups(?string $domain = null, string $schoolYear = '2026/2027', ?int $centerId = null): Collection
    {
        $paymentsQuery = Payment::with(['student.user', 'formation.center', 'center', 'recorder'])
            ->where('status', 'paid')
            ->orderBy('payment_date', 'desc');

        if ($schoolYear !== 'all') {
            $paymentsQuery->where(function ($q) use ($schoolYear) {
                $q->where('school_year', $schoolYear)
                    ->orWhereNull('school_year');
            });
        }

        if ($centerId) {
            $paymentsQuery->where('center_id', $centerId);
        }

        if ($domain && $domain !== 'all') {
            $paymentsQuery->where(function ($q) use ($domain) {
                $q->where('domain', $domain)
                    ->orWhereHas('formation', fn ($f) => $f->where('domain', $domain));
            });
        }

        $payments = $paymentsQuery->get();

        $monthNames = self::monthNames();

        return $payments->groupBy(function ($payment) {
            return $payment->payment_date ? $payment->payment_date->format('Y-m') : 'sans-date';
        })->map(function (Collection $group, string $key) use ($monthNames) {
            $totalCollected = (float) $group->sum('amount');
            $distinctStudents = $group->pluck('student_id')->unique()->count();

            $monthLabel = $key;
            $monthNum = 0;
            $yearNum = 0;
            if ($key !== 'sans-date' && preg_match('/^(\d{4})-(\d{2})$/', $key, $matches)) {
                $yearNum = (int) $matches[1];
                $monthNum = (int) $matches[2];
                $monthLabel = ($monthNames[$monthNum] ?? $matches[2]).' '.$yearNum;
            }

            return (object) [
                'period_key' => $key,
                'period_label' => $monthLabel,
                'month_num' => $monthNum,
                'year_num' => $yearNum,
                'payments_count' => $group->count(),
                'students_count' => $distinctStudents,
                'total_collected' => $totalCollected,
                'payments' => $group,
            ];
        });
    }

    /**
     * Calcule l'évolution mensuelle consolidée (encaissements vs salaires versés) pour les graphiques.
     */
    public function getMonthlyEvolution(?string $domain = null, string $schoolYear = '2026/2027', ?int $centerId = null): array
    {
        $months = collect([
            ['month' => 9,  'year' => 2026],
            ['month' => 10, 'year' => 2026],
            ['month' => 11, 'year' => 2026],
            ['month' => 12, 'year' => 2026],
            ['month' => 1,  'year' => 2027],
            ['month' => 2,  'year' => 2027],
            ['month' => 3,  'year' => 2027],
            ['month' => 4,  'year' => 2027],
            ['month' => 5,  'year' => 2027],
            ['month' => 6,  'year' => 2027],
        ])->map(static function (array $period): array {
            $period['label'] = format_date(Carbon::create($period['year'], $period['month'], 1), 'M y');

            return $period;
        })->all();

        $paymentsQuery = Payment::where('status', 'paid');
        if ($schoolYear !== 'all') {
            $paymentsQuery->where(function ($q) use ($schoolYear) {
                $q->where('school_year', $schoolYear)->orWhereNull('school_year');
            });
        }
        if ($centerId) {
            $paymentsQuery->where('center_id', $centerId);
        }
        if ($domain && $domain !== 'all') {
            $paymentsQuery->where(function ($q) use ($domain) {
                $q->where('domain', $domain)->orWhereHas('formation', fn ($f) => $f->where('domain', $domain));
            });
        }
        $payments = $paymentsQuery->get();

        $salariesQuery = TeacherSalary::where('status', 'paid');
        if ($schoolYear !== 'all') {
            $salariesQuery->where('school_year', $schoolYear);
        }
        if ($centerId) {
            $salariesQuery->where('center_id', $centerId);
        }
        if ($domain && $domain !== 'all') {
            $salariesQuery->where('domain', $domain);
        }
        $salaries = $salariesQuery->get();

        $items = [];
        $maxAmount = 5000.0;

        foreach ($months as $m) {
            $collected = (float) $payments->filter(function ($p) use ($m) {
                return $p->payment_date
                    && (int) $p->payment_date->format('n') === $m['month']
                    && (int) $p->payment_date->format('Y') === $m['year'];
            })->sum('amount');

            $salaryPaid = (float) $salaries->filter(function ($s) use ($m) {
                return (int) $s->month === $m['month'] && (int) $s->year === $m['year'];
            })->sum('paid_amount');

            if ($collected > $maxAmount) {
                $maxAmount = $collected;
            }
            if ($salaryPaid > $maxAmount) {
                $maxAmount = $salaryPaid;
            }

            $items[] = [
                'label' => $m['label'],
                'full_month' => $m['label'].' '.$m['year'],
                'month' => $m['month'],
                'year' => $m['year'],
                'collected' => $collected,
                'salaries' => $salaryPaid,
                'net' => $collected - $salaryPaid,
            ];
        }

        return [
            'items' => $items,
            'max_amount' => $maxAmount,
        ];
    }

    /**
     * Répartition des montants attendus et encaissés par domaine.
     */
    public function getDomainBreakdown(string $schoolYear = '2026/2027', ?int $centerId = null): Collection
    {
        $breakdown = collect();
        $domains = self::getDomains();

        foreach ($domains as $slug => $label) {
            $rows = $this->getStudentFinancialRows($slug, $schoolYear, $centerId);
            $expected = (float) $rows->sum('annual_price');
            $collected = (float) $rows->sum('paid_amount');
            $remaining = (float) $rows->sum('remaining_amount');
            $rate = $expected > 0 ? round(($collected / $expected) * 100, 1) : 0.0;

            $breakdown->put($slug, (object) [
                'slug' => $slug,
                'label' => $label,
                'students' => $rows->count(),
                'expected' => $expected,
                'collected' => $collected,
                'remaining' => $remaining,
                'rate' => $rate,
            ]);
        }

        return $breakdown;
    }

    /**
     * Calcule l'ensemble des KPIs financiers globaux ou filtrés.
     */
    public function getOverviewKpis(?string $domain = null, string $schoolYear = '2026/2027', int $month = 10, int $year = 2026, ?int $centerId = null): array
    {
        $studentRows = $this->getStudentFinancialRows($domain, $schoolYear, $centerId);
        $teacherRows = $this->getTeacherSalaryRows($domain, $month, $year, $schoolYear, $centerId);

        $totalEncaise = (float) $studentRows->sum('paid_amount');
        $totalAttendu = (float) $studentRows->sum('annual_price');
        $totalRestantEtudiants = (float) $studentRows->sum('remaining_amount');

        $salairesPayes = (float) $teacherRows->sum('paid_amount');
        $salairesRestant = (float) $teacherRows->sum('remaining_amount');
        $salairesAttendus = (float) $teacherRows->sum('monthly_salary');

        // Solde net de trésorerie (Paiements étudiants encaissés - Salaires professeurs payés)
        $soldeNet = $totalEncaise - $salairesPayes;

        // Taux de recouvrement des paiements étudiants
        $tauxRecouvrement = $totalAttendu > 0 ? round(($totalEncaise / $totalAttendu) * 100, 1) : 0.0;
        $tauxSalaires = $salairesAttendus > 0 ? round(($salairesPayes / $salairesAttendus) * 100, 1) : 0.0;

        return [
            'total_encaisse' => $totalEncaise,
            'total_restant_etudiants' => $totalRestantEtudiants,
            'total_attendu' => $totalAttendu,
            'salaires_payes' => $salairesPayes,
            'salaires_restant' => $salairesRestant,
            'salaires_attendus' => $salairesAttendus,
            'solde_net' => $soldeNet,
            'total_etudiants' => $studentRows->count(),
            'total_professeurs' => $teacherRows->count(),
            'taux_recouvrement' => $tauxRecouvrement,
            'taux_salaires' => $tauxSalaires,
            'etudiants_payes' => $studentRows->where('status', 'paid')->count(),
            'etudiants_partiels' => $studentRows->where('status', 'partial')->count(),
            'etudiants_impayes' => $studentRows->where('status', 'unpaid')->count(),
            'professeurs_payes' => $teacherRows->where('status', 'paid')->count(),
            'professeurs_partiels' => $teacherRows->where('status', 'partial')->count(),
            'professeurs_impayes' => $teacherRows->where('status', 'unpaid')->count(),
            'monthly_evolution' => $this->getMonthlyEvolution($domain, $schoolYear, $centerId),
            'domain_breakdown' => $this->getDomainBreakdown($schoolYear, $centerId),
        ];
    }

    /**
     * Calcule la répartition financière 70/30 sur les paiements réellement encaissés.
     *
     * Règle de répartition :
     * - Si l'inscription a été effectuée par le Super Admin : 70% pour le Super Admin, 30% pour le Responsable Centre.
     * - Si l'inscription a été effectuée depuis un centre (notamment par une secrétaire) : 70% pour le Responsable Centre, 30% pour le Super Admin.
     *
     * Origine de l'inscription :
     * Déterminée de façon fiable via le créateur de l'inscription ($enrollment->creator / created_by),
     * ou à défaut via le premier versement ($firstPayment->recorder / recorded_by).
     *
     * @param  Collection  $studentRows  Collection de StudentFinancialRow ou Payment
     * @return array{super_admin_share: float, center_admin_share: float, total_collected: float, unclassified_count: int}
     */
    public function calculateRevenueSplit(Collection $studentRows, ?int $centerId = null): array
    {
        $superAdminShare = 0.00;
        $centerAdminShare = 0.00;
        $totalCollected = 0.00;
        $unclassifiedCount = 0;

        foreach ($studentRows as $row) {
            $enrollment = $row->enrollment ?? null;
            $payments = $row->payments ?? collect();
            $paidAmount = (float) ($row->paid_amount ?? 0);

            if ($paidAmount <= 0) {
                continue;
            }

            $totalCollected += $paidAmount;

            // Déterminer l'origine de l'inscription de façon fiable
            $originRole = null;

            // 1. Priorité à l'auteur direct de l'inscription (created_by)
            if ($enrollment && $enrollment->creator) {
                $originRole = $enrollment->creator->role;
            } elseif ($enrollment && $enrollment->created_by) {
                $creator = User::find($enrollment->created_by);
                $originRole = $creator?->role;
            }

            // 2. Si non renseigné sur l'inscription, vérifier le premier versement (ou les paiements rattachés)
            if (! $originRole && $payments->isNotEmpty()) {
                $firstRecordedPayment = $payments->sortBy('created_at')->first(fn ($p) => ! empty($p->recorded_by));
                if ($firstRecordedPayment) {
                    $recorder = $firstRecordedPayment->recorder ?? User::find($firstRecordedPayment->recorded_by);
                    $originRole = $recorder?->role;
                }
            }

            // 3. Répartition selon l'origine réelle de l'inscription
            if ($originRole === User::ROLE_SUPER_ADMIN) {
                // Inscription par Super Admin : 70% Super Admin / 30% Responsable Centre
                $superAdminShare += $paidAmount * 0.70;
                $centerAdminShare += $paidAmount * 0.30;
            } elseif ($originRole === User::ROLE_SECRETARY || $originRole === User::ROLE_CENTER_ADMIN) {
                // Inscription depuis le centre (Secrétaire ou Admin Centre) : 70% Responsable Centre / 30% Super Admin
                $centerAdminShare += $paidAmount * 0.70;
                $superAdminShare += $paidAmount * 0.30;
            } else {
                // Origine non explicitement tracée (anciennes inscriptions historiques)
                // Attribution par défaut : inscription effectuée en centre
                $unclassifiedCount++;
                $centerAdminShare += $paidAmount * 0.70;
                $superAdminShare += $paidAmount * 0.30;
            }
        }

        return [
            'super_admin_share' => round($superAdminShare, 2),
            'center_admin_share' => round($centerAdminShare, 2),
            'total_collected' => round($totalCollected, 2),
            'unclassified_count' => $unclassifiedCount,
        ];
    }

    /**
     * Calcul de la répartition 70/30 pour les revenus des stagiaires.
     * - Si enregistré par Super Admin : 70% Super Admin / 30% Centre
     * - Si enregistré par Secrétaire ou Admin Centre : 70% Centre / 30% Super Admin
     *
     * @return array{super_admin_share: float, center_admin_share: float, total_collected: float}
     */
    public function calculateStagiaireRevenueSplit(?int $centerId = null): array
    {
        $query = Stagiaire::with('creator')->whereNotNull('stage_price')->where('stage_price', '>', 0);

        if ($centerId) {
            $query->where('center_id', $centerId);
        }

        $stagiaires = $query->get();

        $superAdminShare = 0.00;
        $centerAdminShare = 0.00;
        $totalCollected = 0.00;

        foreach ($stagiaires as $stagiaire) {
            $price = (float) $stagiaire->stage_price;
            $totalCollected += $price;

            $role = $stagiaire->creator?->role;

            if ($role === User::ROLE_SUPER_ADMIN) {
                $superAdminShare += $price * 0.70;
                $centerAdminShare += $price * 0.30;
            } elseif ($role === User::ROLE_SECRETARY || $role === User::ROLE_CENTER_ADMIN) {
                $centerAdminShare += $price * 0.70;
                $superAdminShare += $price * 0.30;
            } else {
                // Origine par défaut : centre
                $centerAdminShare += $price * 0.70;
                $superAdminShare += $price * 0.30;
            }
        }

        return [
            'super_admin_share' => round($superAdminShare, 2),
            'center_admin_share' => round($centerAdminShare, 2),
            'total_collected' => round($totalCollected, 2),
        ];
    }

    /**
     * Calcul du compte financier indépendant et du solde automatique pour un rôle / centre donné.
     *
     * Règle :
     * Solde = Total des revenus attribués - Total des dépenses enregistrées.
     *
     * - Super Admin : revenus attribués (élèves + stagiaires) sur tous les centres (ou centre sélectionné)
     *   moins les dépenses enregistrées par un Super Admin.
     * - Admin Centre : revenus attribués à son centre (élèves + stagiaires)
     *   moins les dépenses enregistrées pour son centre par le centre (ou dépenses de son centre).
     *
     * @return array{revenue: float, expenses: float, balance: float, is_negative: bool}
     */
    public function getAccountBalance(?int $centerId, bool $isSuperAdmin, string $schoolYear = '2026/2027'): array
    {
        // 1. Revenus des élèves (StudentFinancialRow / calculateRevenueSplit)
        $studentRows = $this->getStudentFinancialRows(null, $schoolYear, $centerId);
        $studentSplit = $this->calculateRevenueSplit($studentRows, $centerId);

        // 2. Revenus des stagiaires
        $stagiaireSplit = $this->calculateStagiaireRevenueSplit($centerId);

        if ($isSuperAdmin) {
            // Revenus attribués au Super Admin
            $attributedRevenue = $studentSplit['super_admin_share'] + $stagiaireSplit['super_admin_share'];

            // Dépenses enregistrées par le Super Admin (ou attribuées au compte Super Admin)
            $expenseQuery = Expense::where('status', 'paid')
                ->whereHas('recorder', fn ($q) => $q->where('role', User::ROLE_SUPER_ADMIN));

            if ($centerId) {
                $expenseQuery->where('center_id', $centerId);
            }

            $recordedExpenses = (float) $expenseQuery->sum('amount');
        } else {
            // Revenus attribués à l'Admin Centre
            $attributedRevenue = $studentSplit['center_admin_share'] + $stagiaireSplit['center_admin_share'];

            // Dépenses enregistrées du centre (déduites du compte du centre)
            // Inclut les dépenses du centre et les salaires payés du centre
            $expenseQuery = Expense::where('status', 'paid');
            if ($centerId) {
                $expenseQuery->where('center_id', $centerId);
                // Si la dépense a été enregistrée par le Super Admin pour son propre compte, ne pas la déduire du centre
                $expenseQuery->where(function ($q) {
                    $q->whereNull('recorded_by')
                        ->orWhereHas('recorder', fn ($sub) => $sub->where('role', '!=', User::ROLE_SUPER_ADMIN));
                });
            }

            $recordedExpenses = (float) $expenseQuery->sum('amount');

            // Ajouter les salaires des professeurs versés dans ce centre
            if ($centerId) {
                $teacherSalaries = (float) TeacherSalary::where('center_id', $centerId)
                    ->where('status', 'paid')
                    ->sum('paid_amount');
                $recordedExpenses += $teacherSalaries;
            }
        }

        $balance = round($attributedRevenue - $recordedExpenses, 2);

        return [
            'revenue' => round($attributedRevenue, 2),
            'expenses' => round($recordedExpenses, 2),
            'balance' => $balance,
            'is_negative' => $balance < 0,
        ];
    }
}
