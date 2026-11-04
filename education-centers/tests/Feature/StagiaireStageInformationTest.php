<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasStagiaireFormData;
use Tests\TestCase;

/**
 * Section « Informations du stage » des formulaires stagiaires.
 *
 * Trois champs ajoutés sans toucher aux autres : date de début de stage
 * (obligatoire), date de fin de stage (obligatoire, postérieure ou égale au
 * début) et prix du stage en dirhams (obligatoire, positif ou nul).
 *
 * Le même formulaire servant au Super Admin et à l'Admin Centre — le centre de
 * ce dernier restant imposé côté serveur — les règles et le rendu sont vérifiés
 * pour les deux rôles, ainsi que les permissions existantes (professeur exclu,
 * isolation des centres).
 *
 * 1. Carte présente et correctement instrumentée à l'ajout comme à l'édition.
 * 2. Absence, incohérence de période et montant négatif refusés ; montant nul accepté.
 * 3. Enregistrement des trois valeurs, devise par défaut (DH) comprise.
 * 4. Affichage en page de détails puis modification persistée.
 * 5. Admin Centre : mêmes champs, mêmes règles, périmètre conservé.
 * 6. Traductions FR/AR, RTL et responsive.
 */
class StagiaireStageInformationTest extends TestCase
{
    use HasStagiaireFormData;
    use RefreshDatabase;

    protected Center $berlin;

    protected Center $munich;

    protected User $superAdmin;

    protected User $adminBerlin;

    protected User $adminMunich;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->berlin = Center::factory()->create(['name' => 'Centre Berlin', 'code' => 'BER']);
        $this->munich = Center::factory()->create(['name' => 'Centre Munich', 'code' => 'MUN']);

        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa-stage@test.de']);
        $this->adminBerlin = User::factory()->centerAdmin($this->berlin->id)->create(['email' => 'ab-stage@test.de']);
        $this->adminMunich = User::factory()->centerAdmin($this->munich->id)->create(['email' => 'am-stage@test.de']);
    }

    /**
     * Dossier complet envoyé par le formulaire du Super Admin, centre inclus.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $this->stagiairePayloadWithCin(array_merge(
            ['center_id' => $this->berlin->id],
            $overrides
        ));
    }

    private function createPageHtml(User $as): string
    {
        return $this->actingAs($as)->get(route('stagiaires.create'))->assertOk()->getContent();
    }

    /**
     * HTML de la carte « Informations du stage » : de son ouverture à sa fermeture.
     */
    private function stageCardHtml(string $html): string
    {
        $title = strpos($html, __('Informations du stage'));
        $this->assertNotFalse($title, 'Le titre « Informations du stage » doit être rendu.');

        $start = strrpos(substr($html, 0, $title), '<section class="ec-card');
        $this->assertNotFalse($start, 'La carte « Informations du stage » doit être rendue.');

        $end = strpos($html, '</section>', $title);
        $this->assertNotFalse($end, 'La carte « Informations du stage » doit être fermée.');

        return substr($html, $start, $end - $start);
    }

    /**
     * Attribut complet d'un champ du formulaire.
     */
    private function inputTag(string $html, string $field): string
    {
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\bname="'.preg_quote($field, '/').'"[^>]*>/s',
            $html,
            "Le champ « {$field} » doit être présent."
        );

        preg_match('/<input\b[^>]*\bname="'.preg_quote($field, '/').'"[^>]*>/s', $html, $tag);

        return $tag[0];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Rendu de la section
    // ──────────────────────────────────────────────────────────────────────────

    public function test_creation_form_shows_the_stage_card_with_three_required_fields(): void
    {
        $card = $this->stageCardHtml($this->createPageHtml($this->superAdmin));

        // Libellés demandés, avec le marqueur d'obligation.
        foreach (['Date de début de stage', 'Date de fin de stage', 'Prix du stage (DH)'] as $label) {
            $this->assertStringContainsString(__($label), $card);
        }

        $this->assertGreaterThanOrEqual(3, substr_count($card, 'text-rose-500">*</span>'),
            'Les trois champs de la section doivent être marqués obligatoires.');

        // Types et instrumentation de contrôle.
        $this->assertStringContainsString('type="date"', $this->inputTag($card, 'stage_start_date'));
        $this->assertStringContainsString('type="date"', $this->inputTag($card, 'stage_end_date'));
        $this->assertStringContainsString('type="number"', $this->inputTag($card, 'stage_price'));

        foreach (['stage_start_date', 'stage_end_date', 'stage_price'] as $field) {
            $tag = $this->inputTag($card, $field);
            $this->assertStringContainsString('required', $tag, "« {$field} » doit exiger la saisie.");
            $this->assertStringContainsString('data-required', $tag, "« {$field} » doit être contrôlé par le script.");
        }

        // Règles reprises côté client : fin ≥ début, montant ≥ 0.
        $this->assertStringContainsString('data-date-not-before="stage_start_date"', $this->inputTag($card, 'stage_end_date'));
        $this->assertStringContainsString('min="0"', $this->inputTag($card, 'stage_price'));
        $this->assertStringContainsString('step="0.01"', $this->inputTag($card, 'stage_price'));
    }

    public function test_stage_card_is_responsive_and_keeps_the_card_design(): void
    {
        $html = $this->createPageHtml($this->superAdmin);

        // Pleine largeur sur desktop, même coquille que les autres cartes.
        $this->assertMatchesRegularExpression(
            '/<section class="ec-card overflow-hidden lg:col-span-2">\s*<header class="border-b[^"]*dark:bg-slate-800\/40">\s*<h2 class="flex items-center gap-3 text-2xl/',
            $this->stageCardHtml($html),
            'La carte doit reprendre la présentation des cartes existantes.'
        );

        // Une colonne sur mobile, puis deux et trois colonnes aux points de rupture.
        $this->assertStringContainsString('grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-3', $this->stageCardHtml($html));
    }

    public function test_edit_form_shows_the_stage_card_prefilled_with_saved_values(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'stage_start_date' => '2026-11-01',
            'stage_end_date' => '2027-04-30',
            'stage_price' => 1500.00,
        ]));

        $html = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.edit', $stagiaire))
            ->assertOk()
            ->getContent();

        $card = $this->stageCardHtml($html);

        $this->assertStringContainsString('value="2026-11-01"', $this->inputTag($card, 'stage_start_date'));
        $this->assertStringContainsString('value="2027-04-30"', $this->inputTag($card, 'stage_end_date'));
        $this->assertStringContainsString('value="1500"', $this->inputTag($card, 'stage_price'));

        foreach (['stage_start_date', 'stage_end_date', 'stage_price'] as $field) {
            $this->assertStringContainsString('required data-required', $this->inputTag($card, $field),
                "« {$field} » doit rester obligatoire en modification.");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Règles de validation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_each_stage_field_is_mandatory(): void
    {
        foreach (['stage_start_date', 'stage_end_date', 'stage_price'] as $field) {
            $payload = $this->form();
            unset($payload[$field]);

            $this->actingAs($this->superAdmin)
                ->from(route('stagiaires.create'))
                ->post(route('stagiaires.store'), $payload)
                ->assertSessionHasErrors($field);

            $this->assertSame(0, Stagiaire::count(), "Aucun dossier ne doit être enregistré sans « {$field} ».");
        }
    }

    public function test_end_date_earlier_than_start_date_is_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->post(route('stagiaires.store'), $this->form([
                'stage_start_date' => '2027-04-30',
                'stage_end_date' => '2026-11-01',
            ]))
            ->assertSessionHasErrors('stage_end_date');

        $this->assertSame(0, Stagiaire::count());
    }

    /**
     * « Supérieure ou égale » : une période d'un seul jour reste valide.
     */
    public function test_identical_start_and_end_dates_are_accepted(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'stage_start_date' => '2026-12-01',
                'stage_end_date' => '2026-12-01',
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::firstOrFail();
        $this->assertSame('2026-12-01', $stagiaire->stage_start_date->toDateString());
        $this->assertSame('2026-12-01', $stagiaire->stage_end_date->toDateString());
    }

    public function test_invalid_dates_and_negative_price_are_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['stage_start_date' => 'pas-une-date']))
            ->assertSessionHasErrors('stage_start_date');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['stage_price' => -250]))
            ->assertSessionHasErrors('stage_price');

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['stage_price' => 'gratuit']))
            ->assertSessionHasErrors('stage_price');

        $this->assertSame(0, Stagiaire::count());
    }

    /**
     * « Positif ou nul » : la gratuité (0) reste une valeur valide.
     */
    public function test_zero_stage_price_is_accepted(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form(['stage_price' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) Stagiaire::firstOrFail()->stage_price);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Enregistrement
    // ──────────────────────────────────────────────────────────────────────────

    public function test_stage_information_is_saved_with_the_stagiaire(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $this->form([
                'stage_start_date' => '2026-10-05',
                'stage_end_date' => '2027-03-20',
                'stage_price' => 2400.50,
                'currency' => default_currency(),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('stagiaires', [
            'last_name' => 'Benani',
            'currency' => default_currency(),
        ]);

        $stagiaire = Stagiaire::firstOrFail();
        $this->assertSame('2026-10-05', $stagiaire->stage_start_date->toDateString());
        $this->assertSame('2027-03-20', $stagiaire->stage_end_date->toDateString());
        $this->assertSame(2400.50, (float) $stagiaire->stage_price);
        $this->assertSame('MAD', $stagiaire->currency);
        $this->assertStringEndsWith('DH', $stagiaire->formatted_stage_price);
    }

    /**
     * Le formulaire n'affiche aucune devise : le serveur applique celle de la
     * plateforme, ce qui correspond au libellé « Prix du stage (DH) ».
     */
    public function test_price_is_recorded_in_dirhams_when_no_currency_is_sent(): void
    {
        $payload = $this->form();
        unset($payload['currency']);

        $this->actingAs($this->superAdmin)
            ->post(route('stagiaires.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stagiaires', ['currency' => default_currency()]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Affichage en détails puis modification
    // ──────────────────────────────────────────────────────────────────────────

    public function test_details_page_displays_period_and_price(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'stage_start_date' => '2026-11-01',
            'stage_end_date' => '2027-04-30',
            'stage_price' => 1500.00,
            'currency' => default_currency(),
        ]));

        $html = $this->actingAs($this->superAdmin)
            ->get(route('stagiaires.show', $stagiaire))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('Informations du stage'), $html);
        $this->assertStringContainsString($stagiaire->stage_start_date->format('d/m/Y'), $html);
        $this->assertStringContainsString($stagiaire->stage_end_date->format('d/m/Y'), $html);
        $this->assertStringContainsString($stagiaire->formatted_stage_price, $html);
    }

    public function test_stage_information_can_be_modified(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'cin_recto_path' => 'stagiaires/cin/recto.pdf',
            'cin_verso_path' => 'stagiaires/cin/verso.pdf',
            'profile_photo_path' => 'stagiaires/1/profile/profil.jpg',
            'stage_start_date' => '2026-11-01',
            'stage_end_date' => '2027-04-30',
            'stage_price' => 1500.00,
        ]));

        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'stage_start_date' => '2027-01-10',
                'stage_end_date' => '2027-06-25',
                'stage_price' => 1800.00,
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire->refresh();
        $this->assertSame('2027-01-10', $stagiaire->stage_start_date->toDateString());
        $this->assertSame('2027-06-25', $stagiaire->stage_end_date->toDateString());
        $this->assertSame(1800.00, (float) $stagiaire->stage_price);
    }

    public function test_editing_another_section_keeps_the_stage_information_untouched(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->berlin->id,
            'cin_recto_path' => 'stagiaires/cin/recto.pdf',
            'cin_verso_path' => 'stagiaires/cin/verso.pdf',
            'profile_photo_path' => 'stagiaires/1/profile/profil.jpg',
            'stage_start_date' => '2026-11-01',
            'stage_end_date' => '2027-04-30',
            'stage_price' => 1500.00,
        ]));

        $this->actingAs($this->superAdmin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->berlin->id,
                'last_name' => 'Benani-Actualisé',
                'company_name' => 'Siemens Berlin',
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire->refresh();
        $this->assertSame('Benani-Actualisé', $stagiaire->last_name);
        $this->assertSame('Siemens Berlin', $stagiaire->company_name);
        $this->assertSame('2026-11-01', $stagiaire->stage_start_date->toDateString());
        $this->assertSame('2027-04-30', $stagiaire->stage_end_date->toDateString());
        $this->assertSame(1500.00, (float) $stagiaire->stage_price);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Admins Centre et permissions existantes
    // ──────────────────────────────────────────────────────────────────────────

    public function test_center_admin_form_offers_the_same_stage_fields(): void
    {
        $card = $this->stageCardHtml($this->createPageHtml($this->adminBerlin));

        foreach (['stage_start_date', 'stage_end_date', 'stage_price'] as $field) {
            $this->assertStringContainsString('data-required', $this->inputTag($card, $field));
        }

        // Aucun sélecteur de centre pour un Admin Centre : le périmètre reste imposé.
        $this->assertStringNotContainsString('name="center_id"', $this->createPageHtml($this->adminBerlin));
    }

    public function test_center_admin_saves_stage_information_for_its_own_center(): void
    {
        $this->actingAs($this->adminBerlin)
            ->post(route('stagiaires.store'), $this->stagiairePayloadWithCin([
                'stage_start_date' => '2026-09-15',
                'stage_end_date' => '2027-01-30',
                'stage_price' => 900.00,
            ]))
            ->assertSessionHasNoErrors();

        $stagiaire = Stagiaire::firstOrFail();
        $this->assertSame($this->berlin->id, $stagiaire->center_id);
        $this->assertSame('2026-09-15', $stagiaire->stage_start_date->toDateString());
        $this->assertSame('2027-01-30', $stagiaire->stage_end_date->toDateString());
        $this->assertSame(900.00, (float) $stagiaire->stage_price);
    }

    public function test_center_admin_must_respect_the_same_stage_rules(): void
    {
        $this->actingAs($this->adminBerlin)
            ->post(route('stagiaires.store'), $this->withoutField(
                $this->stagiairePayloadWithCin(),
                'stage_price'
            ))
            ->assertSessionHasErrors('stage_price');

        $this->actingAs($this->adminBerlin)
            ->post(route('stagiaires.store'), $this->stagiairePayloadWithCin([
                'stage_start_date' => '2027-05-01',
                'stage_end_date' => '2027-04-01',
            ]))
            ->assertSessionHasErrors('stage_end_date');

        $this->assertSame(0, Stagiaire::count());
    }

    public function test_center_admin_cannot_change_stage_information_of_another_center(): void
    {
        $stagiaire = Stagiaire::create($this->stagiairePayload([
            'center_id' => $this->munich->id,
            'stage_start_date' => '2026-11-01',
            'stage_end_date' => '2027-04-30',
            'stage_price' => 1500.00,
        ]));

        $this->actingAs($this->adminBerlin)
            ->get(route('stagiaires.edit', $stagiaire))
            ->assertForbidden();

        $this->actingAs($this->adminBerlin)
            ->put(route('stagiaires.update', $stagiaire), $this->stagiairePayload([
                'center_id' => $this->munich->id,
                'stage_start_date' => '2020-01-01',
                'stage_end_date' => '2020-01-02',
                'stage_price' => 1.00,
            ]))
            ->assertForbidden();

        $stagiaire->refresh();
        $this->assertSame('2026-11-01', $stagiaire->stage_start_date->toDateString());
        $this->assertSame(1500.00, (float) $stagiaire->stage_price);
    }

    public function test_teacher_never_reaches_the_stage_form(): void
    {
        $teacher = User::factory()->teacher()->create([
            'email' => 't-stage@test.de',
            'center_id' => $this->berlin->id,
        ]);

        $this->actingAs($teacher)->get(route('stagiaires.create'))->assertForbidden();
        $this->actingAs($teacher)->post(route('stagiaires.store'), $this->form())->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Traductions FR / AR
    // ──────────────────────────────────────────────────────────────────────────

    public function test_stage_section_is_translated_in_arabic_with_rtl(): void
    {
        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $card = $this->stageCardHtml($this->createPageHtml($this->superAdmin));

        $this->assertStringContainsString('معلومات التدريب', $card);
        $this->assertStringContainsString('تاريخ بدء التدريب', $card);
        $this->assertStringContainsString('تاريخ نهاية التدريب', $card);
        $this->assertStringContainsString('سعر التدريب (د.م.)', $card);

        $page = $this->createPageHtml($this->superAdmin);
        $this->assertStringContainsString('dir="rtl"', $page);

        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'fr'))->assertRedirect();
    }

    public function test_stage_error_messages_are_translated_in_both_locales(): void
    {
        $payload = $this->withoutField($this->form(), 'stage_start_date');

        // Français : message explicite sous le champ concerné.
        $fr = $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->followingRedirects()
            ->post(route('stagiaires.store'), $payload)
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('La date de début du stage est obligatoire.', $fr);

        // Arabe : même règle, libellé importé de lang/ar.json.
        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'ar'))->assertRedirect();

        $ar = $this->actingAs($this->superAdmin)
            ->from(route('stagiaires.create'))
            ->followingRedirects()
            ->post(route('stagiaires.store'), $this->withoutField($this->form(), 'stage_price'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('ثمن التدريب إلزامي.', $ar);

        $this->actingAs($this->superAdmin)->get(route('locale.switch', 'fr'))->assertRedirect();
    }
}
