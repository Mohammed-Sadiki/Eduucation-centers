<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminFormationSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected Center $center;
    protected User $superAdmin;
    protected Formation $formationTii;

    protected function setUp(): void
    {
        parent::setUp();

        $this->center = Center::create([
            'name' => 'Centre Agadir',
            'code' => 'AGA',
            'city' => 'Agadir',
            'country' => 'Morocco',
            'status' => 'active',
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
        ]);

        $this->formationTii = Formation::create([
            'center_id' => $this->center->id,
            'name' => 'tii',
            'code' => 'TII-01',
            'is_official' => true,
            'status' => 'active',
            'capacity' => 25,
            'price' => 2000,
        ]);
    }

    private function sidebarHtml(string $html): string
    {
        $start = strpos($html, '<aside');
        $this->assertNotFalse($start, 'La sidebar (<aside>) doit être rendue.');
        $end = strpos($html, '</aside>', $start);
        $this->assertNotFalse($end, 'La sidebar doit être correctement fermée.');

        return substr($html, $start, $end - $start);
    }

    public function test_super_admin_entering_formation_sees_only_the_6_modules(): void
    {
        // Accéder à la formation 'tii'
        $response = $this->actingAs($this->superAdmin)
            ->get(route('formations-generales.show', $this->formationTii));

        $response->assertOk();
        $this->assertEquals($this->formationTii->id, session('active_formation_id'));

        // Naviguer sur une page avec sidebar (ex: schedules.index) en conservant la session
        $pageResponse = $this->actingAs($this->superAdmin)
            ->withSession([
                'active_formation_id' => $this->formationTii->id,
                'active_domain' => null,
            ])
            ->get(route('schedules.index'));

        $pageResponse->assertOk();
        $sidebar = $this->sidebarHtml($pageResponse->getContent());

        // 1. Les 6 modules demandés doivent être présents
        $this->assertStringContainsString('Dashboard', $sidebar);
        $this->assertStringContainsString('Emploi du temps', $sidebar);
        $this->assertStringContainsString('Présences', $sidebar);
        $this->assertStringContainsString('Évaluations', $sidebar);
        $this->assertStringContainsString('Certificats', $sidebar);
        $this->assertStringContainsString('Notifications', $sidebar);

        // 2. Les rubriques globales/catégories accordéons doivent être masquées
        $this->assertStringNotContainsString('PÉDAGOGIE', $sidebar);
        $this->assertStringNotContainsString('GESTION', $sidebar);
        $this->assertStringNotContainsString('COMMUNICATION', $sidebar);
        $this->assertStringNotContainsString('Centres', $sidebar);
        $this->assertStringNotContainsString('Inscriptions', $sidebar);
        $this->assertStringNotContainsString('Salles', $sidebar);
        $this->assertStringNotContainsString('Annonces', $sidebar);
    }

    public function test_super_admin_outside_formation_sees_full_sidebar(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('formations.index'));
        $response->assertOk();

        $sidebar = $this->sidebarHtml($response->getContent());

        $this->assertStringContainsString('Centres', $sidebar);
        $this->assertStringContainsString('Pédagogie', $sidebar);
        $this->assertStringContainsString('Gestion', $sidebar);
        $this->assertStringContainsString('Communication', $sidebar);
    }
}
