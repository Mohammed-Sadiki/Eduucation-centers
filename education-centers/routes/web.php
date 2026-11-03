<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\CenterAdminDomainController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\ClassPlanController;
use App\Http\Controllers\ClassTeacherController;
use App\Http\Controllers\ContentTranslationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\FinanceDashboardController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\GeneralFormationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StagiaireController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\SuperAdminDomainController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherPortalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────────────────────
//  SITE PUBLIC
// ──────────────────────────────────────────────────────────────────────────────
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/centres', [PublicController::class, 'centers'])->name('public.centers');
Route::get('/centres/{center}', [PublicController::class, 'centerShow'])->name('public.centers.show');
Route::get('/offres-formations', [PublicController::class, 'formations'])->name('public.formations');
Route::get('/offres-formations/{formation}', [PublicController::class, 'formationShow'])->name('public.formations.show');
Route::get('/actualites', [PublicController::class, 'announcements'])->name('public.announcements');
Route::get('/actualites/{slug}', [PublicController::class, 'announcementShow'])->name('public.announcements.show');
Route::get('/contact', [PublicController::class, 'contactForm'])->name('public.contact');
Route::post('/contact', [PublicController::class, 'contactStore'])->name('public.contact.store');
Route::get('/candidature', [PublicController::class, 'applyForm'])->name('public.apply');
Route::post('/candidature', [PublicController::class, 'applyStore'])->name('public.apply.store');
Route::get('/candidature/confirmation', [PublicController::class, 'applySuccess'])->name('public.apply.success');
Route::get('/verifier-document', [CertificateController::class, 'verify'])->name('public.verify-certificate');

// ──────────────────────────────────────────────────────────────────────────────
//  CHANGEMENT DE LANGUE
// ──────────────────────────────────────────────────────────────────────────────
Route::match(['get', 'post'], '/locale/{locale}', [LocaleController::class, 'switch'])
    ->name('locale.switch')
    ->where('locale', 'fr|ar');

// ──────────────────────────────────────────────────────────────────────────────
//  AUTHENTIFICATION (Guest uniquement)
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ──────────────────────────────────────────────────────────────────────────────
//  ESPACE AUTHENTIFIÉ — Tous les rôles
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Accueil commun Super Admin + Admin Centre (nouvelle page 4 cartes) ──
    Route::middleware('role:super_admin,center_admin')->group(function () {
        Route::get('/admin/accueil', [DashboardController::class, 'adminHome'])->name('admin.home');
    });

    // Notifications (tous les utilisateurs authentifiés)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::get('/notifications/dropdown', [NotificationController::class, 'dropdown'])->name('notifications.dropdown');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // ──────────────────────────────────────────────────────────────────────────
    //  SUPER ADMIN uniquement
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {
        // ── Sélection des domaines (page d'entrée du Super Admin) ──
        Route::get('/super-admin/domaines', [SuperAdminDomainController::class, 'index'])
            ->name('super-admin.domaines');

        // ── Dashboard Santé (route nommée conservée pour compatibilité) ──
        Route::get('/super-admin/domaines/sante', [SuperAdminDomainController::class, 'santeDashboard'])
            ->name('super-admin.sante.dashboard');

        // ── Dashboards des domaines (route paramétrée générique, pilotée par la base) ──
        // Le slug est validé côté contrôleur contre la source de vérité `domains`
        // (404 si inconnu). La route « sante » ci-dessus reste prioritaire (alias).
        // Quitter le contexte domaine pour revenir à « tous les domaines » (avant le générique).
        Route::get('/super-admin/domaines/all', [SuperAdminDomainController::class, 'clearDomain'])
            ->name('super-admin.domain.clear');

        Route::get('/super-admin/domaines/{domain}', [SuperAdminDomainController::class, 'domainDashboard'])
            ->name('super-admin.domain.dashboard')
            ->where('domain', '[a-zA-Z0-9\-]+');

    });

    // ── Gestion financière (Super Admin global + Admin Centre) ──
    // Le module est ouvert à l'Admin Centre, mais chaque requête est forcée sur
    // SON centre côté serveur (Controller::resolveCenterScope + garanties
    // d'appartenance dans les actions d'écriture). Le Super Admin garde la vue
    // multi-centres (restriction volontaire via ?center_id).
    Route::middleware('role:super_admin,center_admin')->group(function () {
        Route::prefix('super-admin/finance')->name('super-admin.finance.')->group(function () {
            // 1. Tableau de bord
            Route::get('/', [FinanceDashboardController::class, 'index'])->name('dashboard');
            // 2. Statistiques
            Route::get('/statistiques', [FinanceDashboardController::class, 'statistics'])->name('statistics');
            // 3. Gestion financière : Paiements
            Route::get('/etudiants', [FinanceDashboardController::class, 'students'])->name('students');
            Route::post('/paiements-etudiants', [FinanceDashboardController::class, 'storeStudentPayment'])->name('students.payment.store');
            // 3. Gestion financière : Dépenses
            Route::get('/depenses', [FinanceDashboardController::class, 'expenses'])->name('expenses');
            Route::post('/depenses', [FinanceDashboardController::class, 'storeExpense'])->name('expenses.store');
            Route::patch('/depenses/{expense}/cancel', [FinanceDashboardController::class, 'cancelExpense'])->name('expenses.cancel');
            Route::delete('/depenses/{expense}', [FinanceDashboardController::class, 'destroyExpense'])->name('expenses.destroy');
            Route::get('/depenses/{expense}/justificatif', [FinanceDashboardController::class, 'downloadExpenseReceipt'])->name('expenses.receipt');
            Route::get('/depenses/{expense}/recu-pdf', [FinanceDashboardController::class, 'expenseReceiptPdf'])->name('expenses.receipt-pdf');
            // 3. Gestion financière : Recouvrement
            Route::get('/recouvrement', [FinanceDashboardController::class, 'recovery'])->name('recovery');
            // 4. Compte bancaire
            Route::get('/compte-bancaire', [FinanceDashboardController::class, 'bank'])->name('bank');
            Route::post('/compte-bancaire', [FinanceDashboardController::class, 'storeBankTransaction'])->name('bank.store');
            // 5. Comptabilité (Journal, CPC, Bilan, Grand Livre)
            Route::get('/comptabilite', [FinanceDashboardController::class, 'accounting'])->name('accounting');
            // 6. Salaires des professeurs
            Route::get('/professeurs', [FinanceDashboardController::class, 'teachers'])->name('teachers');
            Route::post('/salaires-professeurs', [FinanceDashboardController::class, 'storeTeacherSalaryPayment'])->name('teachers.salary.store');
            // 7. Configuration des prix & budgets
            Route::get('/configuration', [FinanceDashboardController::class, 'configuration'])->name('configuration');
            Route::put('/configuration', [FinanceDashboardController::class, 'updateConfiguration'])->name('configuration.update');
            // 8. Rapports financiers & Historique
            Route::get('/historique', [FinanceDashboardController::class, 'history'])->name('history');
            Route::get('/rapports', [FinanceDashboardController::class, 'reports'])->name('reports');
        });
    });

    // ── SUPER ADMIN uniquement (suite) ──
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('centers', CenterController::class);
        Route::patch('centers/{center}/toggle-status', [CenterController::class, 'toggleStatus'])
            ->name('centers.toggle-status');
        // Traductions du contenu dynamique (corrections manuelles)
        Route::prefix('super-admin/translations')->name('super-admin.translations.')->group(function () {
            Route::get('/', [ContentTranslationController::class, 'index'])->name('index');
            Route::post('/', [ContentTranslationController::class, 'store'])->name('store');
            Route::post('/auto', [ContentTranslationController::class, 'autoTranslate'])->name('auto');
        });
        // Audit logs (super admin)
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });

    // ──────────────────────────────────────────────────────────────────────────
    //  SUPER ADMIN + CENTER ADMIN
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super_admin,center_admin')->group(function () {

        // ── Espace Admin Centre piloté par domaine (portail + dashboard) ──
        Route::get('/center-admin/domaines', [CenterAdminDomainController::class, 'domainPicker'])
            ->name('center-admin.domaines');
        // Revenir à « tous les domaines » (avant le générique {domain}).
        Route::get('/center-admin/domaines/all', [CenterAdminDomainController::class, 'clearDomain'])
            ->name('center-admin.domain.clear');
        Route::get('/center-admin/domaines/{domain}', [CenterAdminDomainController::class, 'domainDashboard'])
            ->name('center-admin.domain.dashboard')
            ->where('domain', '[a-zA-Z0-9\-]+');

        // ── Gestion des stagiaires (Super Admin : tous centres autorisés,
        //    Admin Centre : son centre uniquement, périmètre imposé côté serveur) ──
        Route::get('/super-admin/stagiaires/dashboard', [StagiaireController::class, 'dashboard'])
            ->name('stagiaires.dashboard');
        Route::get('/super-admin/stagiaires/documents', [StagiaireController::class, 'documents'])
            ->name('stagiaires.documents');
        Route::get('/super-admin/stagiaires/{stagiaire}/cin/{side}', [StagiaireController::class, 'downloadCin'])
            ->name('stagiaires.download-cin');
        Route::get('/super-admin/stagiaires/{stagiaire}/cin/{side}/get', [StagiaireController::class, 'downloadCin'])
            ->name('stagiaires.cin-download');
        Route::get('/super-admin/stagiaires/{stagiaire}/profile-photo', [StagiaireController::class, 'showProfilePhoto'])
            ->name('stagiaires.profile-photo');

        Route::get('/super-admin/stagiaires/{stagiaire}/diplomas/{diploma}/download', [StagiaireController::class, 'downloadDiploma'])
            ->name('stagiaires.download-diploma');
        Route::get('/super-admin/stagiaires/{stagiaire}/diplomas/{diploma}/file', [StagiaireController::class, 'downloadDiploma'])
            ->name('stagiaires.diploma-download');

        Route::get('/super-admin/stagiaires/{stagiaire}/documents/{document}/download', [StagiaireController::class, 'downloadDocument'])
            ->name('stagiaires.download-document');
        Route::get('/super-admin/stagiaires/{stagiaire}/documents/{document}/file', [StagiaireController::class, 'downloadDocument'])
            ->name('stagiaires.document-download');

        Route::delete('/super-admin/stagiaires/{stagiaire}/diplomas/{diploma}', [StagiaireController::class, 'deleteDiploma'])
            ->name('stagiaires.delete-diploma');
        Route::delete('/super-admin/stagiaires/{stagiaire}/documents/{document}', [StagiaireController::class, 'deleteDocument'])
            ->name('stagiaires.delete-document');
        Route::resource('super-admin/stagiaires', StagiaireController::class)
            ->names('stagiaires');

        // ── Plan de classe (lecture seule) ──
        Route::get('/plan-de-classe', [ClassPlanController::class, 'index'])->name('class-plan.index');
        Route::get('/api/class-plan/teachers/{teacher}/classes', [ClassPlanController::class, 'getTeacherClasses'])->name('class-plan.teacher.classes');
        Route::get('/api/class-plan/classes/{class}/students', [ClassPlanController::class, 'getClassStudents'])->name('class-plan.class.students');

        // Utilisateurs
        Route::resource('users', UserController::class);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->name('users.toggle-status');

        // Étudiants
        Route::resource('students', StudentController::class);
        Route::patch('students/{student}/toggle-status', [StudentController::class, 'toggleStatus'])
            ->name('students.toggle-status');

        // Professeurs
        Route::resource('teachers', TeacherController::class);
        Route::patch('teachers/{teacher}/toggle-status', [TeacherController::class, 'toggleStatus'])
            ->name('teachers.toggle-status');

        // Formations
        Route::resource('formations', FormationController::class);
        Route::patch('formations/{formation}/toggle-status', [FormationController::class, 'toggleStatus'])
            ->name('formations.toggle-status');

        // ── Formations générales officielles (surface additive de la refonte) ──
        // Portail des 6 cartes + espace par formation officielle (≤2 classes,
        // 1 professeur responsable). N'affecte pas les routes/domaines historiques.
        Route::prefix('formations-generales')->name('formations-generales.')->group(function () {
            Route::get('/', [GeneralFormationController::class, 'index'])->name('index');

            // Configuration des six formations générales officielles.
            // Le picker liste les 6 emplacements autorisés ; l'édition met à
            // jour prix/durée/description/statut/professeur responsable sans
            // jamais permettre de 7ᵉ formation ni de sous-formation.
            // Ces segments littéraux DOIVENT précéder le joker /{formation}.
            Route::get('/configurer', [GeneralFormationController::class, 'picker'])
                ->name('picker');
            Route::post('/configurer', [GeneralFormationController::class, 'activate'])
                ->name('activate');
            Route::get('/{formation}/configurer', [GeneralFormationController::class, 'edit'])
                ->name('edit');
            Route::patch('/{formation}/configurer', [GeneralFormationController::class, 'update'])
                ->name('update');

            Route::get('/{formation}', [GeneralFormationController::class, 'show'])->name('show');
            Route::post('/{formation}/classes', [GeneralFormationController::class, 'storeClass'])->name('classes.store');
            Route::post('/{formation}/responsable', [GeneralFormationController::class, 'assignTeacher'])->name('teacher.assign');
            Route::delete('/{formation}/responsable', [GeneralFormationController::class, 'detachTeacher'])->name('teacher.detach');
        });

        // Classes
        Route::resource('classes', ClassController::class);
        Route::patch('classes/{class}/toggle-status', [ClassController::class, 'toggleStatus'])
            ->name('classes.toggle-status');

        // Affectation professeurs ↔ classes
        Route::post('classes/{class}/teachers', [ClassTeacherController::class, 'store'])
            ->name('classes.teachers.store');
        Route::delete('classes/{class}/teachers/{teacher}', [ClassTeacherController::class, 'destroy'])
            ->name('classes.teachers.destroy');

        // Salles
        Route::resource('rooms', RoomController::class);
        Route::patch('rooms/{room}/toggle-status', [RoomController::class, 'toggleStatus'])
            ->name('rooms.toggle-status');

        // Emplois du temps
        Route::resource('schedules', ScheduleController::class);
        Route::patch('schedules/{schedule}/toggle-status', [ScheduleController::class, 'toggleStatus'])
            ->name('schedules.toggle-status');

        // 4I — Inscriptions
        Route::resource('enrollments', EnrollmentController::class);
        Route::patch('enrollments/{enrollment}/status', [EnrollmentController::class, 'changeStatus'])
            ->name('enrollments.change-status');

        // 4L — Paiements
        Route::resource('payments', PaymentController::class);

        // 4L — Factures
        Route::resource('invoices', InvoiceController::class);
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

        // 4M — Documents étudiants
        Route::resource('documents', DocumentController::class, ['except' => ['edit', 'update']]);
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])
            ->name('documents.download');

        // 4N — Certificats
        Route::resource('certificates', CertificateController::class, ['except' => ['edit', 'update']]);
        Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])
            ->name('certificates.download');
        Route::get('certificates/{certificate}/print', [CertificateController::class, 'printPdf'])
            ->name('certificates.print');

        // 4O — Annonces
        Route::resource('announcements', AnnouncementController::class);

        // Audit (admin centre)
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index')->withoutMiddleware('role:super_admin');
    });

    // ──────────────────────────────────────────────────────────────────────────
    //  4J — PRÉSENCES (Admin + Teacher)
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super_admin,center_admin,teacher')->group(function () {
        Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::get('/attendances/create', [AttendanceController::class, 'create'])->name('attendances.create');
        Route::post('/attendances/session', [AttendanceController::class, 'storeSession'])->name('attendances.store-session');
        Route::get('/attendances/{attendance}', [AttendanceController::class, 'show'])->name('attendances.show');
        Route::get('/attendances/{attendance}/edit', [AttendanceController::class, 'edit'])->name('attendances.edit');
        Route::patch('/attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
        Route::delete('/attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');
        Route::get('/api/attendances/students-for-schedule', [AttendanceController::class, 'getStudentsForSchedule'])->name('attendances.students-for-schedule');
    });

    // ──────────────────────────────────────────────────────────────────────────
    //  4K — ÉVALUATIONS / NOTES (Admin + Teacher)
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super_admin,center_admin,teacher')->group(function () {
        Route::resource('evaluations', EvaluationController::class);
        Route::post('evaluations/{evaluation}/grades', [EvaluationController::class, 'saveGrades'])
            ->name('evaluations.grades.save');
    });

    // ──────────────────────────────────────────────────────────────────────────
    //  MESSAGERIE INTERNE (Super Admin + Center Admin + Teacher avec droit de réponse)
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super_admin,center_admin,teacher')->group(function () {
        Route::post('/messages/conversations/{conversation}', [MessageController::class, 'sendMessage'])->name('messages.send');
    });

    Route::middleware('role:super_admin,center_admin')->group(function () {
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages/start-direct/{target}', [MessageController::class, 'startDirect'])->name('messages.start-direct');
    });

    // Routes de consultation des messages (Super Admin, Center Admin, Teacher)
    Route::middleware('role:super_admin,center_admin,teacher')->group(function () {
        Route::get('/messages/conversations', [MessageController::class, 'getConversations'])->name('messages.conversations');
        Route::get('/messages/conversations/{conversation}', [MessageController::class, 'getMessages'])->name('messages.show');
        Route::get('/messages/unread-count', [MessageController::class, 'getUnreadCount'])->name('messages.unread-count');
        Route::get('/messages/attachments/{attachment}', [MessageController::class, 'downloadAttachment'])->name('messages.attachment');
    });

    // ──────────────────────────────────────────────────────────────────────────
    //  ESPACE PROFESSEUR — Classes, étudiants, emploi du temps et messages reçus
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware('role:teacher')->group(function () {
        Route::get('/professeur/mes-messages', [MessageController::class, 'teacherIndex'])
            ->name('teacher.messages');
        Route::get('/professeur/mes-classes', [TeacherPortalController::class, 'classes'])
            ->name('teacher.classes');
        Route::get('/professeur/mes-classes/{class}', [TeacherPortalController::class, 'showClass'])
            ->name('teacher.classes.show');
        Route::get('/professeur/mon-emploi-du-temps', [TeacherPortalController::class, 'schedule'])
            ->name('teacher.schedule');
        Route::get('/professeur/mes-salaires', [TeacherPortalController::class, 'salaries'])
            ->name('teacher.salaries');
    });

    // ──────────────────────────────────────────────────────────────────────────
    //  ACCÈS ÉTUDIANT — Lecture seule
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware('role:student')->group(function () {
        Route::get('/mes-formations', [StudentPortalController::class, 'formations'])
            ->name('student.formations');
        Route::get('/mon-emploi-du-temps', [StudentPortalController::class, 'schedule'])
            ->name('student.schedule');
        Route::get('/mes-presences', [StudentPortalController::class, 'attendances'])
            ->name('student.attendances');
        Route::get('/mes-notes', [StudentPortalController::class, 'evaluations'])
            ->name('student.evaluations');
        Route::get('/mes-documents', [StudentPortalController::class, 'documents'])
            ->name('student.documents');
        Route::get('/mes-documents/{document}/download', [DocumentController::class, 'download'])
            ->name('student.documents.download');
        Route::get('/mes-documents/{document}/apercu', [DocumentController::class, 'preview'])
            ->name('student.documents.preview');
        Route::get('/mes-certificats', [StudentPortalController::class, 'certificates'])
            ->name('student.certificates');
        Route::get('/mes-certificats/{certificate}/download', [CertificateController::class, 'download'])
            ->name('student.certificates.download');
        Route::get('/mes-certificats/{certificate}/print', [CertificateController::class, 'printPdf'])
            ->name('student.certificates.print');
        Route::get('/mes-paiements', [StudentPortalController::class, 'payments'])
            ->name('student.payments');
    });
});
