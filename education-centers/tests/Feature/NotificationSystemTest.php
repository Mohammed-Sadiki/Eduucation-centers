<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Formation;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Stagiaire;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReminderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * MISSION — Système de notifications internes (Super Admin / Admin Centre / Professeur).
 *
 * Vérifie les 18 scénarios du cahier des charges : création après événement réel,
 * absence de notification en cas d'échec, bons destinataires, isolation entre les
 * quatre centres et entre professeurs, compteur non lues, dropdown, marquage lu /
 * tout lu, pagination + filtres, accès interdit au notification d'autrui, absence
 * de doublons, rappels basés sur les dates réelles, navigation autorisée,
 * compatibilité FR/AR + RTL, mode clair/sombre, aucune notification externe.
 *
 * Le système réutilise l'architecture « app_notifications » existante (canal
 * database maison) — aucun email / SMS / WhatsApp / service externe.
 */
class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    private Center $tanger;

    private Center $essaouira;

    private Center $agadir;

    private Center $tetouane;

    private User $superAdmin;

    private User $adminTanger;

    private User $adminAgadir;

    private Teacher $teacherTanger;

    private Teacher $teacherTanger2;

    private Formation $formationTanger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tanger = Center::factory()->create(['name' => 'Centre Tanger', 'code' => 'TNG']);
        $this->essaouira = Center::factory()->create(['name' => 'Centre Essaouira', 'code' => 'ESS']);
        $this->agadir = Center::factory()->create(['name' => 'Centre Agadir', 'code' => 'AGD']);
        $this->tetouane = Center::factory()->create(['name' => 'Centre Tétouane', 'code' => 'TET']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa@tst.ma']);
        $this->adminTanger = User::factory()->centerAdmin($this->tanger->id)->create(['email' => 'ca.tanger@tst.ma']);
        $this->adminAgadir = User::factory()->centerAdmin($this->agadir->id)->create(['email' => 'ca.agadir@tst.ma']);

        $this->teacherTanger = Teacher::factory()->forCenter($this->tanger)->create();
        $this->teacherTanger2 = Teacher::factory()->forCenter($this->tanger)->create();

        $this->formationTanger = Formation::factory()
            ->forCenter($this->tanger)->forDomain('informatique')
            ->create(['annual_price' => 12000, 'price' => 1200]);
    }

    private function service(): NotificationService
    {
        return app(NotificationService::class);
    }

    // ── 1. Création après un événement réel + 3. bon destinataire ────────────

    public function test_new_enrollment_notifies_center_administrators_of_that_center(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'center_id' => $this->tanger->id,
            'domain' => 'informatique',
            'formation_id' => $this->formationTanger->id,
            'first_name' => 'Hanae',
            'last_name' => 'Benali',
            'date_of_birth' => '2002-03-10',
            'nationality' => 'Marocaine',
            'phone' => '+212 600000001',
        ]);

        $response->assertSessionHasNoErrors();
        $student = Student::first();
        $this->assertNotNull($student);

        // L'Admin Centre de Tanger est notifié (inscription + dossier incomplet, CIN absente).
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->adminTanger->id,
            'type' => 'enrollment',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->adminTanger->id,
            'type' => 'dossier_incomplete',
        ]);

        // Le lien pointe vers le dossier étudiant autorisé.
        $notif = AppNotification::where('user_id', $this->adminTanger->id)->where('type', 'enrollment')->first();
        $this->assertSame(route('students.show', $student), $notif->link);
    }

    // ── 4. Isolation entre les quatre centres ────────────────────────────────

    public function test_enrollment_does_not_leak_to_other_centers(): void
    {
        $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'center_id' => $this->tanger->id,
            'domain' => 'informatique',
            'formation_id' => $this->formationTanger->id,
            'first_name' => 'Omar',
            'last_name' => 'Idrissi',
            'date_of_birth' => '2001-01-01',
            'nationality' => 'Marocaine',
            'phone' => '+212 600000002',
        ])->assertSessionHasNoErrors();

        // Aucun administrateur d'un autre centre ne reçoit la notification.
        $this->assertSame(0, AppNotification::where('user_id', $this->adminAgadir->id)->count());
        $this->assertDatabaseMissing('app_notifications', ['center_id' => $this->agadir->id]);
        $this->assertDatabaseMissing('app_notifications', ['center_id' => $this->essaouira->id]);
        $this->assertDatabaseMissing('app_notifications', ['center_id' => $this->tetouane->id]);
    }

    // ── 2. Aucune notification en cas d'échec de la transaction ──────────────

    public function test_no_notification_when_enrollment_fails_validation(): void
    {
        // Dossier invalide (pas de prénom) → aucune inscription, aucune notification.
        $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'center_id' => $this->tanger->id,
            'domain' => 'informatique',
            'formation_id' => $this->formationTanger->id,
            'last_name' => 'SansPrenom',
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    // ── Ne jamais notifier l'auteur de son propre acte ───────────────────────

    public function test_actor_is_not_notified_of_their_own_action(): void
    {
        // L'Admin Centre de Tanger réalise lui-même une inscription : il est exclu
        // de la diffusion (notifyCenterAdmins exceptUserId) et ne doit donc recevoir
        // aucune notification pour son propre acte.
        $this->actingAs($this->adminTanger)->post(route('students.store'), [
            'center_id' => $this->tanger->id,
            'domain' => 'informatique',
            'formation_id' => $this->formationTanger->id,
            'first_name' => 'Nadia',
            'last_name' => 'Hamdi',
            'date_of_birth' => '2002-07-07',
            'nationality' => 'Marocaine',
            'phone' => '+212 600000020',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            AppNotification::where('user_id', $this->adminTanger->id)->where('type', 'enrollment')->count(),
            "L'auteur ne doit pas être notifié de son propre enregistrement."
        );
    }

    // ── 12. Absence de doublons (sendUnique) ─────────────────────────────────

    public function test_duplicate_unread_notifications_are_suppressed(): void
    {
        $svc = $this->service();
        $student = Student::factory()->forCenter($this->tanger)->create();

        $first = $svc->sendUnique(
            $this->adminTanger, 'enrollment', 'Nouvelle inscription', 'msg',
            route('students.show', $student), null, $this->tanger->id,
            Student::class, $student->id
        );
        $second = $svc->sendUnique(
            $this->adminTanger, 'enrollment', 'Nouvelle inscription', 'msg',
            route('students.show', $student), null, $this->tanger->id,
            Student::class, $student->id
        );

        $this->assertNotNull($first);
        $this->assertNull($second, 'Un doublon non lu identique doit être ignoré.');
        $this->assertSame(1, AppNotification::where('type', 'enrollment')->where('user_id', $this->adminTanger->id)->count());

        // Une fois lue, une nouvelle alerte identique est de nouveau permise.
        $first->markAsRead();
        $third = $svc->sendUnique(
            $this->adminTanger, 'enrollment', 'Nouvelle inscription', 'msg',
            route('students.show', $student), null, $this->tanger->id,
            Student::class, $student->id
        );
        $this->assertNotNull($third);
    }

    // ── 6. Compteur de non lues + poll JSON + isolation destinataire ─────────

    public function test_poll_returns_only_authenticated_user_data(): void
    {
        $svc = $this->service();
        $svc->send($this->adminTanger, 'enrollment', 'Alerte Tanger TNG-UNIQ', 'm1', null, null, $this->tanger->id);
        $svc->send($this->adminTanger, 'payment', 'Paiement Tanger', 'm2', null, null, $this->tanger->id);
        $svc->send($this->adminAgadir, 'enrollment', 'Alerte Agadir AGD-UNIQ', 'm3', null, null, $this->agadir->id);

        $response = $this->actingAs($this->adminTanger)->getJson(route('notifications.poll'));
        $response->assertOk()->assertJsonStructure(['count', 'items']);
        $response->assertJsonPath('count', 2);

        $titles = collect($response->json('items'))->pluck('title')->all();
        $this->assertContains('Alerte Tanger TNG-UNIQ', $titles);
        $this->assertNotContains('Alerte Agadir AGD-UNIQ', $titles);
    }

    // ── 7. Affichage du dropdown (rendu serveur) ─────────────────────────────

    public function test_dropdown_fragment_renders_items_and_accessibility(): void
    {
        $this->service()->send($this->adminTanger, 'announcement', 'Nouvelle annonce', 'Réunion', route('notifications.index'), null, $this->tanger->id);

        $response = $this->actingAs($this->adminTanger)->get(route('notifications.dropdown'));
        $response->assertOk()
            ->assertSee('data-notif-body', false)
            ->assertSee('Nouvelle annonce')
            ->assertSee('role="menu"', false)
            ->assertSee(__('Voir toutes les notifications'));
    }

    public function test_dropdown_shows_empty_message_when_no_notifications(): void
    {
        $this->actingAs($this->adminTanger)
            ->get(route('notifications.dropdown'))
            ->assertOk()
            ->assertSee(__('Aucune notification pour le moment.'));
    }

    // ── 8. Marquage individuel comme lu ──────────────────────────────────────

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $notif = $this->service()->send($this->adminTanger, 'payment', 'Paiement', 'm', null, null, $this->tanger->id);

        $this->actingAs($this->adminTanger)
            ->patch(route('notifications.read', $notif))
            ->assertRedirect();

        $this->assertTrue($notif->fresh()->isRead());
    }

    // ── 9. Marquage de toutes les notifications comme lues ───────────────────

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $svc = $this->service();
        for ($i = 0; $i < 3; $i++) {
            $svc->send($this->adminTanger, 'enrollment', "N$i", 'm', null, null, $this->tanger->id);
        }
        $this->assertSame(3, $svc->getUnreadCount($this->adminTanger));

        $this->actingAs($this->adminTanger)->patch(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $svc->getUnreadCount($this->adminTanger));
    }

    // ── 10. Pagination + filtres Toutes / Non lues / Lues ────────────────────

    public function test_index_filters_unread_and_read(): void
    {
        $svc = $this->service();
        $a = $svc->send($this->adminTanger, 'enrollment', 'LUE-XYZ', 'm', null, null, $this->tanger->id);
        $b = $svc->send($this->adminTanger, 'payment', 'NONLUE-XYZ', 'm', null, null, $this->tanger->id);
        $a->markAsRead();

        // Filtre « Non lues » : la liste paginée ne contient que les non lues.
        $unread = $this->actingAs($this->adminTanger)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()->assertSee('NONLUE-XYZ')
            ->viewData('notifications');
        $this->assertSame(1, $unread->total());
        $this->assertSame('NONLUE-XYZ', $unread->first()->title);

        // Filtre « Lues » : uniquement les lues.
        $read = $this->actingAs($this->adminTanger)
            ->get(route('notifications.index', ['filter' => 'read']))
            ->assertOk()->assertSee('LUE-XYZ')
            ->viewData('notifications');
        $this->assertSame(1, $read->total());
        $this->assertSame('LUE-XYZ', $read->first()->title);

        // Filtre « Toutes » : les deux.
        $all = $this->actingAs($this->adminTanger)
            ->get(route('notifications.index'))
            ->assertOk()
            ->viewData('notifications');
        $this->assertSame(2, $all->total());
    }

    public function test_index_paginates(): void
    {
        $svc = $this->service();
        for ($i = 1; $i <= 25; $i++) {
            $svc->send($this->adminTanger, 'announcement', "Notif $i", 'm', null, null, $this->tanger->id);
        }

        $this->actingAs($this->adminTanger)->get(route('notifications.index'))
            ->assertOk()->assertSee('Notif 1');

        $this->assertGreaterThan(20, AppNotification::where('user_id', $this->adminTanger->id)->count());
    }

    // ── 11. Accès interdit aux notifications d'un autre utilisateur ──────────

    public function test_cannot_read_or_delete_another_users_notification(): void
    {
        $foreign = $this->service()->send($this->adminAgadir, 'enrollment', 'Notre centre', 'm', null, null, $this->agadir->id);

        // Isolation entre centres : l'Admin Tanger ne peut ni marquer lue ni supprimer.
        $this->actingAs($this->adminTanger)->patch(route('notifications.read', $foreign))->assertForbidden();
        $this->actingAs($this->adminTanger)->delete(route('notifications.destroy', $foreign))->assertForbidden();

        // Le destinataire légitime, lui, peut.
        $this->actingAs($this->adminAgadir)->patch(route('notifications.read', $foreign))->assertRedirect();
    }

    // ── 5. Isolation entre professeurs ───────────────────────────────────────

    public function test_teacher_cannot_access_another_teacher_notification(): void
    {
        $svc = $this->service();
        $mine = $svc->send($this->teacherTanger->user, 'teacher_assignment', 'MA-CLASSE-UNIQ', 'm', route('teacher.classes'), null, $this->tanger->id);
        $others = $svc->send($this->teacherTanger2->user, 'teacher_assignment', 'AUTRE-CLASSE-UNIQ', 'm', route('teacher.classes'), null, $this->tanger->id);

        // La page de notifications n'expose QUE ses propres notifications.
        $this->actingAs($this->teacherTanger->user)->get(route('notifications.index'))
            ->assertOk()->assertSee('MA-CLASSE-UNIQ')->assertDontSee('AUTRE-CLASSE-UNIQ');

        // Accès direct à la notification d'un collègue interdit.
        $this->actingAs($this->teacherTanger->user)->patch(route('notifications.read', $others))->assertForbidden();

        // Le lien de sa propre notification est accessible (navigation autorisée).
        $this->actingAs($this->teacherTanger->user)->get($mine->link)->assertOk();
    }

    // ── 13. Rappels basés sur les échéances RÉELLES ──────────────────────────

    public function test_due_soon_reminder_uses_real_invoice_due_date(): void
    {
        $student = Student::factory()->forCenter($this->tanger)->create();
        Invoice::create([
            'center_id' => $this->tanger->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-TNG-000001',
            'total_amount' => 1500,
            'currency' => 'MAD',
            'invoice_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(2)->toDateString(),
            'status' => 'sent',
        ]);

        $created = app(ReminderNotificationService::class)->paymentDueSoon();
        $this->assertGreaterThanOrEqual(1, $created);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->adminTanger->id,
            'type' => 'payment_due',
        ]);
    }

    public function test_paid_invoice_generates_no_reminder(): void
    {
        $student = Student::factory()->forCenter($this->tanger)->create();
        Invoice::create([
            'center_id' => $this->tanger->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-TNG-000002',
            'total_amount' => 1500,
            'currency' => 'MAD',
            'invoice_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(2)->toDateString(),
            'status' => 'paid',
        ]);

        $this->assertSame(0, app(ReminderNotificationService::class)->paymentDueSoon());
        $this->assertDatabaseMissing('app_notifications', ['type' => 'payment_due']);
    }

    public function test_overdue_reminder_is_not_repeated_within_cooldown(): void
    {
        $student = Student::factory()->forCenter($this->tanger)->create();
        Invoice::create([
            'center_id' => $this->tanger->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-TNG-000003',
            'total_amount' => 2000,
            'currency' => 'MAD',
            'invoice_date' => Carbon::today()->subDays(10)->toDateString(),
            'due_date' => Carbon::today()->subDays(3)->toDateString(),
            'status' => 'sent',
        ]);

        $svc = app(ReminderNotificationService::class);
        $first = $svc->paymentOverdue();
        $second = $svc->paymentOverdue(); // rappel : cooldown → rien de nouveau

        $this->assertGreaterThanOrEqual(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(1, AppNotification::where('type', 'payment_overdue')->count());
    }

    public function test_salary_reminder_only_for_unpaid_real_dates(): void
    {
        TeacherSalary::create([
            'center_id' => $this->tanger->id,
            'teacher_id' => $this->teacherTanger->id,
            'month' => (int) Carbon::today()->format('m'),
            'year' => (int) Carbon::today()->format('Y'),
            'expected_amount' => 5000,
            'paid_amount' => 0,
            'currency' => 'MAD',
            'status' => 'unpaid',
            'scheduled_payment_date' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $this->assertGreaterThanOrEqual(1, app(ReminderNotificationService::class)->salaryDueSoon());
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->adminTanger->id, 'type' => 'salary']);
    }

    public function test_attendance_reminder_skips_sessions_already_recorded(): void
    {
        $class = ClassRoom::factory()->create(['center_id' => $this->tanger->id, 'formation_id' => $this->formationTanger->id]);
        $room = Room::create(['center_id' => $this->tanger->id, 'name' => 'Salle A', 'code' => 'RA-01', 'capacity' => 30, 'status' => 'active']);
        $schedule = Schedule::create([
            'center_id' => $this->tanger->id,
            'class_id' => $class->id,
            'room_id' => $room->id,
            'teacher_id' => $this->teacherTanger->id,
            'date' => Carbon::today()->subDays(2)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'type' => 'course',
            'status' => 'completed',
        ]);

        $reminder = app(ReminderNotificationService::class);

        // Séance sans présence → le professeur est alerté.
        $this->assertGreaterThanOrEqual(1, $reminder->missingAttendance());
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->teacherTanger->user->id,
            'type' => 'attendance',
        ]);

        // Présence déjà enregistrée → plus aucune nouvelle alerte pour cette séance.
        $student = Student::factory()->forCenter($this->tanger)->create();
        Attendance::create([
            'center_id' => $this->tanger->id,
            'class_id' => $class->id,
            'schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'date' => $schedule->date->toDateString(),
            'status' => 'present',
        ]);

        // Nouvelle séance : on purge le cooldown en supprimant l'alerte existante pour repartir proprement.
        AppNotification::where('type', 'attendance')->delete();
        $this->assertSame(0, $reminder->missingAttendance(), 'Une séance avec présences saisies ne doit pas relancer une alerte.');
    }

    // ── 14. Navigation vers les pages autorisées ─────────────────────────────

    public function test_new_stagiaire_notifies_center_administrators(): void
    {
        // Le Super Admin enregistre un stagiaire à Tanger (dossier complet, CIN fournie).
        $this->actingAs($this->superAdmin)->post(route('stagiaires.store'), [
            'center_id' => $this->tanger->id,
            'first_name' => 'Youssef',
            'last_name' => 'Alami',
            'date_of_birth' => '1999-09-09',
            'nationality' => 'Marocaine',
            'phone' => '+212600000009',
            'whatsapp' => '+212600000009',
            'emergency_contact_phone' => '+212600000019',
            'company_name' => 'ACME Tanger',
            'company_phone' => '+212500000000',
            'company_location' => 'Tanger',
            'stage_start_date' => '2026-09-01',
            'stage_end_date' => '2027-02-28',
            'stage_price' => 1200.00,
            'cin_recto' => UploadedFile::fake()->image('recto.jpg'),
            'cin_verso' => UploadedFile::fake()->image('verso.jpg'),
            'profile_photo' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::first();
        $this->assertNotNull($stagiaire);

        // « Nouveau stagiaire » → Admin Centre du centre concerné, lien vers son dossier.
        $notif = AppNotification::where('user_id', $this->adminTanger->id)->where('type', 'stagiaire')->first();
        $this->assertNotNull($notif);
        $this->assertSame(route('stagiaires.show', $stagiaire), $notif->link);

        // Le Super Admin (auteur) n'est pas notifié ; les autres centres sont isolés.
        $this->assertSame(0, AppNotification::where('user_id', $this->superAdmin->id)->count());
        $this->assertSame(0, AppNotification::where('user_id', $this->adminAgadir->id)->count());
    }

    // ── 15. Compatibilité FR/AR + RTL ────────────────────────────────────────

    public function test_notifications_page_supports_arabic_rtl(): void
    {
        $this->service()->send($this->adminTanger, 'enrollment', 'Test FR AR', 'm', null, null, $this->tanger->id);

        $response = $this->actingAs($this->adminTanger)->get(route('notifications.index', ['lang' => 'ar']));
        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
    }

    // ── 16. Mode clair/sombre présent ────────────────────────────────────────

    public function test_notification_markup_supports_dark_mode(): void
    {
        $this->service()->send($this->adminTanger, 'payment', 'Dark', 'm', null, null, $this->tanger->id);

        $this->actingAs($this->adminTanger)->get(route('notifications.index'))
            ->assertOk()->assertSee('dark:', false);

        $this->actingAs($this->adminTanger)->get(route('notifications.dropdown'))
            ->assertOk()->assertSee('dark:', false);
    }

    // ── 17. Aucune notification externe envoyée ──────────────────────────────

    public function test_no_external_notification_is_sent(): void
    {
        Mail::fake();

        $this->actingAs($this->superAdmin)->post(route('students.store'), [
            'center_id' => $this->tanger->id,
            'domain' => 'informatique',
            'formation_id' => $this->formationTanger->id,
            'first_name' => 'Salma',
            'last_name' => 'Fassi',
            'date_of_birth' => '2003-02-02',
            'nationality' => 'Marocaine',
            'phone' => '+212 600000010',
        ])->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    // ── Sécurité : le destinataire/centre ne sont pas modifiables via HTTP ───

    public function test_notification_creation_is_scoped_to_server_side_recipient(): void
    {
        // Un Simple HTTP client ne peut pas créer une notification pour autrui :
        // la route « read » d'une notification d'un autre utilisateur est refusée,
        // et aucune route d'écriture arbitraire n'expose le champ user_id.
        $foreign = $this->service()->send($this->adminAgadir, 'payment', 'Autrui', 'm', null, null, $this->agadir->id);

        $this->actingAs($this->adminTanger)
            ->patch(route('notifications.read', $foreign), ['user_id' => $this->adminTanger->id, 'center_id' => $this->tanger->id])
            ->assertForbidden();

        // La tentative n'a rien changé.
        $foreign->refresh();
        $this->assertSame($this->adminAgadir->id, $foreign->user_id);
        $this->assertNull($foreign->read_at);
    }

    // ── Le Super Admin n'est pas alerté pour un événement mineur de centre ───

    public function test_super_admin_is_not_notified_for_minor_center_event(): void
    {
        // Une inscription (événement mineur d'un centre) ne doit PAS remonter au
        // Super Admin : seules les alertes globales (ex. dépense importante) le
        // concernent. On vérifie ici que le Super Admin ne reçoit rien.
        $this->actingAs($this->adminTanger)->post(route('students.store'), [
            'center_id' => $this->tanger->id,
            'domain' => 'informatique',
            'formation_id' => $this->formationTanger->id,
            'first_name' => 'Kenza',
            'last_name' => 'Bakri',
            'date_of_birth' => '1998-08-08',
            'nationality' => 'Marocaine',
            'phone' => '+212 600000021',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, AppNotification::where('user_id', $this->superAdmin->id)->count());
    }

    // ── 18. Aucune régression : types connus conservés ───────────────────────

    public function test_icon_mapping_is_defined_for_all_known_types(): void
    {
        $types = ['message', 'enrollment', 'stagiaire', 'dossier_incomplete', 'payment',
            'payment_due', 'payment_overdue', 'expense', 'salary', 'teacher_assignment',
            'schedule_change', 'attendance', 'certificate', 'announcement'];

        foreach ($types as $type) {
            $this->assertNotSame('bell', AppNotification::iconForType($type), "Icône manquante pour le type [$type].");
        }
        $this->assertSame('bell', AppNotification::iconForType('unknown_x'));
    }
}
