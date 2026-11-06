<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretaryFormationSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected Center $center;
    protected User $secretary;
    protected Formation $formationSante;
    protected Formation $formationInfo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->center = Center::create([
            'name' => 'Centre Tanger',
            'code' => 'TGR',
            'city' => 'Tanger',
            'country' => 'Morocco',
            'status' => 'active',
        ]);

        $this->secretary = User::create([
            'name' => 'Secrétaire Tanger',
            'email' => 'secretaire.tanger@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SECRETARY,
            'center_id' => $this->center->id,
            'status' => 'active',
        ]);

        $this->formationSante = Formation::create([
            'center_id' => $this->center->id,
            'name' => 'Santé',
            'code' => 'SANTE-TGR',
            'official_code' => 'sante',
            'is_official' => true,
            'status' => 'active',
            'capacity' => 30,
            'price' => 2500,
        ]);

        $this->formationInfo = Formation::create([
            'center_id' => $this->center->id,
            'name' => 'Informatique',
            'code' => 'INFO-TGR',
            'official_code' => 'informatique',
            'is_official' => true,
            'status' => 'active',
            'capacity' => 30,
            'price' => 3000,
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

    public function test_secretary_outside_formation_sees_full_sidebar(): void
    {
        // En accédant à une page avec sidebar (ex: formations.index) sans session formation
        $response = $this->actingAs($this->secretary)->get(route('formations.index'));
        $response->assertOk();

        $sidebar = $this->sidebarHtml($response->getContent());

        // Les sections globales doivent être visibles
        $this->assertStringContainsString('Élèves', $sidebar);
        $this->assertStringContainsString('Inscriptions', $sidebar);
        $this->assertStringContainsString('Stagiaires', $sidebar);
        $this->assertStringContainsString('Salles', $sidebar);
    }

    public function test_secretary_entering_sante_formation_sees_only_the_6_modules(): void
    {
        // Accéder à la formation Santé
        $response = $this->actingAs($this->secretary)
            ->get(route('formations-generales.show', $this->formationSante));

        $response->assertOk();
        $this->assertEquals($this->formationSante->id, session('active_formation_id'));

        // Naviguer sur une page avec sidebar en conservant la session
        $pageResponse = $this->actingAs($this->secretary)
            ->withSession([
                'active_formation_id' => $this->formationSante->id,
                'active_domain' => 'sante',
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

        // 2. Les modules hors formation doivent être masqués
        $this->assertStringNotContainsString('Élèves', $sidebar);
        $this->assertStringNotContainsString('Inscriptions', $sidebar);
        $this->assertStringNotContainsString('Stagiaires', $sidebar);
        $this->assertStringNotContainsString('Salles', $sidebar);
        $this->assertStringNotContainsString('Annonces', $sidebar);
    }

    public function test_secretary_entering_informatique_formation_sees_only_the_6_modules(): void
    {
        // Accéder à la formation Informatique
        $response = $this->actingAs($this->secretary)
            ->get(route('formations-generales.show', $this->formationInfo));

        $response->assertOk();

        $pageResponse = $this->actingAs($this->secretary)
            ->withSession([
                'active_formation_id' => $this->formationInfo->id,
                'active_domain' => 'informatique',
            ])
            ->get(route('attendances.index'));

        $pageResponse->assertOk();
        $sidebar = $this->sidebarHtml($pageResponse->getContent());

        $this->assertStringContainsString('Dashboard', $sidebar);
        $this->assertStringContainsString('Emploi du temps', $sidebar);
        $this->assertStringContainsString('Présences', $sidebar);
        $this->assertStringContainsString('Évaluations', $sidebar);
        $this->assertStringContainsString('Certificats', $sidebar);
        $this->assertStringContainsString('Notifications', $sidebar);

        $this->assertStringNotContainsString('Élèves', $sidebar);
        $this->assertStringNotContainsString('Inscriptions', $sidebar);
        $this->assertStringNotContainsString('Stagiaires', $sidebar);
    }
}
