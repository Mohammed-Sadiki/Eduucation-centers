<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InternalCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private Center $centerAgadir;
    private Center $centerEssaouira;

    private User $superAdmin;

    private User $adminAgadir;
    private User $adminEssaouira;

    private User $secretaryAgadir;
    private User $secretaryEssaouira;

    private User $teacherUserAgadir;
    private Teacher $teacherAgadir;

    private User $teacherUserEssaouira;
    private Teacher $teacherEssaouira;

    private User $studentAgadir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Centres
        $this->centerAgadir = Center::create(['name' => 'Centre Agadir', 'code' => 'AGD', 'status' => 'active']);
        $this->centerEssaouira = Center::create(['name' => 'Centre Essaouira', 'code' => 'ESS', 'status' => 'active']);

        // Super Admin (aucun centre rattaché)
        $this->superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Super Administrateur',
            'status' => User::STATUS_ACTIVE,
        ]);

        // Admins Centre
        $this->adminAgadir = User::factory()->centerAdmin($this->centerAgadir->id)->create([
            'name' => 'Admin Agadir',
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->adminEssaouira = User::factory()->centerAdmin($this->centerEssaouira->id)->create([
            'name' => 'Admin Essaouira',
            'status' => User::STATUS_ACTIVE,
        ]);

        // Secrétaires
        $this->secretaryAgadir = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerAgadir->id,
            'name' => 'Secrétaire Agadir',
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->secretaryEssaouira = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerEssaouira->id,
            'name' => 'Secrétaire Essaouira',
            'status' => User::STATUS_ACTIVE,
        ]);

        // Professeurs
        $this->teacherUserAgadir = User::factory()->teacher($this->centerAgadir->id)->create([
            'name' => 'Prof Agadir',
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->teacherAgadir = Teacher::create([
            'user_id' => $this->teacherUserAgadir->id,
            'center_id' => $this->centerAgadir->id,
            'teacher_number' => 'ENS-AGD-01',
            'first_name' => 'Tariq',
            'last_name' => 'Agadir',
            'status' => 'active',
        ]);

        $this->teacherUserEssaouira = User::factory()->teacher($this->centerEssaouira->id)->create([
            'name' => 'Prof Essaouira',
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->teacherEssaouira = Teacher::create([
            'user_id' => $this->teacherUserEssaouira->id,
            'center_id' => $this->centerEssaouira->id,
            'teacher_number' => 'ENS-ESS-01',
            'first_name' => 'Fatima',
            'last_name' => 'Essaouira',
            'status' => 'active',
        ]);

        // Étudiant
        $this->studentAgadir = User::factory()->student($this->centerAgadir->id)->create([
            'name' => 'Étudiant Agadir',
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    // =========================================================================
    // 1. Matrice Super Admin (Accès global)
    // =========================================================================

    public function test_super_admin_can_communicate_with_admins_of_all_centers(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->adminAgadir))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->adminEssaouira))
            ->assertOk();
    }

    public function test_super_admin_can_communicate_with_secretaries_of_all_centers(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->secretaryAgadir))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->secretaryEssaouira))
            ->assertOk();
    }

    public function test_super_admin_can_communicate_with_teachers_of_all_centers(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->teacherUserAgadir))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->teacherUserEssaouira))
            ->assertOk();
    }

    public function test_super_admin_cannot_communicate_with_students(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->studentAgadir))
            ->assertForbidden();
    }

    // =========================================================================
    // 2. Matrice Admin Centre
    // =========================================================================

    public function test_center_admin_can_communicate_with_super_admin(): void
    {
        $this->actingAs($this->adminAgadir)
            ->postJson(route('messages.start-direct', $this->superAdmin))
            ->assertOk();
    }

    public function test_center_admin_can_communicate_with_secretaries_of_own_center(): void
    {
        $this->actingAs($this->adminAgadir)
            ->postJson(route('messages.start-direct', $this->secretaryAgadir))
            ->assertOk();
    }

    public function test_center_admin_can_communicate_with_teachers_of_own_center(): void
    {
        $this->actingAs($this->adminAgadir)
            ->postJson(route('messages.start-direct', $this->teacherUserAgadir))
            ->assertOk();
    }

    public function test_center_admin_cannot_communicate_with_other_center_admin(): void
    {
        $this->actingAs($this->adminAgadir)
            ->postJson(route('messages.start-direct', $this->adminEssaouira))
            ->assertForbidden();
    }

    public function test_center_admin_cannot_communicate_with_secretaries_of_other_center(): void
    {
        $this->actingAs($this->adminAgadir)
            ->postJson(route('messages.start-direct', $this->secretaryEssaouira))
            ->assertForbidden();
    }

    public function test_center_admin_cannot_communicate_with_teachers_of_other_center(): void
    {
        $this->actingAs($this->adminAgadir)
            ->postJson(route('messages.start-direct', $this->teacherUserEssaouira))
            ->assertForbidden();
    }

    // =========================================================================
    // 3. Matrice Secrétaire
    // =========================================================================

    public function test_secretary_can_communicate_with_super_admin(): void
    {
        $this->actingAs($this->secretaryAgadir)
            ->postJson(route('messages.start-direct', $this->superAdmin))
            ->assertOk();
    }

    public function test_secretary_can_communicate_with_admin_of_own_center(): void
    {
        $this->actingAs($this->secretaryAgadir)
            ->postJson(route('messages.start-direct', $this->adminAgadir))
            ->assertOk();
    }

    public function test_secretary_can_communicate_with_teachers_of_own_center(): void
    {
        $this->actingAs($this->secretaryAgadir)
            ->postJson(route('messages.start-direct', $this->teacherUserAgadir))
            ->assertOk();
    }

    public function test_secretary_cannot_communicate_with_other_center_admin(): void
    {
        $this->actingAs($this->secretaryAgadir)
            ->postJson(route('messages.start-direct', $this->adminEssaouira))
            ->assertForbidden();
    }

    public function test_secretary_cannot_communicate_with_secretaries(): void
    {
        // Même centre
        $otherSecAgadir = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->centerAgadir->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->secretaryAgadir)
            ->postJson(route('messages.start-direct', $otherSecAgadir))
            ->assertForbidden();

        // Autre centre
        $this->actingAs($this->secretaryAgadir)
            ->postJson(route('messages.start-direct', $this->secretaryEssaouira))
            ->assertForbidden();
    }

    public function test_secretary_cannot_communicate_with_teachers_of_other_center(): void
    {
        $this->actingAs($this->secretaryAgadir)
            ->postJson(route('messages.start-direct', $this->teacherUserEssaouira))
            ->assertForbidden();
    }

    // =========================================================================
    // 4. Matrice Professeur (avec initiation autorisée)
    // =========================================================================

    public function test_teacher_can_initiate_communication_with_super_admin(): void
    {
        $this->actingAs($this->teacherUserAgadir)
            ->postJson(route('messages.start-direct', $this->superAdmin))
            ->assertOk();
    }

    public function test_teacher_can_initiate_communication_with_admin_of_own_center(): void
    {
        $this->actingAs($this->teacherUserAgadir)
            ->postJson(route('messages.start-direct', $this->adminAgadir))
            ->assertOk();
    }

    public function test_teacher_can_initiate_communication_with_secretary_of_own_center(): void
    {
        $this->actingAs($this->teacherUserAgadir)
            ->postJson(route('messages.start-direct', $this->secretaryAgadir))
            ->assertOk();
    }

    public function test_teacher_cannot_communicate_with_teachers_same_or_other_center(): void
    {
        // Autre professeur même centre
        $teacherUser2 = User::factory()->teacher($this->centerAgadir->id)->create(['status' => 'active']);
        Teacher::create([
            'user_id' => $teacherUser2->id,
            'center_id' => $this->centerAgadir->id,
            'teacher_number' => 'ENS-AGD-02',
            'first_name' => 'Ali',
            'last_name' => 'Prof2',
            'status' => 'active',
        ]);

        $this->actingAs($this->teacherUserAgadir)
            ->postJson(route('messages.start-direct', $teacherUser2))
            ->assertForbidden();

        // Professeur d'un autre centre
        $this->actingAs($this->teacherUserAgadir)
            ->postJson(route('messages.start-direct', $this->teacherUserEssaouira))
            ->assertForbidden();
    }

    public function test_teacher_cannot_communicate_with_admin_or_secretary_of_other_center(): void
    {
        $this->actingAs($this->teacherUserAgadir)
            ->postJson(route('messages.start-direct', $this->adminEssaouira))
            ->assertForbidden();

        $this->actingAs($this->teacherUserAgadir)
            ->postJson(route('messages.start-direct', $this->secretaryEssaouira))
            ->assertForbidden();
    }

    // =========================================================================
    // 5. Étudiants et rôles exclus
    // =========================================================================

    public function test_student_cannot_initiate_or_access_messaging(): void
    {
        $this->actingAs($this->studentAgadir)
            ->postJson(route('messages.start-direct', $this->teacherUserAgadir))
            ->assertForbidden();

        $this->actingAs($this->studentAgadir)
            ->get(route('messages.index'))
            ->assertForbidden();

        $this->actingAs($this->studentAgadir)
            ->get(route('teacher.messages'))
            ->assertForbidden();
    }

    // =========================================================================
    // 6. Sécurité ID Manipulation & Isolation des conversations
    // =========================================================================

    public function test_user_cannot_access_conversation_they_are_not_participant_in(): void
    {
        $conv = Conversation::findOrCreateDirect($this->superAdmin, $this->adminAgadir);

        // L'admin d'Essaouira tente d'accéder à la conversation privée d'Agadir
        $this->actingAs($this->adminEssaouira)
            ->getJson(route('messages.show', $conv))
            ->assertForbidden();

        // Le professeur d'Agadir (non participant) tente d'accéder
        $this->actingAs($this->teacherUserAgadir)
            ->getJson(route('messages.show', $conv))
            ->assertForbidden();
    }

    public function test_user_cannot_send_message_to_conversation_they_are_not_participant_in(): void
    {
        $conv = Conversation::findOrCreateDirect($this->superAdmin, $this->adminAgadir);

        $this->actingAs($this->adminEssaouira)
            ->postJson(route('messages.send', $conv), ['content' => 'Tentative injection'])
            ->assertForbidden();
    }

    // =========================================================================
    // 7. Envoi, réception, historique, horodatage et pièces jointes
    // =========================================================================

    public function test_send_and_receive_message_with_attachment(): void
    {
        $conv = Conversation::findOrCreateDirect($this->adminAgadir, $this->teacherUserAgadir);

        $file = UploadedFile::fake()->create('document_pedagogique.pdf', 100, 'application/pdf');

        $res = $this->actingAs($this->adminAgadir)
            ->postJson(route('messages.send', $conv), [
                'content' => 'Voici les consignes du semestre',
                'file'    => $file,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $attachmentId = $res->json('message.attachments.0.id');
        $this->assertNotNull($attachmentId);

        // Le professeur télécharge la pièce jointe
        $this->actingAs($this->teacherUserAgadir)
            ->get(route('messages.attachment', $attachmentId))
            ->assertOk();

        // Un utilisateur externe ne peut pas télécharger la pièce jointe
        $this->actingAs($this->teacherUserEssaouira)
            ->get(route('messages.attachment', $attachmentId))
            ->assertForbidden();
    }

    // =========================================================================
    // 8. Notifications système en base
    // =========================================================================

    public function test_notification_is_sent_on_new_message(): void
    {
        $conv = Conversation::findOrCreateDirect($this->adminAgadir, $this->teacherUserAgadir);

        $this->actingAs($this->adminAgadir)
            ->postJson(route('messages.send', $conv), [
                'content' => 'Notification test message',
            ])
            ->assertOk();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->teacherUserAgadir->id,
            'type'    => 'message',
        ]);
    }
}

