<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Phase 7 — Cohérence i18n FR/AR + RTL sur les nouvelles pages domaine/portail.
 *
 * Vérifie que le portail Admin Centre, les dashboards de domaine (Super Admin et
 * Admin Centre) et la bannière de contexte s'affichent correctement en arabe avec
 * direction RTL, et que les libellés ajoutés sont bien traduits.
 */
class DomainPortalI18nTest extends TestCase
{
    use RefreshDatabase;

    private Center $center;

    private Formation $sante;

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->center = Center::create(['name' => 'Alpha', 'code' => 'AL', 'address' => 'A', 'status' => 'active']);
        $this->superAdmin = User::factory()->superAdmin()->create(['locale' => 'ar', 'status' => 'active']);
        $this->admin = User::factory()->centerAdmin($this->center->id)->create(['locale' => 'ar', 'status' => 'active']);

        $this->sante = Formation::create([
            'center_id' => $this->center->id, 'domain' => 'sante',
            'name' => 'Infirmier', 'code' => 'INF', 'status' => 'active',
        ]);

        $user = User::factory()->create(['role' => 'student', 'center_id' => $this->center->id]);
        $student = Student::create([
            'user_id' => $user->id, 'center_id' => $this->center->id, 'student_number' => 'AR-1',
            'first_name' => 'Eleve', 'last_name' => 'X', 'date_of_birth' => '2000-01-01',
            'gender' => 'male', 'status' => 'active',
        ]);
        Enrollment::create([
            'center_id' => $this->center->id, 'student_id' => $student->id, 'formation_id' => $this->sante->id,
            'registration_number' => 'E-1', 'application_date' => '2026-01-01', 'status' => 'accepted',
        ]);
    }

    public function test_center_admin_portal_renders_rtl_in_arabic(): void
    {
        $this->actingAs($this->admin)
            ->get(route('center-admin.domaines'))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_center_admin_domain_dashboard_renders_rtl_in_arabic(): void
    {
        $this->actingAs($this->admin)
            ->get(route('center-admin.domain.dashboard', 'sante'))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_super_admin_domain_dashboard_renders_rtl_in_arabic(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.domain.dashboard', 'sante'))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_new_portal_labels_are_translated_in_arabic(): void
    {
        App::setLocale('ar');

        $this->assertSame('بوابة مسؤول المركز', __('Portail Admin Centre'));
        $this->assertSame('فضاء مسؤول المركز', __('Espace Admin Centre'));
        $this->assertSame('التكوينات العامة', __('Formations générales'));
        $this->assertSame('التكوين العام النشط', __('Formation générale active'));
        $this->assertSame('تغيير التكوين العام', __('Changer de formation générale'));
        $this->assertSame('جميع التكوينات العامة', __('Toutes les formations générales'));
    }

    public function test_labels_remain_french_in_french_locale(): void
    {
        App::setLocale('fr');

        $this->assertSame('Portail Admin Centre', __('Portail Admin Centre'));
        $this->assertSame('Espace Admin Centre', __('Espace Admin Centre'));
    }
}
