<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\StoreStudentPaymentRequest;
use App\Http\Requests\Finance\StoreTeacherSalaryPaymentRequest;
use App\Http\Requests\Finance\UpdateFinancePricesRequest;
use App\Models\AccountingEntry;
use App\Models\BankTransaction;
use App\Models\Center;
use App\Models\CenterBudget;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Services\FinanceService;
use App\Services\NotificationService;
use App\Services\PdfService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    public function __construct(
        protected FinanceService $financeService
    ) {}

    /**
     * Domaine actif du module financier : priorité au filtre explicite `?domain`
     * (y compris « all »), sinon le domaine sélectionné dans le contexte global
     * (session active_domain). C'est ce qui « unifie » la finance avec le reste
     * de l'application : un Super Admin qui a choisi un domaine voit ses KPI et
     * listes financiers filtrés sur ce domaine sans action supplémentaire.
     */
    private function resolveDomain(Request $request): string
    {
        $domain = $request->query('domain');

        if ($domain !== null && $domain !== '') {
            return $domain;
        }

        $ctx = user_context();

        return $ctx->hasDomain() ? $ctx->domainSlug() : 'all';
    }

    /**
     * Centre de travail : filtre explicite `?center_id` pour le Super Admin,
     * sinon le centre forcé par le contexte (Admin Centre).
     */
    private function resolveCenterScope(Request $request): ?int
    {
        $ctx = user_context();

        // Admin Centre : périmètre TOUJOURS son propre centre, jamais celui fourni
        // par l'URL. Le middleware ResolveDomainContext a déjà figé le contexte ;
        // on ignore donc volontairement tout ?center_id malveillant.
        if ($ctx->isCenterAdmin()) {
            return $ctx->centerId();
        }

        // Super Admin : restriction volontaire via ?center_id, sinon le centre du
        // contexte (null = vue globale multi-centres).
        if ($centerId = $request->query('center_id')) {
            return (int) $centerId;
        }

        return $ctx->hasCenter() ? $ctx->centerId() : null;
    }

    /**
     * Garantie d'appartenance pour les actions d'écriture : un Admin Centre ne
     * peut manipuler que les données de SON centre. No-op pour le Super Admin.
     *
     * @param  int|string|null  $centerId  centre de l'entité ciblée (étudiant,
     *                                     professeur, dépense, écriture bancaire…)
     */
    private function guardOwnCenter(int|string|null $centerId): void
    {
        $user = auth()->user();

        if ($user?->isCenterAdmin() && (int) $centerId !== (int) $user->center_id) {
            abort(403, __('Accès refusé : cette donnée n\'appartient pas à votre centre.'));
        }
    }

    /**
     * Dashboard financier GLOBAL du Super Admin.
     */
    public function index(Request $request): View
    {
        $domain = $this->resolveDomain($request);
        $schoolYear = $request->query('school_year', '2026/2027');
        $month = (int) $request->query('month', (int) now()->format('n'));
        if ($month < 1 || $month > 12) {
            $month = 10;
        }
        $year = (int) $request->query('year', (int) now()->format('Y'));
        if ($year < 2020 || $year > 2035) {
            $year = 2026;
        }
        $centerId = $this->resolveCenterScope($request);

        $kpis = $this->financeService->getOverviewKpis($domain, $schoolYear, $month, $year, $centerId);
        $studentRows = $this->financeService->getStudentFinancialRows($domain, $schoolYear, $centerId);
        $teacherRows = $this->financeService->getTeacherSalaryRows($domain, $month, $year, $schoolYear, $centerId);

        $domains = FinanceService::getDomains();
        $schoolYears = FinanceService::SCHOOL_YEARS;
        $centers = Center::orderBy('name')->get();

        // Récupérer les étudiants et formations pour le modal d'ajout rapide de paiement.
        // Pour un Admin Centre, on restreint ces listes à SON centre (le Super Admin
        // conserve la vue globale / son filtre volontaire).
        $centerScoped = user_context()->isCenterAdmin() ? $centerId : null;
        $studentsList = Student::with('center')->where('status', 'active')
            ->when($centerScoped, fn ($q) => $q->where('center_id', $centerScoped))
            ->orderBy('last_name')->get();
        $formationsList = Formation::with('center')->where('status', 'active')->current()
            ->when($centerScoped, fn ($q) => $q->where('center_id', $centerScoped))
            ->orderBy('name')->get();
        $teachersList = Teacher::with('center')->where('status', 'active')
            ->when($centerScoped, fn ($q) => $q->where('center_id', $centerScoped))
            ->orderBy('last_name')->get();

        // Données JSON pour pré-remplissage dynamique dans le modal de paiement
        $studentFinancialMap = $studentRows->keyBy('student.id')->map(function ($row) {
            return [
                'student_id' => $row->student->id,
                'student_name' => $row->student->full_name,
                'student_number' => $row->student->student_number,
                'center_name' => $row->center?->name ?? 'N/A',
                // La formation générale, jamais l'ancienne sous-formation : c'est
                // la clé attendue par le `<select>` du modal de paiement.
                'formation_id' => $row->formation->general_formation_id ?? $row->formation->id,
                'formation_name' => $row->formation->general_formation_name,
                'annual_price' => $row->annual_price,
                'paid_amount' => $row->paid_amount,
                'remaining_amount' => $row->remaining_amount,
                'status' => $row->status,
            ];
        });

        $monthsNames = FinanceService::monthNames();

        return view('super-admin.finance.dashboard', compact(
            'kpis',
            'studentRows',
            'teacherRows',
            'domain',
            'schoolYear',
            'month',
            'year',
            'centerId',
            'domains',
            'schoolYears',
            'centers',
            'studentsList',
            'formationsList',
            'teachersList',
            'studentFinancialMap',
            'monthsNames'
        ));
    }

    /**
     * Vue dédiée : Paiements étudiants
     */
    public function students(Request $request): View
    {
        $domain = $this->resolveDomain($request);
        $schoolYear = $request->query('school_year', '2026/2027');
        $status = $request->query('status', 'all');
        $centerId = $this->resolveCenterScope($request);
        $formationId = $request->query('formation_id') ? (int) $request->query('formation_id') : null;
        $search = trim((string) $request->query('search', ''));
        $viewMode = $request->query('view', 'students'); // 'students' | 'monthly' | 'unpaid'

        $allStudentRows = $this->financeService->getStudentFinancialRows($domain, $schoolYear, $centerId);

        // Calcul des métriques globales de la sélection (avant filtrage textuel ou de statut)
        $stats = [
            'total_expected' => (float) $allStudentRows->sum('annual_price'),
            'total_paid' => (float) $allStudentRows->sum('paid_amount'),
            'total_remaining' => (float) $allStudentRows->sum('remaining_amount'),
            'count_paid' => $allStudentRows->where('status', 'paid')->count(),
            'count_partial' => $allStudentRows->where('status', 'partial')->count(),
            'count_unpaid' => $allStudentRows->where('status', 'unpaid')->count(),
            'total_students' => $allStudentRows->count(),
            'recovery_rate' => $allStudentRows->sum('annual_price') > 0
                ? round(($allStudentRows->sum('paid_amount') / $allStudentRows->sum('annual_price')) * 100, 1)
                : 0.0,
        ];

        $studentRows = $allStudentRows;

        // Filtre par statut si renseigné
        if ($status !== 'all') {
            $studentRows = $studentRows->where('status', $status);
        }

        // Filtre par formation
        if ($formationId) {
            $studentRows = $studentRows->filter(fn ($row) => (int) $row->formation->id === $formationId);
        }

        // Filtre par recherche texte (nom, prénom, matricule ou référence de paiement)
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $studentRows = $studentRows->filter(function ($row) use ($searchLower) {
                $nameMatches = str_contains(mb_strtolower($row->student->full_name), $searchLower)
                    || str_contains(mb_strtolower($row->student->student_number ?? ''), $searchLower);

                $refMatches = $row->payments->contains(function ($payment) use ($searchLower) {
                    return str_contains(mb_strtolower($payment->reference ?? ''), $searchLower);
                });

                return $nameMatches || $refMatches;
            });
        }

        // Mode Recouvrement : étudiants ayant un solde restant > 0
        if ($viewMode === 'unpaid') {
            $studentRows = $studentRows->filter(fn ($row) => $row->remaining_amount > 0)
                ->sortByDesc('remaining_amount');
        }

        // Récupérer les versements groupés par mois (pour la vue accordéon)
        $monthlyGroups = $this->financeService->getMonthlyPaymentGroups($domain, $schoolYear, $centerId);

        $domains = FinanceService::getDomains();
        $schoolYears = FinanceService::SCHOOL_YEARS;
        $centers = Center::orderBy('name')->get();
        $studentsList = Student::with('center')->where('status', 'active')->orderBy('last_name')->get();
        $formationsList = Formation::with('center')->where('status', 'active')->current()->orderBy('name')->get();

        // Données JSON pour le modal
        $studentFinancialMap = $allStudentRows->keyBy('student.id')->map(function ($row) {
            return [
                'student_id' => $row->student->id,
                'student_name' => $row->student->full_name,
                'student_number' => $row->student->student_number,
                'center_name' => $row->center?->name ?? 'N/A',
                'formation_id' => $row->formation->general_formation_id ?? $row->formation->id,
                'formation_name' => $row->formation->general_formation_name,
                'annual_price' => $row->annual_price,
                'paid_amount' => $row->paid_amount,
                'remaining_amount' => $row->remaining_amount,
                'status' => $row->status,
            ];
        });

        $formations = $formationsList;

        return view('super-admin.finance.students', compact(
            'studentRows',
            'monthlyGroups',
            'stats',
            'domain',
            'schoolYear',
            'status',
            'centerId',
            'formationId',
            'search',
            'viewMode',
            'domains',
            'schoolYears',
            'centers',
            'studentsList',
            'formationsList',
            'formations',
            'studentFinancialMap'
        ));
    }

    /**
     * Vue dédiée : Salaires professeurs.
     *
     * La gestion des salaires est désormais intégrée à « Dépenses » comme une
     * catégorie de charge. Cette rubrique autonome n'existe plus : on redirige
     * vers Dépenses (filtrée sur la catégorie « Salaires des professeurs ») pour
     * ne laisser qu'une seule interface de gestion, sans casser les anciens liens.
     */
    public function teachers(Request $request): RedirectResponse
    {
        $params = array_filter([
            'category_id' => $this->salaryCategoryId(),
            'school_year' => $request->query('school_year'),
            'center_id' => $request->query('center_id'),
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('super-admin.finance.expenses', $params)
            ->with('info', __('La gestion des salaires des professeurs est désormais intégrée aux Dépenses.'));
    }

    /**
     * Vue dédiée : Historique des paiements
     */
    public function history(Request $request): View
    {
        $domain = $this->resolveDomain($request);
        $schoolYear = $request->query('school_year', 'all');
        $paymentMethod = $request->query('payment_method', $request->query('method', 'all'));
        $method = $paymentMethod;
        $centerId = $this->resolveCenterScope($request);
        $search = trim((string) $request->query('search', ''));

        $paymentsQuery = Payment::with(['student.user', 'formation.center', 'center', 'recorder'])
            ->latest('payment_date');

        if ($centerId) {
            $paymentsQuery->where('center_id', $centerId);
        }

        if ($schoolYear !== 'all') {
            $paymentsQuery->where('school_year', $schoolYear);
        }

        if ($method !== 'all') {
            $paymentsQuery->where('method', $method);
        }

        if ($domain && $domain !== 'all') {
            $paymentsQuery->where(function ($q) use ($domain) {
                $q->where('domain', $domain)
                    ->orWhereHas('formation', fn ($f) => $f->where('domain', $domain));
            });
        }

        if ($search !== '') {
            $searchLower = "%{$search}%";
            $paymentsQuery->where(function ($q) use ($searchLower) {
                $q->where('reference', 'LIKE', $searchLower)
                    ->orWhere('notes', 'LIKE', $searchLower)
                    ->orWhereHas('student', function ($sq) use ($searchLower) {
                        $sq->where('first_name', 'LIKE', $searchLower)
                            ->orWhere('last_name', 'LIKE', $searchLower)
                            ->orWhere('student_number', 'LIKE', $searchLower);
                    });
            });
        }

        // Totaux globaux des paiements filtrés
        $stats = [
            'total_count' => (clone $paymentsQuery)->count(),
            'total_amount' => (float) (clone $paymentsQuery)->where('status', 'paid')->sum('amount'),
            'cash_total' => (float) (clone $paymentsQuery)->where('status', 'paid')->where('method', 'cash')->sum('amount'),
            'transfer_total' => (float) (clone $paymentsQuery)->where('status', 'paid')->where('method', 'bank_transfer')->sum('amount'),
            'card_total' => (float) (clone $paymentsQuery)->where('status', 'paid')->where('method', 'card')->sum('amount'),
        ];

        $payments = $paymentsQuery->paginate(25)->withQueryString();
        $domains = FinanceService::getDomains();
        $schoolYears = FinanceService::SCHOOL_YEARS;
        $centers = Center::orderBy('name')->get();

        return view('super-admin.finance.history', compact(
            'payments',
            'stats',
            'domain',
            'schoolYear',
            'method',
            'paymentMethod',
            'centerId',
            'search',
            'domains',
            'schoolYears',
            'centers'
        ));
    }

    /**
     * Page de configuration des prix annuels et des salaires mensuels.
     */
    public function configuration(Request $request): View
    {
        $centerId = $this->resolveCenterScope($request);

        $formationsQuery = Formation::with('center')->current()->orderBy('name');
        $teachersQuery = Teacher::with(['user', 'center'])->orderBy('last_name');

        // Admin Centre : uniquement les formations/professeurs de SON centre.
        if ($centerId) {
            $formationsQuery->where('center_id', $centerId);
            $teachersQuery->where('center_id', $centerId);
        }

        $formations = $formationsQuery->get();
        $teachers = $teachersQuery->get();
        $domains = FinanceService::getDomains();

        return view('super-admin.finance.configuration', compact('formations', 'teachers', 'domains', 'centerId'));
    }

    /**
     * Enregistrer les modifications de prix et salaires configurables.
     */
    public function updateConfiguration(UpdateFinancePricesRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Admin Centre : ne peut modifier que les prix/salaires de SON centre.
        // La clause centre_id est ajoutée à chaque UPDATE pour bloquer toute
        // identifiant d'une autre structure (défensif, même si la liste est déjà
        // filtrée côté vue).
        $centerId = $this->resolveCenterScope($request);

        if (! empty($data['formations'])) {
            foreach ($data['formations'] as $fData) {
                Formation::where('id', $fData['id'])
                    ->when($centerId, fn ($q) => $q->where('center_id', $centerId))
                    ->update([
                        'annual_price' => $fData['annual_price'],
                        'domain' => $fData['domain'] ?? null,
                    ]);
            }
        }

        if (! empty($data['teachers'])) {
            foreach ($data['teachers'] as $tData) {
                Teacher::where('id', $tData['id'])
                    ->when($centerId, fn ($q) => $q->where('center_id', $centerId))
                    ->update([
                        'monthly_salary' => $tData['monthly_salary'],
                        'domain' => $tData['domain'] ?? null,
                    ]);
            }
        }

        return redirect()->route('super-admin.finance.configuration')
            ->with('success', __('Les prix annuels et salaires mensuels ont été mis à jour avec succès.'));
    }

    /**
     * Enregistrer un paiement étudiant depuis le module financier.
     */
    public function storeStudentPayment(StoreStudentPaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $student = Student::with('center')->findOrFail($data['student_id']);
        // Multi-centres : un Admin Centre ne peut encaisser qu'un étudiant de son périmètre.
        $this->guardOwnCenter($student->center_id);
        $formation = ! empty($data['formation_id']) ? Formation::find($data['formation_id']) : null;

        // Vérification de sécurité métier : vérifier que le montant ne dépasse pas le solde restant si l'inscription existe
        $enrollment = ! empty($data['enrollment_id'])
            ? Enrollment::find($data['enrollment_id'])
            : Enrollment::where('student_id', $student->id)
                ->where('school_year', $data['school_year'])
                ->first();

        $targetFormation = $formation ?? $enrollment?->formation;
        if ($targetFormation) {
            // Référence du solde dû : le PRIX FINAL de l'inscription s'il a été
            // accordé (réduction), sinon le tarif officiel du catalogue. On ne se
            // fie jamais au prix envoyé par le navigateur.
            $expected = ($enrollment && $enrollment->final_price !== null)
                ? $enrollment->getPayableAmount()
                : FinanceService::getFormationAnnualPrice($targetFormation);
            $alreadyPaid = (float) Payment::where('student_id', $student->id)
                ->where('school_year', $data['school_year'])
                ->where('status', 'paid')
                ->sum('amount');

            $remaining = max(0.00, $expected - $alreadyPaid);
            if ((float) $data['amount'] > $remaining + 0.05 && $remaining > 0) {
                return back()->withInput()->withErrors([
                    'amount' => __("Le montant saisi (:amount) dépasse le solde restant dû de l'étudiant (:remaining).", [
                        'amount' => format_price($data['amount'], 'MAD'),
                        'remaining' => format_price($remaining, 'MAD'),
                    ]),
                ]);
            }
        }

        $reference = ! empty($data['reference']) ? $data['reference'] : Payment::generateReference($student->center);

        Payment::create([
            'center_id' => $student->center_id,
            'student_id' => $student->id,
            'formation_id' => $formation?->id,
            'enrollment_id' => $data['enrollment_id'] ?? null,
            'school_year' => $data['school_year'],
            'domain' => $formation?->domain ?? null,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'payment_date' => $data['payment_date'],
            'method' => $data['method'],
            'reference' => $reference,
            'status' => 'paid',
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        // Notification interne : paiement réellement encaissé. Le responsable du
        // centre est alerté (sauf s'il est l'auteur de l'encaissement).
        app(NotificationService::class)->notifyCenterAdmins(
            centerId: $student->center_id,
            type: 'payment',
            title: __('Nouveau paiement encaissé'),
            message: __('Paiement de :amount encaissé pour :name.', [
                'amount' => format_price($data['amount'], $data['currency']),
                'name' => $student->full_name,
            ]),
            link: route('super-admin.finance.students'),
            exceptUserId: $request->user()->id,
            relatedType: Student::class,
            relatedId: $student->id,
            metadata: ['amount' => (float) $data['amount']],
        );

        return back()->with('success', __('Paiement de :amount :currency enregistré avec succès pour :full_name.', ['amount' => $data['amount'], 'currency' => $data['currency'], 'full_name' => $student->full_name]));
    }

    /**
     * Enregistrer le paiement d'un salaire professeur.
     */
    public function storeTeacherSalaryPayment(StoreTeacherSalaryPaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $teacher = Teacher::with('center')->findOrFail($data['teacher_id']);
        // Multi-centres : un Admin Centre ne peut salayer qu'un professeur de son périmètre.
        $this->guardOwnCenter($teacher->center_id);

        $expected = ! empty($data['expected_amount']) && (float) $data['expected_amount'] > 0
            ? (float) $data['expected_amount']
            : FinanceService::getTeacherMonthlySalary($teacher);

        $paid = (float) $data['paid_amount'];

        if ($paid >= $expected && $expected > 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        } else {
            $status = 'unpaid';
        }

        TeacherSalary::updateOrCreate(
            [
                'center_id' => $teacher->center_id,
                'teacher_id' => $teacher->id,
                'month' => $data['month'],
                'year' => $data['year'],
            ],
            [
                'domain' => $teacher->domain,
                'school_year' => $data['school_year'],
                'expected_amount' => $expected,
                'paid_amount' => $paid,
                'currency' => $data['currency'],
                'status' => $status,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'] ?? 'Virement bancaire',
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]
        );

        return back()->with('success', __('Paiement de salaire enregistré pour :full_name.', ['full_name' => $teacher->full_name]));
    }

    /**
     * Page Rapports financiers.
     */
    public function reports(): View
    {
        return view('super-admin.finance.reports');
    }

    /**
     * Page Statistiques (Capture 3 Cad Syndic).
     */
    public function statistics(Request $request): View
    {
        $domain = $this->resolveDomain($request);
        $schoolYear = $request->query('school_year', '2026/2027');
        $month = (int) $request->query('month', (int) now()->format('n'));
        $year = (int) $request->query('year', (int) now()->format('Y'));
        $centerId = $this->resolveCenterScope($request);

        $kpis = $this->financeService->getOverviewKpis($domain, $schoolYear, $month, $year, $centerId);
        $domains = FinanceService::getDomains();
        $schoolYears = FinanceService::SCHOOL_YEARS;
        $centers = Center::orderBy('name')->get();

        // Récupérer les données réelles de dépenses pour le comparatif
        $expensesQuery = Expense::where('status', 'paid');
        if ($centerId) {
            $expensesQuery->where('center_id', $centerId);
        }
        $expensesTotal = (float) $expensesQuery->sum('amount');

        return view('super-admin.finance.statistics', compact(
            'kpis',
            'domain',
            'schoolYear',
            'month',
            'year',
            'centerId',
            'domains',
            'schoolYears',
            'centers',
            'expensesTotal'
        ));
    }

    /**
     * Page Dépenses (Capture 1 Cad Syndic).
     */
    public function expenses(Request $request): View
    {
        $schoolYear = $request->query('school_year', '2026/2027');
        $centerId = $this->resolveCenterScope($request);
        $categoryId = $request->query('category_id') ? (int) $request->query('category_id') : null;
        $search = $request->query('search');

        $salaryCategoryId = $this->salaryCategoryId();

        // Catégorie sélectionnée = « Salaires » ⇒ on n'affiche que les salaires ;
        // autre catégorie ⇒ uniquement les dépenses de cette catégorie ;
        // aucune catégorie ⇒ vue unifiée dépenses + salaires.
        $isSalaryFilter = $categoryId === $salaryCategoryId;
        $showExpenses = ! $categoryId || ! $isSalaryFilter;
        $showSalaries = ! $categoryId || $isSalaryFilter;

        // ── Dépenses classiques ────────────────────────────────────────────
        $chargeRows = collect();

        if ($showExpenses) {
            $query = Expense::with(['category', 'center', 'recorder', 'items'])
                ->where('school_year', $schoolYear);

            if ($centerId) {
                $query->where('center_id', $centerId);
            }
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            foreach ($query->get() as $expense) {
                $chargeRows->push($this->expenseChargeRow($expense));
            }
        }

        // ── Salaires des professeurs (source unique : table teacher_salaries) ─
        $salaryStats = ['expected' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'count' => 0];

        if ($showSalaries) {
            $salaryQuery = TeacherSalary::with(['teacher', 'center'])
                ->where('school_year', $schoolYear);

            if ($centerId) {
                $salaryQuery->where('center_id', $centerId);
            }

            foreach ($salaryQuery->get() as $salary) {
                if ($search) {
                    $hay = mb_strtolower(
                        ($salary->teacher?->full_name ?? '').' '.
                        ($salary->reference ?? '').' '.
                        ($salary->notes ?? '')
                    );
                    if (! str_contains($hay, mb_strtolower($search))) {
                        continue;
                    }
                }

                $row = $this->salaryChargeRow($salary);
                $chargeRows->push($row);

                $salaryStats['expected'] += $row->amount;
                $salaryStats['paid'] += $row->realized_amount;
                $salaryStats['due'] += $row->due_amount;
                $salaryStats['count']++;
            }
        }

        // Tri chronologique inverse (charge la plus récente en tête)
        $chargeRows = $chargeRows->sortByDesc(fn ($r) => optional($r->date)->getTimestamp() ?? 0)->values();

        // ── Totaux cohérents : engagé (commité) vs réalisé (sortie trésorerie) ─
        // Un salaire n'est JAMAIS présent dans la table des dépenses : il n'est
        // donc compté qu'une seule fois. Le « réalisé » n'inclut que ce qui a été
        // effectivement payé (dépense de statut payé / montant versé du salaire).
        $totalExpenses = (float) $chargeRows->sum('amount');          // charges engagées
        $realizedTotal = (float) $chargeRows->sum('realized_amount');  // sorties réalisées
        $dueTotal = (float) $chargeRows->sum('due_amount');            // reste à payer

        // Budget annuel
        $budgetQuery = CenterBudget::where('school_year', $schoolYear);
        if ($centerId) {
            $budgetQuery->where('center_id', $centerId);
        }
        $annualBudget = (float) $budgetQuery->sum('annual_allocated_budget');
        if ($annualBudget <= 0) {
            $annualBudget = 500000.00; // Seuil de référence par défaut
        }

        $remainingBudget = max(0, $annualBudget - $totalExpenses);
        $consumptionRate = $annualBudget > 0 ? round(($totalExpenses / $annualBudget) * 100, 1) : 0;

        // Groupement par mois pour les accordéons Cad Syndic
        $monthsNames = FinanceService::monthNames();
        $groupedExpenses = $chargeRows->groupBy(fn ($r) => $r->period_key)->map(function ($items, $key) use ($monthsNames) {
            $hasDate = (bool) preg_match('/^\d{4}-\d{2}$/', (string) $key);
            $date = $hasDate ? Carbon::parse($key.'-01') : null;
            $mNum = $date ? (int) $date->format('n') : 0;
            $mLabel = $date
                ? ($monthsNames[$mNum] ?? '').' '.$date->format('Y')
                : __('Sans date');

            return (object) [
                'period_key' => $key,
                'period_label' => $mLabel,
                'badge' => $date ? strtoupper(substr($monthsNames[$mNum] ?? 'MOIS', 0, 3)) : '—',
                'expenses_count' => $items->count(),
                'total_amount' => (float) $items->sum('amount'),
                'realized_amount' => (float) $items->sum('realized_amount'),
                'due_amount' => (float) $items->sum('due_amount'),
                'cash_total' => (float) $items->where('method', 'cash')->sum('amount'),
                'check_total' => (float) $items->where('method', 'check')->sum('amount'),
                'transfer_total' => (float) $items->where('method', 'bank_transfer')->sum('amount'),
                'items' => $items,
            ];
        });

        $categories = ExpenseCategory::orderBy('name')->get();
        $centers = Center::orderBy('name')->get();
        $schoolYears = FinanceService::SCHOOL_YEARS;
        $teachersList = Teacher::with('center')->where('status', 'active')
            ->when($centerId, fn ($q) => $q->where('center_id', $centerId))
            ->orderBy('last_name')->get();
        $monthsNames = FinanceService::monthNames();

        return view('super-admin.finance.expenses', compact(
            'chargeRows',
            'groupedExpenses',
            'annualBudget',
            'totalExpenses',
            'realizedTotal',
            'dueTotal',
            'salaryStats',
            'remainingBudget',
            'consumptionRate',
            'categories',
            'centers',
            'schoolYears',
            'schoolYear',
            'centerId',
            'categoryId',
            'salaryCategoryId',
            'search',
            'teachersList',
            'monthsNames'
        ));
    }

    /**
     * Normalise une dépense classique en ligne de charge unifiée.
     */
    private function expenseChargeRow(Expense $expense): object
    {
        $isCancelled = $expense->status === 'cancelled';
        $amount = (float) $expense->amount;
        $realized = (! $isCancelled && $expense->status === 'paid') ? $amount : 0.0;

        return (object) [
            'type' => 'expense',
            'id' => $expense->id,
            'date' => $expense->expense_date,
            'period_key' => $expense->expense_date ? $expense->expense_date->format('Y-m') : 'sans-date',
            'nature' => $expense->translated('title'),
            'party' => $expense->supplier,
            'teacher_name' => null,
            'category_label' => $expense->category?->translated('name') ?? __('Générale'),
            'is_salary' => false,
            'amount' => $isCancelled ? 0.0 : $amount,       // engagée = 0 si annulée
            'realized_amount' => $realized,
            'due_amount' => $isCancelled ? 0.0 : max(0, $amount - $realized),
            'method' => $expense->payment_method,
            'status' => $expense->status,
            'status_label' => $this->expenseStatusLabel($expense->status),
            'reference' => $expense->reference,
            'has_receipt' => (bool) $expense->receipt_path,
            'items' => $expense->items,
            'can_cancel' => ! $isCancelled,
            'can_delete' => true,
            'model' => $expense,
        ];
    }

    /**
     * Normalise un salaire professeur en ligne de charge unifiée.
     */
    private function salaryChargeRow(TeacherSalary $salary): object
    {
        $expected = (float) $salary->expected_amount;
        $paid = (float) $salary->paid_amount;
        $due = max(0.0, $expected - $paid);

        // Date de rattachement : date de versement sinon premier jour de la période.
        $date = $salary->payment_date
            ?: Carbon::create($salary->year, $salary->month, 1)->startOfMonth();

        return (object) [
            'type' => 'salary',
            'id' => $salary->id,
            'date' => $date,
            'period_key' => $date->format('Y-m'),
            'nature' => __('Salaire — :teacher', ['teacher' => $salary->teacher?->full_name ?? '—']),
            'party' => $salary->teacher?->full_name ?? '—',
            'teacher_name' => $salary->teacher?->full_name,
            'category_label' => __('Salaires des professeurs'),
            'is_salary' => true,
            'amount' => $expected,          // charge engagée
            'realized_amount' => $paid,     // sortie de trésorerie réelle
            'due_amount' => $due,           // reste dû (non présenté comme payé)
            'method' => $salary->payment_method,
            'status' => $salary->status,
            'status_label' => $salary->status_label,
            'reference' => $salary->reference,
            'period_label' => $salary->month_name.' '.$salary->year,
            'has_receipt' => false,
            'items' => collect(),
            'can_cancel' => false,
            'can_delete' => false,
            'model' => null,
        ];
    }

    private function expenseStatusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => __('Payé'),
            'pending' => __('En attente'),
            'cancelled' => __('Annulé'),
            default => ucfirst((string) $status),
        };
    }

    /**
     * Id de la catégorie de charge « Salaires des professeurs ». Créée à la volée
     * (firstOrCreate) pour les bases déjà en place, sans migration ni reset.
     */
    private function salaryCategoryId(): int
    {
        $category = ExpenseCategory::firstOrCreate(
            ['code' => 'salaires'],
            [
                'name' => 'Salaires des professeurs',
                'color' => '#8B5CF6',
                'description' => 'Rémunérations versées aux professeurs',
            ]
        );

        return (int) $category->id;
    }

    /**
     * Enregistrer une nouvelle dépense avec ses articles et justificatif.
     */
    public function storeExpense(Request $request): RedirectResponse
    {
        // ── Catégorie « Salaires des professeurs » ⇒ workflow salaire intégré ──
        // On réutilise la table TeacherSalary (source unique) : AUCUNE ligne
        // Expense n'est créée pour un salaire → zéro doublon comptable.
        if ((int) $request->input('category_id') === $this->salaryCategoryId()) {
            return $this->storeSalaryExpense($request);
        }

        $validated = $request->validate([
            'center_id' => 'required|exists:centers,id',
            'category_id' => 'nullable|exists:expense_categories,id',
            'title' => 'required|string|max:255',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:cash,check,bank_transfer,credit_card,other',
            'supplier' => 'nullable|string|max:150',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'school_year' => 'nullable|string|max:20',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|numeric|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        // Un Admin Centre ne peut pas dévier du centre : ré-ancrage serveur défensif.
        if ($request->user()?->isCenterAdmin()) {
            $validated['center_id'] = $request->user()->center_id;
        }

        // Calcul du montant total à partir des articles s'ils sont fournis
        $itemsData = $request->input('items', []);
        $totalCalculated = 0.00;

        if (! empty($itemsData)) {
            foreach ($itemsData as $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $totalCalculated += ($qty * $unitPrice);
            }
        }

        // Si aucun article n'a été spécifié, utiliser le montant direct
        $finalAmount = $totalCalculated > 0 ? $totalCalculated : (float) ($request->input('amount') ?? 0);
        if ($finalAmount <= 0) {
            return back()->withErrors(['amount' => __('Le montant total de la dépense doit être supérieur à zéro.')])->withInput();
        }

        // Traitement du justificatif
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('expenses/receipts', 'public');
        }

        DB::transaction(function () use ($validated, $request, $finalAmount, $receiptPath, $itemsData) {
            $expense = Expense::create([
                'center_id' => $validated['center_id'],
                'category_id' => $validated['category_id'] ?? null,
                'title' => $validated['title'],
                'amount' => $finalAmount,
                'currency' => 'MAD',
                'expense_date' => $validated['expense_date'],
                'payment_method' => $validated['payment_method'],
                'supplier' => $validated['supplier'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'receipt_path' => $receiptPath,
                'description' => $validated['description'] ?? null,
                'school_year' => $validated['school_year'] ?? '2026/2027',
                'status' => 'paid',
                'recorded_by' => $request->user()->id,
            ]);

            // Enregistrer les articles achetés
            if (! empty($itemsData)) {
                foreach ($itemsData as $it) {
                    $qty = (int) ($it['quantity'] ?? 1);
                    $unitPrice = (float) ($it['unit_price'] ?? 0);
                    ExpenseItem::create([
                        'expense_id' => $expense->id,
                        'name' => $it['name'],
                        'unit_price' => $unitPrice,
                        'quantity' => $qty,
                        'total_price' => ($qty * $unitPrice),
                    ]);
                }
            }

            // Tracer dans le journal bancaire pour les paiements bancaires, cartes ou chèques
            if (in_array($validated['payment_method'], ['bank_transfer', 'check', 'credit_card'])) {
                $lastBalance = (float) BankTransaction::where('center_id', $validated['center_id'])->latest('id')->value('running_balance') ?? 0;
                BankTransaction::create([
                    'center_id' => $validated['center_id'],
                    'transaction_date' => $validated['expense_date'],
                    'label' => 'Dépense : '.$validated['title'].($expense->supplier ? " ({$expense->supplier})" : ''),
                    'origin' => 'Dépense d\'exploitation',
                    'method' => $validated['payment_method'],
                    'type' => 'withdrawal',
                    'amount' => $finalAmount,
                    'running_balance' => $lastBalance - $finalAmount,
                    'reference' => $validated['reference'] ?? ('DEP-'.str_pad($expense->id, 5, '0', STR_PAD_LEFT)),
                    'receipt_path' => $receiptPath,
                    'expense_id' => $expense->id,
                    'recorded_by' => $request->user()->id,
                ]);
            }

            // Écriture comptable (Débit Compte de charge 6122, Crédit Banque 5141 ou Caisse 5161)
            AccountingEntry::create([
                'center_id' => $validated['center_id'],
                'piece_number' => $validated['reference'] ?? ('DEP-'.str_pad($expense->id, 5, '0', STR_PAD_LEFT)),
                'entry_date' => $validated['expense_date'],
                'entry_type' => 'Dépense',
                'label' => $validated['title'],
                'third_party' => $validated['supplier'] ?? 'Fournisseur divers',
                'account_code' => '6122',
                'debit' => $finalAmount,
                'credit' => 0.00,
                'cumulative_balance' => -$finalAmount,
                'recorded_by' => $request->user()->id,
            ]);

            // Dépasse le seuil « important » configurable → alerte le Super Admin.
            if ($finalAmount > (float) config('notifications.expense_threshold', 5000)) {
                app(NotificationService::class)->notifySuperAdmins(
                    type: 'expense',
                    title: __('Dépense importante'),
                    message: __('Dépense de :amount (:title) au centre :center.', [
                        'amount' => format_price($finalAmount, 'MAD'),
                        'title' => $validated['title'],
                        'center' => $expense->center?->name ?? '—',
                    ]),
                    link: route('super-admin.finance.expenses'),
                    centerId: $validated['center_id'],
                    relatedType: Expense::class,
                    relatedId: $expense->id,
                    metadata: ['amount' => $finalAmount],
                );
            }
        });

        return back()->with('success', __('Achat de :amount enregistré avec succès.', ['amount' => format_price($finalAmount, 'MAD')]));
    }

    /**
     * Enregistrer un salaire professeur depuis le formulaire « Dépenses ».
     *
     * Réutilise intégralement la logique existante (TeacherSalary::updateOrCreate,
     * statut dérivé des montants) : pas de deuxième moteur de calcul, pas de
     * doublon (updateOrCreate sur centre+prof+mois+année), aucune ligne Expense.
     * Le centre est TOUJOURS celui du professeur (résolu côté serveur).
     */
    private function storeSalaryExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'expected_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'in:cash,check,bank_transfer,credit_card,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $teacher = Teacher::with('center')->findOrFail($validated['teacher_id']);

        // Sécurité multi-centres : un Admin Centre ne peut payer qu'un professeur
        // de son périmètre (le centre est dérivé du professeur, jamais du POST).
        if ($request->user()?->isCenterAdmin()
            && (int) $teacher->center_id !== (int) $request->user()->center_id) {
            abort(403, __('Ce professeur n\'appartient pas à votre centre.'));
        }

        $expected = ! empty($validated['expected_amount']) && (float) $validated['expected_amount'] > 0
            ? (float) $validated['expected_amount']
            : FinanceService::getTeacherMonthlySalary($teacher);

        $paid = (float) $validated['paid_amount'];

        // Statut dérivé des montants réels (règle §6 : un salaire en attente
        // n'est jamais présenté comme une sortie de trésorerie réalisée).
        $status = match (true) {
            $expected > 0 && $paid >= $expected => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };

        $salary = DB::transaction(function () use ($validated, $teacher, $expected, $paid, $status, $request) {
            return TeacherSalary::updateOrCreate(
                [
                    'center_id' => $teacher->center_id,
                    'teacher_id' => $teacher->id,
                    'month' => $validated['month'],
                    'year' => $validated['year'],
                ],
                [
                    'domain' => $teacher->domain,
                    'school_year' => $validated['school_year'] ?? '2026/2027',
                    'expected_amount' => $expected,
                    'paid_amount' => $paid,
                    'currency' => $validated['currency'] ?? 'MAD',
                    'status' => $status,
                    'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                    'payment_method' => $validated['payment_method'] ?? 'bank_transfer',
                    'reference' => $validated['reference'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]
            );
        });

        return back()->with('success', __('Salaire de :amount enregistré pour :full_name.', [
            'amount' => format_price($paid, $salary->currency),
            'full_name' => $teacher->full_name,
        ]));
    }

    /**
     * Annuler une dépense enregistrée.
     */
    public function cancelExpense(Expense $expense): RedirectResponse
    {
        $this->guardOwnCenter($expense->center_id);

        if ($expense->status === 'cancelled') {
            return back()->with('error', __('Cette dépense est déjà annulée.'));
        }

        $expense->update(['status' => 'cancelled']);

        // Si une écriture bancaire était associée, créer une contrepassation de crédit
        $linkedBank = BankTransaction::where('expense_id', $expense->id)->first();
        if ($linkedBank) {
            $lastBalance = (float) BankTransaction::where('center_id', $expense->center_id)->latest('id')->value('running_balance') ?? 0;
            BankTransaction::create([
                'center_id' => $expense->center_id,
                'transaction_date' => now()->toDateString(),
                'label' => 'Contrepassation annulation : '.$expense->title,
                'origin' => 'Annulation dépense',
                'method' => $linkedBank->method,
                'type' => 'deposit',
                'amount' => $expense->amount,
                'running_balance' => $lastBalance + (float) $expense->amount,
                'reference' => 'ANNUL-'.$expense->id,
                'recorded_by' => auth()->id(),
            ]);
        }

        return back()->with('success', __('Dépense annulée avec succès et budget réajusté.'));
    }

    /**
     * Supprimer une dépense.
     */
    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $this->guardOwnCenter($expense->center_id);

        // Supprimer les transactions bancaires et comptables liées
        BankTransaction::where('expense_id', $expense->id)->delete();
        $expense->delete();

        return back()->with('success', __('Dépense supprimée.'));
    }

    /**
     * Télécharger ou prévisualiser le justificatif d'une dépense.
     */
    public function downloadExpenseReceipt(Expense $expense)
    {
        $this->guardOwnCenter($expense->center_id);

        if (! $expense->receipt_path || ! Storage::disk('public')->exists($expense->receipt_path)) {
            return back()->with('error', __('Aucun justificatif trouvé pour cette dépense.'));
        }

        return Storage::disk('public')->response($expense->receipt_path);
    }

    /**
     * Generate a localized PDF receipt for an expense (Arabic-safe).
     * Supports mode=inline (print viewer) and attachment (download) with the
     * exact same document content.
     */
    public function expenseReceiptPdf(Request $request, Expense $expense, PdfService $pdf): Response
    {
        $this->guardOwnCenter($expense->center_id);

        $expense->load(['center', 'category', 'items', 'recorder']);

        $filename = 'recu-'.($expense->reference ?: $expense->id).'.pdf';
        $view = 'super-admin.finance.pdf.expense-receipt';
        $data = ['expense' => $expense];

        return $request->query('mode') === 'inline'
            ? $pdf->inline($view, $data, $filename)
            : $pdf->download($view, $data, $filename);
    }

    /**
     * Page Recouvrement (Capture 6 Cad Syndic).
     */
    public function recovery(Request $request): View
    {
        $domain = $this->resolveDomain($request);
        $schoolYear = $request->query('school_year', '2026/2027');
        $centerId = $this->resolveCenterScope($request);
        $search = $request->query('search');

        $rows = $this->financeService->getStudentFinancialRows($domain, $schoolYear, $centerId);

        // Filtrer uniquement les dossiers avec un reste à charge
        $debtors = $rows->where('remaining_amount', '>', 0);

        if ($search) {
            $debtors = $debtors->filter(function ($row) use ($search) {
                return str_contains(strtolower($row->student->full_name), strtolower($search))
                    || str_contains(strtolower($row->student->student_number ?? ''), strtolower($search))
                    || str_contains(strtolower($row->formation->general_formation_name), strtolower($search));
            });
        }

        $debtors = $debtors->sortByDesc('remaining_amount');

        $totalDebt = (float) $debtors->sum('remaining_amount');
        $debtorsCount = $debtors->count();
        $unpaidCount = $debtors->where('status', 'unpaid')->count();

        $domains = FinanceService::getDomains();
        $schoolYears = FinanceService::SCHOOL_YEARS;
        $centers = Center::orderBy('name')->get();

        return view('super-admin.finance.recovery', compact(
            'debtors',
            'totalDebt',
            'debtorsCount',
            'unpaidCount',
            'domain',
            'schoolYear',
            'centerId',
            'domains',
            'schoolYears',
            'centers',
            'search'
        ));
    }

    /**
     * Page Compte bancaire (Capture 7 Cad Syndic).
     */
    public function bank(Request $request): View
    {
        $centerId = $this->resolveCenterScope($request);
        $type = $request->query('type', 'all');
        $search = $request->query('search');

        $query = BankTransaction::with(['center', 'recorder']);

        if ($centerId) {
            $query->where('center_id', $centerId);
        }
        if ($type !== 'all') {
            $query->where('type', $type);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('origin', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderByDesc('transaction_date')->orderByDesc('id')->get();

        $totalDeposits = (float) BankTransaction::when($centerId, fn ($q) => $q->where('center_id', $centerId))->where('type', 'deposit')->sum('amount');
        $totalWithdrawals = (float) BankTransaction::when($centerId, fn ($q) => $q->where('center_id', $centerId))->where('type', 'withdrawal')->sum('amount');
        $bankBalance = $totalDeposits - $totalWithdrawals;

        $centers = Center::orderBy('name')->get();

        return view('super-admin.finance.bank', compact(
            'transactions',
            'totalDeposits',
            'totalWithdrawals',
            'bankBalance',
            'centerId',
            'type',
            'search',
            'centers'
        ));
    }

    /**
     * Enregistrer une écriture bancaire manuelle.
     */
    public function storeBankTransaction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'center_id' => 'required|exists:centers,id',
            'transaction_date' => 'required|date',
            'label' => 'required|string|max:255',
            'origin' => 'required|string|max:100',
            'method' => 'required|in:bank_transfer,check,cash,credit_card,other',
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:100',
        ]);

        // Un Admin Centre ne peut pas dévier du centre : ré-ancrage serveur défensif.
        if ($request->user()?->isCenterAdmin()) {
            $validated['center_id'] = $request->user()->center_id;
        }

        $lastBalance = (float) BankTransaction::where('center_id', $validated['center_id'])->latest('id')->value('running_balance') ?? 0;
        $amount = (float) $validated['amount'];
        $newBalance = $validated['type'] === 'deposit' ? ($lastBalance + $amount) : ($lastBalance - $amount);

        $validated['running_balance'] = $newBalance;
        $validated['recorded_by'] = $request->user()->id;

        BankTransaction::create($validated);

        return back()->with('success', __('Écriture bancaire enregistrée.'));
    }

    /**
     * Page Comptabilité : Journal, CPC, Bilan, Grand Livre (Capture 8 Cad Syndic).
     */
    public function accounting(Request $request): View
    {
        $tab = $request->query('tab', 'journal'); // journal, cpc, bilan, grand_livre
        $centerId = $this->resolveCenterScope($request);
        $year = (int) $request->query('year', (int) now()->format('Y'));

        // Données consolidées réelles
        $paymentsQuery = Payment::where('status', 'paid');
        $expensesQuery = Expense::where('status', 'paid');
        $salariesQuery = TeacherSalary::where('status', 'paid');

        if ($centerId) {
            $paymentsQuery->where('center_id', $centerId);
            $expensesQuery->where('center_id', $centerId);
            $salariesQuery->where('center_id', $centerId);
        }

        $totalProducts = (float) $paymentsQuery->sum('amount');
        $totalChargesExpenses = (float) $expensesQuery->sum('amount');
        $totalChargesSalaries = (float) $salariesQuery->sum('paid_amount');
        $totalCharges = $totalChargesExpenses + $totalChargesSalaries;
        $netResult = $totalProducts - $totalCharges;

        // Journal des écritures réelles
        $entries = AccountingEntry::when($centerId, fn ($q) => $q->where('center_id', $centerId))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $centers = Center::orderBy('name')->get();

        return view('super-admin.finance.accounting', compact(
            'tab',
            'centerId',
            'year',
            'centers',
            'totalProducts',
            'totalCharges',
            'totalChargesExpenses',
            'totalChargesSalaries',
            'netResult',
            'entries'
        ));
    }
}
