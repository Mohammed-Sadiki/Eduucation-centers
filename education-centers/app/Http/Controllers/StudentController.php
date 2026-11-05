<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Student;
use App\Services\FinanceService;
use App\Services\NotificationService;
use App\Services\PersonalFinanceService;
use App\Support\AcademicPerimeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    /**
     * Liste des dossiers étudiants avec recherche, filtres et pagination.
     * Super Admin -> tous | Admin Centre -> uniquement son centre.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Student::class);

        $authUser = $request->user();
        $query = Student::with(['user', 'center']);

        // Isolation multi-centres stricte
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Filtrage par domaine actif (Super Admin / Admin Centre en contexte domaine)
        $query = $this->applyDomainScope($query);

        // Filtre : formation spécifique
        if ($formationId = $request->input('formation_id')) {
            $query->whereHas('enrollments', function ($q) use ($formationId) {
                $q->where('formation_id', $formationId);
            });
        }

        // Filtre : recherche par numéro étudiant
        if ($studentNumber = $request->input('student_number')) {
            $query->where('student_number', 'like', "%{$studentNumber}%");
        }

        // Filtre : recherche par nom / prénom
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Filtre : email (via user)
        if ($email = $request->input('email')) {
            $query->whereHas('user', function ($q) use ($email) {
                $q->where('email', 'like', "%{$email}%");
            });
        }

        // Filtre : statut
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtre : centre (Super Admin seulement)
        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        $students = $query->latest('id')->paginate(15)->withQueryString();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('students.index', compact('students', 'centers'));
    }

    /**
     * Formulaire de création indépendante d'un élève (centralisé depuis la page
     * des Formations générales). Aucun compte de connexion n'est requis : le dossier
     * est créé directement. Nouveau parcours « Centre → Formation générale → Classe »,
     * sans étape Domaine : le catalogue des six formations officielles du centre est
     * proposé directement. Une préselection est possible via `?formation_id=`.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Student::class);

        $authUser = $request->user();

        // Liste des centres : sélectionnable uniquement par le Super Admin.
        $centers = ($authUser->isCenterAdmin() || $authUser->isSecretary()) ? collect() : Center::orderBy('name')->get();

        // Catalogue des formations : uniquement les formations générales
        // officielles actives, filtrées par le centre autorisé.
        // Admin Centre / Secrétaire → son centre ; Super Admin → tous les centres (le select
        // est filtré côté client dès qu'un centre est choisi).
        $centerIds = ($authUser->isCenterAdmin() || $authUser->isSecretary())
            ? [(int) $authUser->center_id]
            : null;

        $formationsData = Formation::selectionForCenters($centerIds);

        // Préselection depuis la carte d'une formation générale
        // (`formations-generales/{id}?...`) -> ?formation_id= transmis à l'URL.
        $preselectedFormationId = $request->filled('formation_id')
            ? (int) $request->input('formation_id')
            : null;

        // Sécurité : on ne préselectionne que si la formation est bien
        // dans le catalogue autorisé pour l'utilisateur courant.
        if ($preselectedFormationId !== null) {
            $allowed = array_column($formationsData, 'id');
            if (! in_array($preselectedFormationId, $allowed, true)) {
                $preselectedFormationId = null;
            }
        }

        return view('students.create', compact(
            'centers',
            'formationsData',
            'preselectedFormationId',
        ));
    }

    /**
     * Enregistrer un nouveau dossier étudiant (+ inscription à la formation
     * sélectionnée, pièces/fichiers et paiement intégré au module financier).
     *
     * Le dossier peut rester « dossier seul » (aucune formation / aucun paiement
     * transmis) : les ajouts sont optionnels, conformément au fonctionnement
     * existant. Dès qu'une formation et une option de paiement sont fournies, la
     * transaction crée l'inscription puis un versement « paid » réutilisant la
     * logique financière existante (visible dans Gestion financière → Paiements).
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $authUser = $request->user();

        // Détermination stricte du center_id côté serveur. Le compte utilisateur
        // n'entre plus en compte : Super Admin = centre choisi (obligatoire à la
        // validation), Admin Centre / Secrétaire = son propre centre, quelle que soit la requête.
        $centerId = ($authUser->isCenterAdmin() || $authUser->isSecretary())
            ? $authUser->center_id
            : $request->validated('center_id');
        $center = Center::findOrFail($centerId);

        // Génération automatique du student_number côté serveur
        $studentNumber = Student::generateStudentNumber($center);

        $data = $request->validated();

        // Clés hors-colonnes du students : gérées séparément (cascade, fichiers, paiement).
        foreach (['domain', 'formation_id', 'center_id', 'payment_option', 'avance_amount', 'final_price', 'profile_photo', 'cin_files', 'other_files'] as $key) {
            unset($data[$key]);
        }
        $data['center_id'] = $centerId;
        $data['student_number'] = $studentNumber;
        // user_id est facultatif : null = dossier créé sans compte de connexion.
        $data['user_id'] = $data['user_id'] ?? null;
        // Le statut n'est plus un champ visible : défaut « active ».
        $data['status'] = ($data['status'] ?? 'active') ?: 'active';

        $formationId = $request->validated('formation_id');
        $paymentOption = $request->validated('payment_option');
        $schoolYear = config('app.school_year', '2026/2027');

        // Tarifs financiers résolus côté serveur (le navigateur ne fait jamais foi) :
        // - tarif officiel relu depuis la base ;
        // - prix final accordé = valeur transmise et validable, sinon tarif officiel.
        $formation = $formationId ? Formation::find($formationId) : null;
        $officialPrice = $formation ? FinanceService::getFormationAnnualPrice($formation) : 0.0;
        $finalPriceInput = $request->validated('final_price');
        $finalPrice = $formation
            ? ($finalPriceInput !== null && $finalPriceInput !== ''
                ? min((float) $finalPriceInput, $officialPrice)
                : $officialPrice)
            : null;

        $student = DB::transaction(function () use ($data, $formationId, $center, $centerId, $request, $authUser, $paymentOption, $schoolYear, $officialPrice, $finalPrice): Student {
            $student = Student::create($data);

            // ── Photo de profil (colonne dédiée, disque privé) ──
            if ($request->hasFile('profile_photo')) {
                $student->update([
                    'profile_photo_path' => $request->file('profile_photo')
                        ->store("students/{$centerId}/{$student->id}/profile", 'private'),
                ]);
            }

            // ── Photos de la CIN → Documents « identity » ──
            foreach ((array) $request->file('cin_files', []) as $cin) {
                if ($cin instanceof UploadedFile) {
                    $this->recordStudentDocument($cin, $student, $centerId, $authUser->id, 'identity', __('CIN'));
                }
            }

            // ── Autres fichiers → Documents « other » ──
            foreach ((array) $request->file('other_files', []) as $file) {
                if ($file instanceof UploadedFile) {
                    $this->recordStudentDocument($file, $student, $centerId, $authUser->id, 'other', $file->getClientOriginalName());
                }
            }

            // ── Inscription à la formation (logique métier existante) ──
            $enrollment = null;
            if ($formationId) {
                $enrollment = Enrollment::create([
                    'center_id' => $centerId,
                    'student_id' => $student->id,
                    'formation_id' => $formationId,
                    'created_by' => $authUser->id,
                    'application_date' => now()->toDateString(),
                    'status' => Enrollment::STATUS_PENDING,
                    'registration_number' => Enrollment::generateRegistrationNumber($center),
                    // Prix final personnalisé propre à CETTE inscription (le
                    // catalogue n'est jamais modifié). Le tarif officiel est tracé
                    // comme référence de la réduction.
                    'official_price' => $officialPrice,
                    'final_price' => $finalPrice,
                ]);
            }

            // ── Paiement intégré (Payé = totalité, Avance = partiel) ──
            if ($paymentOption && $formationId) {
                $formation = Formation::findOrFail($formationId);
                // Référence financière : le PRIX FINAL accordé (réduction incluse),
                // jamais le tarif catalogue brut.
                $price = $finalPrice ?? FinanceService::getFormationAnnualPrice($formation);

                $amount = $paymentOption === 'paye'
                    ? $price
                    : max(0.0, (float) $request->validated('avance_amount'));

                if ($amount > 0) {
                    Payment::create([
                        'center_id' => $centerId,
                        'student_id' => $student->id,
                        'enrollment_id' => $enrollment?->id,
                        'formation_id' => $formation->id,
                        'school_year' => $schoolYear,
                        'domain' => $formation->domain,
                        'amount' => $amount,
                        'currency' => default_currency(),
                        'payment_date' => now()->toDateString(),
                        'method' => 'cash',
                        'reference' => Payment::generateReference($center),
                        'status' => 'paid',
                        'recorded_by' => $authUser->id,
                    ]);
                }
            }

            return $student;
        });

        // ── Notifications internes (événement réel, après commit) ──────────
        // Ne notifie jamais l'auteur de l'action ; notifie les Admin Centre du
        // centre concerné. Le Super Admin n'est pas alerté pour un acte mineur.
        $notifier = app(NotificationService::class);
        $formationName = $formation?->name ?? __('Formation inconnue');

        if ($formationId) {
            $notifier->notifyCenterAdmins(
                centerId: $centerId,
                type: 'enrollment',
                title: __('Nouvelle inscription'),
                message: __(':name inscrit(e) à :formation.', ['name' => $student->full_name, 'formation' => $formationName]),
                link: route('students.show', $student),
                exceptUserId: $authUser->id,
                relatedType: Student::class,
                relatedId: $student->id,
            );
        }

        // Dossier incomplet : CIN (document « identity ») absente → information
        // réellement obligatoire. On ne traite pas les champs facultatifs comme requis.
        $hasCin = $student->documents()->where('type', 'identity')->exists();
        if (! $hasCin) {
            $notifier->notifyCenterAdmins(
                centerId: $centerId,
                type: 'dossier_incomplete',
                title: __('Dossier étudiant incomplet'),
                message: __('Le dossier de :name est incomplet : CIN manquante.', ['name' => $student->full_name]),
                link: route('students.show', $student),
                exceptUserId: $authUser->id,
                relatedType: Student::class,
                relatedId: $student->id,
            );
        }

        // Paiement encaissé à l'inscription (paye / avance) → responsable du centre.
        if ($paymentOption && $formationId) {
            $paidAmount = $paymentOption === 'paye'
                ? ($finalPrice ?? $officialPrice)
                : max(0.0, (float) $request->validated('avance_amount'));
            if ($paidAmount > 0) {
                $notifier->notifyCenterAdmins(
                    centerId: $centerId,
                    type: 'payment',
                    title: __('Nouveau paiement encaissé'),
                    message: __('Paiement de :amount encaissé pour :name.', [
                        'amount' => format_price($paidAmount, 'MAD'),
                        'name' => $student->full_name,
                    ]),
                    link: route('students.show', $student),
                    exceptUserId: $authUser->id,
                    relatedType: Student::class,
                    relatedId: $student->id,
                    metadata: ['amount' => $paidAmount],
                );
            }
        }

        $message = __('Le dossier étudiant :student_number (:full_name) a été créé avec succès.', [
            'student_number' => $student->student_number,
            'full_name' => $student->full_name,
        ]);

        if ($paymentOption === 'paye') {
            $message .= ' '.__('Le paiement complet a été enregistré dans Gestion financière.');
        } elseif ($paymentOption === 'avance') {
            $message .= ' '.__('L\'avance a été enregistrée comme paiement partiel dans Gestion financière.');
        }

        return redirect()
            ->route('students.show', $student)
            ->with('success', $message);
    }

    /**
     * Enregistre un fichier du dossier étudiant dans la table `documents`
     * (disque privé, nom assaini), sans dupliquer la logique d'upload du module
     * Documents existant.
     */
    private function recordStudentDocument(
        UploadedFile $file,
        Student $student,
        int $centerId,
        ?int $uploadedBy,
        string $type,
        string $title,
    ): Document {
        $safeName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("documents/{$centerId}/{$student->id}", $safeName, 'private');

        return Document::create([
            'center_id' => $centerId,
            'student_id' => $student->id,
            'type' => $type,
            'title' => $title,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
            'status' => 'active',
        ]);
    }

    /**
     * Consulter un dossier étudiant (Détails de l'étudiant).
     */
    public function show(Request $request, Student $student): View
    {
        Gate::authorize('view', $student);

        $student->load([
            'user',
            'center',
            'documents',
            'enrollments.formation',
            'enrollments.classRoom.teachers.user',
            'payments',
        ]);

        $financeSituation = app(PersonalFinanceService::class)->studentSituation($student);

        // Paramètres de retour contextuel (ex: depuis le plan de classe)
        $returnTeacherId = $request->input('return_teacher_id');
        $returnClassId = $request->input('return_class_id');

        // Layout : pour un Admin Centre venant du Plan de classe (ou consultant depuis ce flux),
        // présentation indépendante en pleine largeur sans sidebar administrative.
        // Pour le Super Admin, conservation du layout habituel si aucun flux spécifique n'est demandé.
        $isStandalone = $request->user()->isCenterAdmin() || $request->user()->isSecretary() || $request->filled('standalone');

        return view('students.show', compact(
            'student',
            'financeSituation',
            'returnTeacherId',
            'returnClassId',
            'isStandalone'
        ));
    }

    /**
     * Sert la photo de profil d'un étudiant.
     *
     * La photo est une donnée personnelle enregistrée sur le disque PRIVÉ
     * (storage/app/private) : elle ne peut donc pas être atteinte par
     * asset('storage/…'), qui construit une URL du disque PUBLIC. Elle transite
     * par cette route, protégée par la même politique que la page
     * « Détails de l'étudiant », comme c'est déjà le cas pour les stagiaires.
     */
    public function showProfilePhoto(Student $student): BinaryFileResponse
    {
        Gate::authorize('view', $student);

        $disk = $student->profilePhotoDisk();

        if ($disk === null) {
            abort(404, __('Photo introuvable.'));
        }

        return response()->file(Storage::disk($disk)->path($student->profile_photo_path));
    }

    /**
     * Formulaire d'édition d'un dossier étudiant.
     *
     * Interface strictement identique à « Ajouter un élève » : mêmes composants
     * Blade partagés, mêmes sections dans le même ordre, mêmes champs. Seules les
     * valeurs préremplies changent (dossier enregistré, puis old() après une
     * erreur de validation).
     */
    public function edit(Student $student): View
    {
        Gate::authorize('update', $student);

        $student->load(['user', 'center', 'documents']);

        // Catalogue identique à la création : les formations générales officielles
        // actives, limitées au centre du dossier (le centre n'est pas modifiable).
        $formationsData = Formation::selectionForCenters([(int) $student->center_id]);

        // Inscription de référence : formation, classe, prix accordé et solde.
        $enrollment = $student->currentEnrollment();

        // Le catalogue est celui de la création. L'inscription de référence est
        // toujours présentée par sa formation générale : une ancienne
        // sous-formation n'est jamais proposée comme choix (elle n'existe plus
        // dans le fonctionnement courant), seule ses données restent comptées.
        $preselectedFormationId = $enrollment?->formation_id
            ? AcademicPerimeter::generalFormationId((int) $enrollment->formation_id)
            : null;

        if ($preselectedFormationId !== null && ! in_array($preselectedFormationId, array_column($formationsData, 'id'), true)) {
            // Formation générale hors catalogue (inactivée) : on la réajoute pour
            // que le champ reste fidèlement prérempli, sans jamais proposer une
            // sous-formation héritée.
            $general = Formation::find($preselectedFormationId);

            if ($general) {
                $formationsData[] = [
                    'id' => (int) $general->id,
                    'center_id' => (int) $general->center_id,
                    'name' => $general->translated('name') ?? $general->name,
                    'code' => $general->code,
                    'price' => FinanceService::getFormationAnnualPrice($general),
                    'classes' => collect(ClassRoom::query()
                        ->with('formation')
                        ->whereIn('formation_id', AcademicPerimeter::equivalentIds($general))
                        ->orderBy('name')
                        ->get(['id', 'formation_id', 'name', 'code']))
                        ->map(fn (ClassRoom $c): array => [
                            'id' => (int) $c->id,
                            'name' => $c->group_label,
                            'code' => $c->code,
                        ])
                        ->values()
                        ->all(),
                ];
            }
        }

        // Versements déjà encaissés, par formation générale : sert uniquement à
        // l'affichage du reste à payer. Les versements enregistrés sur une
        // ancienne sous-formation sont rattachés à sa formation générale, sinon
        // le formulaire afficherait un solde erroné pour les dossiers hérités.
        // Aucune écriture financière existante n'est modifiée.
        $paidByFormation = [];
        foreach (Payment::query()
            ->where('center_id', $student->center_id)
            ->where('student_id', $student->id)
            ->where('status', 'paid')
            ->selectRaw('formation_id, SUM(amount) as total')
            ->whereNotNull('formation_id')
            ->groupBy('formation_id')
            ->pluck('total', 'formation_id')
            ->all() as $formationId => $total
        ) {
            $generalId = AcademicPerimeter::generalFormationId((int) $formationId) ?? (int) $formationId;
            $paidByFormation[$generalId] = ($paidByFormation[$generalId] ?? 0.0) + (float) $total;
        }

        return view('students.edit', [
            'student' => $student,
            'formationsData' => $formationsData,
            'preselectedFormationId' => $preselectedFormationId,
            'enrollment' => $enrollment,
            'paidByFormation' => $paidByFormation,
            'paidAmount' => $enrollment ? $enrollment->paidAmount() : 0.0,
            'remainingAmount' => $enrollment ? $enrollment->getRemainingAmount() : 0.0,
            'paymentsCount' => $enrollment ? $enrollment->paidPayments()->count() : 0,
        ]);
    }

    /**
     * Mettre à jour un dossier étudiant.
     *
     * Le formulaire étant celui de la création, la mise à jour doit rester
     * conservatrice : l'identité du dossier (numéro, centre, compte), les
     * inscriptions et leur historique, les paiements, présences, certificats et
     * documents existants ne sont jamais recréés ni réinitialisés.
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $authUser = $request->user();
        $validated = $request->validated();

        // Clés hors-colonnes du tableur students : traitées ci-dessous.
        $data = collect($validated)
            ->except([
                'formation_id', 'payment_option', 'avance_amount', 'final_price',
                'profile_photo', 'cin_files', 'other_files',
            ])
            ->all();

        // Un statut absent ou vide ne doit jamais effacer celui déjà enregistré :
        // la bascule passe par la route students.toggle-status.
        if (($data['status'] ?? null) === null || $data['status'] === '') {
            unset($data['status']);
        }

        $formationId = $validated['formation_id'] ?? null;
        $paymentOption = $validated['payment_option'] ?? null;

        // Ces deux valeurs sont lues hors de la fermeture : `$validated` n'existe
        // pas dans sa portée, et un `?? null` y masquerait silencieusement la
        // saisie (le prix accordé et l'avance ne seraient jamais enregistrés).
        $finalPriceInput = $validated['final_price'] ?? null;
        $avanceAmount = $validated['avance_amount'] ?? null;

        // Inscription de référence AVANT la mise à jour : on la modifie en place,
        // on n'en crée jamais une seconde pour la même formation.
        $enrollment = $student->currentEnrollment();

        $recordedPayment = null;

        DB::transaction(function () use ($request, $student, $authUser, $data, $formationId, $paymentOption, $finalPriceInput, $avanceAmount, $enrollment, &$recordedPayment): void {
            // ── Identité du dossier : uniquement les colonnes transmises ──
            $student->update($data);

            // ── Photo : remplacée seulement si un nouveau fichier est fourni ──
            if ($request->hasFile('profile_photo')) {
                $oldDisk = $student->profilePhotoDisk();
                $oldPath = $student->profile_photo_path;

                $student->update([
                    'profile_photo_path' => $request->file('profile_photo')
                        ->store("students/{$student->center_id}/{$student->id}/profile", 'private'),
                ]);

                // L'ancien fichier n'est supprimé qu'après succès du nouvel envoi.
                if ($oldDisk && $oldPath && $oldPath !== $student->profile_photo_path) {
                    Storage::disk($oldDisk)->delete($oldPath);
                }
            }

            // ── Pièces : ajout des seuls fichiers réellement fournis ──
            // Rien d'existant n'est supprimé (convention du module Documents).
            foreach ((array) $request->file('cin_files', []) as $cin) {
                if ($cin instanceof UploadedFile) {
                    $this->recordStudentDocument($cin, $student, (int) $student->center_id, $authUser->id, 'identity', __('CIN'));
                }
            }

            foreach ((array) $request->file('other_files', []) as $file) {
                if ($file instanceof UploadedFile) {
                    $this->recordStudentDocument($file, $student, (int) $student->center_id, $authUser->id, 'other', $file->getClientOriginalName());
                }
            }

            // ── Formation / inscription ──
            if ($formationId) {
                $formation = Formation::findOrFail($formationId);
                $officialPrice = FinanceService::getFormationAnnualPrice($formation);

                // Le prix final enregistré est conservé tant que la formation ne
                // change pas et qu'aucun nouveau montant n'est transmis
                // ($finalPriceInput, résolue hors de la fermeture).
                // Comparaison par formation générale : un dossier inscrit sur une
                // ancienne sous-formation n'est PAS réaffecté dès qu'on ouvre la
                // fiche — la sous-formation et la formation générale décrivent la
                // même réalité, classe et prix sont donc conservés.
                $sameFormation = $enrollment
                    && AcademicPerimeter::sameGeneralFormation($enrollment->formation_id, $formationId);

                $finalPrice = $finalPriceInput !== null && $finalPriceInput !== ''
                    ? min((float) $finalPriceInput, $officialPrice)
                    : ($sameFormation && $enrollment->final_price !== null
                        ? (float) $enrollment->final_price
                        : $officialPrice);

                if ($enrollment) {
                    $enrollment->formation_id = $formationId;
                    $enrollment->final_price = $finalPrice;

                    if (! $sameFormation) {
                        // Réaffectation : la référence officielle et la classe
                        // suivent la nouvelle formation. Le numéro d'inscription,
                        // la date de demande et le statut restent intacts.
                        $enrollment->official_price = $officialPrice;
                        $enrollment->class_id = null;
                    }

                    $enrollment->save();
                } else {
                    // Dossier sans inscription : création à la demande, avec la
                    // même logique que la création d'un élève.
                    $enrollment = Enrollment::create([
                        'center_id' => $student->center_id,
                        'student_id' => $student->id,
                        'formation_id' => $formationId,
                        'created_by' => $authUser->id,
                        'application_date' => now()->toDateString(),
                        'status' => Enrollment::STATUS_PENDING,
                        'registration_number' => Enrollment::generateRegistrationNumber($student->center),
                        'official_price' => $officialPrice,
                        'final_price' => $finalPrice,
                    ]);
                }
            }

            // ── Paiement : uniquement si une option est expressément choisie ──
            // Un enregistrement simple du dossier ne recrée jamais de versement et
            // ne touche pas aux avances/soldes déjà comptabilisés.
            if ($paymentOption && $enrollment) {
                $amount = $paymentOption === 'paye'
                    ? $enrollment->getRemainingAmount()
                    : min((float) ($avanceAmount ?? 0), $enrollment->getRemainingAmount());

                if ($amount > 0.01) {
                    $recordedPayment = Payment::create([
                        'center_id' => $student->center_id,
                        'student_id' => $student->id,
                        'enrollment_id' => $enrollment->id,
                        'formation_id' => $enrollment->formation_id,
                        'school_year' => config('app.school_year', '2026/2027'),
                        'domain' => $enrollment->formation?->domain,
                        'amount' => $amount,
                        'currency' => default_currency(),
                        'payment_date' => now()->toDateString(),
                        'method' => 'cash',
                        'reference' => Payment::generateReference($student->center),
                        'status' => 'paid',
                        'recorded_by' => $authUser->id,
                    ]);
                }
            }
        });

        $message = __('Le dossier étudiant :student_number a été mis à jour avec succès.', ['student_number' => $student->student_number]);

        if ($recordedPayment) {
            $message .= ' '.__('Le nouveau versement a été enregistré dans Gestion financière.');
        }

        return redirect()
            ->route('students.show', $student)
            ->with('success', $message);
    }

    /**
     * Activer ou désactiver un dossier étudiant.
     */
    public function toggleStatus(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('toggleStatus', $student);

        $newStatus = $student->isActive() ? 'inactive' : 'active';
        $student->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? __('Le dossier étudiant :student_number a été activé.', ['student_number' => $student->student_number])
            : __('Le dossier étudiant :student_number a été désactivé.', ['student_number' => $student->student_number]);

        return back()->with('success', $message);
    }

    /**
     * Supprimer un dossier étudiant si nécessaire.
     */
    public function destroy(Student $student): RedirectResponse
    {
        Gate::authorize('delete', $student);

        $studentNumber = $student->student_number;
        $student->delete();

        return redirect()
            ->route('students.index')
            ->with('success', __('Le dossier étudiant :studentNumber a été supprimé.', ['studentNumber' => $studentNumber]));
    }
}
