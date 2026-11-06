<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ClassRoom;
use App\Models\Conversation;
use App\Models\Formation;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationRoleRulesTest extends TestCase
{
    use RefreshDatabase;

    private Center $centerBerlin;
    private Center $centerMunich;

    private User $superAdmin;
    private User $adminBerlin;
    private User $adminMunich;

    private User $teacherUserBerlin;
    private Teacher $teacherBerlin;

    private User $teacherUserMunich;
    private Teacher $teacherMunich;

    private User $multiTeacherUser;
    private Teacher $multiTeacher;

    private User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Centres
        $this->centerBerlin = Center::create(['name' => 'Centre Berlin', 'code' => 'BER', 'status' => 'active']);
        $this->centerMunich = Center::create(['name' => 'Centre Munich', 'code' => 'MUN', 'status' => 'active']);

        // 2. Utilisateurs Administrateurs
        $this->superAdmin = User::factory()->superAdmin()->create(['name' => 'Super Admin', 'status' => 'active']);
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create(['name' => 'Admin Berlin', 'status' => 'active']);
        $this->adminMunich = User::factory()->centerAdmin($this->centerMunich->id)->create(['name' => 'Admin Munich', 'status' => 'active']);

        // 3. Professeur Berlin
        $this->teacherUserBerlin = User::factory()->teacher($this->centerBerlin->id)->create(['name' => 'Prof Berlin', 'status' => 'active']);
        $this->teacherBerlin = Teacher::create([
            'user_id' => $this->teacherUserBerlin->id,
            'center_id' => $this->centerBerlin->id,
            'teacher_number' => 'ENS-BER-01',
            'first_name' => 'Hans',
            'last_name' => 'Berlin',
            'status' => 'active',
        ]);

        // 4. Professeur Munich
        $this->teacherUserMunich = User::factory()->teacher($this->centerMunich->id)->create(['name' => 'Prof Munich', 'status' => 'active']);
        $this->teacherMunich = Teacher::create([
            'user_id' => $this->teacherUserMunich->id,
            'center_id' => $this->centerMunich->id,
            'teacher_number' => 'ENS-MUN-01',
            'first_name' => 'Klaus',
            'last_name' => 'Munich',
            'status' => 'active',
        ]);

        // 5. Professeur Multi-centres (rattaché à Berlin, mais avec une classe à Munich)
        $this->multiTeacherUser = User::factory()->teacher($this->centerBerlin->id)->create(['name' => 'Prof Multi', 'status' => 'active']);
        $this->multiTeacher = Teacher::create([
            'user_id' => $this->multiTeacherUser->id,
            'center_id' => $this->centerBerlin->id,
            'teacher_number' => 'ENS-MULTI-01',
            'first_name' => 'Otto',
            'last_name' => 'Multi',
            'status' => 'active',
        ]);

        $formationMunich = Formation::create([
            'center_id' => $this->centerMunich->id,
            'domain' => 'informatique',
            'name' => 'Web Dev Munich',
            'code' => 'WDM',
            'status' => 'active',
        ]);

        $classMunich = ClassRoom::create([
            'center_id' => $this->centerMunich->id,
            'formation_id' => $formationMunich->id,
            'name' => 'Classe Munich Dev',
            'code' => 'CMD-1',
            'capacity' => 25,
            'status' => 'active',
        ]);

        $this->multiTeacher->classes()->attach($classMunich->id);

        // 6. Étudiant
        $this->studentUser = User::factory()->student($this->centerBerlin->id)->create(['status' => 'active']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1 & 2. Super Admin <-> Admin Berlin (Bidirectionnel)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_send_to_admin_berlin_and_admin_berlin_can_reply(): void
    {
        // Super Admin initie
        $res = $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->adminBerlin))
            ->assertOk();

        $conversationId = $res->json('conversation_id');
        $conversation = Conversation::find($conversationId);

        // Super Admin envoie
        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Message Super Admin -> Berlin'])
            ->assertOk();

        // Admin Berlin répond
        $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Réponse Berlin -> Super Admin'])
            ->assertOk();

        $this->assertDatabaseHas('messages', ['content' => 'Message Super Admin -> Berlin']);
        $this->assertDatabaseHas('messages', ['content' => 'Réponse Berlin -> Super Admin']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3 & 4. Super Admin <-> Admin Munich (Bidirectionnel)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_send_to_admin_munich_and_admin_munich_can_reply(): void
    {
        $res = $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->adminMunich))
            ->assertOk();

        $conversation = Conversation::find($res->json('conversation_id'));

        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Message Super Admin -> Munich'])
            ->assertOk();

        $this->actingAs($this->adminMunich)
            ->postJson(route('messages.send', $conversation), ['content' => 'Réponse Munich -> Super Admin'])
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5 & 6. Super Admin -> Professeurs (Berlin et Munich)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_send_to_teacher_berlin_and_teacher_munich(): void
    {
        // Super Admin -> Prof Berlin
        $resBerlin = $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->teacherUserBerlin))
            ->assertOk();

        $convBerlin = Conversation::find($resBerlin->json('conversation_id'));

        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.send', $convBerlin), ['content' => 'Directives pédagogiques Berlin'])
            ->assertOk();

        // Super Admin -> Prof Munich
        $resMunich = $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->teacherUserMunich))
            ->assertOk();

        $convMunich = Conversation::find($resMunich->json('conversation_id'));

        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.send', $convMunich), ['content' => 'Directives pédagogiques Munich'])
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7 & 8. Admin Centre -> Professeurs de son propre centre
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_berlin_can_send_to_teacher_berlin(): void
    {
        $res = $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.start-direct', $this->teacherUserBerlin))
            ->assertOk();

        $conversation = Conversation::find($res->json('conversation_id'));

        $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Note de service Berlin'])
            ->assertOk();
    }

    public function test_admin_munich_can_send_to_teacher_munich(): void
    {
        $res = $this->actingAs($this->adminMunich)
            ->postJson(route('messages.start-direct', $this->teacherUserMunich))
            ->assertOk();

        $conversation = Conversation::find($res->json('conversation_id'));

        $this->actingAs($this->adminMunich)
            ->postJson(route('messages.send', $conversation), ['content' => 'Note de service Munich'])
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9 & 10. Admin Berlin <-> Admin Munich INTERDIT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_berlin_cannot_communicate_with_admin_munich(): void
    {
        $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.start-direct', $this->adminMunich))
            ->assertForbidden();

        $this->actingAs($this->adminMunich)
            ->postJson(route('messages.start-direct', $this->adminBerlin))
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11 & 12. Admin Centre -> Professeurs d'un AUTRE centre INTERDIT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_berlin_cannot_send_to_teacher_only_in_munich(): void
    {
        $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.start-direct', $this->teacherUserMunich))
            ->assertForbidden();
    }

    public function test_admin_munich_cannot_send_to_teacher_only_in_berlin(): void
    {
        $this->actingAs($this->adminMunich)
            ->postJson(route('messages.start-direct', $this->teacherUserBerlin))
            ->assertForbidden();
    }

    // Multi-centres : Un prof affecté aux deux centres est joignable par les deux admins
    public function test_multi_center_teacher_can_be_contacted_by_both_admins(): void
    {
        $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.start-direct', $this->multiTeacherUser))
            ->assertOk();

        $this->actingAs($this->adminMunich)
            ->postJson(route('messages.start-direct', $this->multiTeacherUser))
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 13, 14 & 15. Professeur : peut initier avec Super Admin et Admin de son centre,
    // mais professeur <-> professeur et professeur <-> autre centre INTERDITS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_teacher_can_send_to_super_admin(): void
    {
        $this->actingAs($this->teacherUserBerlin)
            ->postJson(route('messages.start-direct', $this->superAdmin))
            ->assertOk();
    }

    public function test_teacher_can_send_to_center_admin(): void
    {
        $this->actingAs($this->teacherUserBerlin)
            ->postJson(route('messages.start-direct', $this->adminBerlin))
            ->assertOk();
    }

    public function test_teacher_cannot_send_to_another_teacher(): void
    {
        $this->actingAs($this->teacherUserBerlin)
            ->postJson(route('messages.start-direct', $this->teacherUserMunich))
            ->assertForbidden();
    }

    public function test_teacher_can_reply_to_admin_in_existing_conversation(): void
    {
        $res = $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.start-direct', $this->teacherUserBerlin))
            ->assertOk();

        $conversation = Conversation::find($res->json('conversation_id'));

        // L'admin envoie d'abord un message
        $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Note de service Berlin'])
            ->assertOk();

        // Le professeur répond dans la conversation existante
        $this->actingAs($this->teacherUserBerlin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Bien reçu, merci'])
            ->assertOk();

        $this->assertDatabaseHas('messages', ['content' => 'Bien reçu, merci', 'sender_id' => $this->teacherUserBerlin->id]);
    }

    public function test_teacher_can_reply_to_super_admin_in_existing_conversation(): void
    {
        $res = $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->teacherUserBerlin))
            ->assertOk();

        $conversation = Conversation::find($res->json('conversation_id'));

        $this->actingAs($this->teacherUserBerlin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Réponse au Super Admin'])
            ->assertOk();
    }

    public function test_teacher_cannot_reply_to_another_center_admin(): void
    {
        // Conversation Munich -> Prof Berlin n'existe pas (communication interdite),
        // mais même forcée, la réponse vers un admin d'un autre centre est refusée.
        $conversation = Conversation::create(['type' => 'direct', 'center_id' => $this->centerMunich->id]);
        $conversation->participants()->create(['user_id' => $this->adminMunich->id]);
        $conversation->participants()->create(['user_id' => $this->teacherUserBerlin->id]);

        $this->actingAs($this->teacherUserBerlin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Tentative hors centre'])
            ->assertForbidden();
    }

    public function test_teacher_cannot_initiate_conversation_with_another_center_admin(): void
    {
        $this->actingAs($this->teacherUserBerlin)
            ->postJson(route('messages.start-direct', $this->adminMunich))
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 16 & 17. Professeur peut lire ses messages mais pas ceux d'un autre
    // ──────────────────────────────────────────────────────────────────────────

    public function test_teacher_can_read_received_messages(): void
    {
        $res = $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->teacherUserBerlin))
            ->assertOk();

        $conversation = Conversation::find($res->json('conversation_id'));

        $this->actingAs($this->superAdmin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Message officiel pour Berlin']);

        // Professeur Berlin accède à la page messagerie
        $this->actingAs($this->teacherUserBerlin)
            ->get(route('teacher.messages'))
            ->assertOk()
            ->assertSee('Messagerie');

        // Professeur lit les messages via JSON (messagerie bidirectionnelle)
        $this->actingAs($this->teacherUserBerlin)
            ->getJson(route('messages.show', $conversation))
            ->assertOk()
            ->assertJsonPath('can_reply', true)
            ->assertJsonPath('messages.0.content', 'Message officiel pour Berlin');
    }

    public function test_teacher_cannot_read_another_teacher_conversation(): void
    {
        $res = $this->actingAs($this->superAdmin)
            ->postJson(route('messages.start-direct', $this->teacherUserBerlin))
            ->assertOk();

        $conversation = Conversation::find($res->json('conversation_id'));

        // Professeur Munich tente d'accéder à la conversation du Professeur Berlin
        $this->actingAs($this->teacherUserMunich)
            ->getJson(route('messages.show', $conversation))
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 18. Étudiant n'a aucun accès
    // ──────────────────────────────────────────────────────────────────────────

    public function test_student_has_no_access_to_messaging(): void
    {
        $this->actingAs($this->studentUser)
            ->get(route('messages.index'))
            ->assertForbidden();

        $this->actingAs($this->studentUser)
            ->get(route('teacher.messages'))
            ->assertForbidden();

        $this->actingAs($this->studentUser)
            ->getJson(route('messages.conversations'))
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 19. Notifications créées lors de l'envoi
    // ──────────────────────────────────────────────────────────────────────────

    public function test_recipient_receives_in_app_notification_when_message_sent(): void
    {
        $res = $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.start-direct', $this->teacherUserBerlin))
            ->assertOk();

        $conversation = Conversation::find($res->json('conversation_id'));

        $this->actingAs($this->adminBerlin)
            ->postJson(route('messages.send', $conversation), ['content' => 'Réunion pédagogique demain à 14h']);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->teacherUserBerlin->id,
            'type' => 'message',
            'link' => route('teacher.messages', ['conversation_id' => $conversation->id]),
        ]);
    }
}
