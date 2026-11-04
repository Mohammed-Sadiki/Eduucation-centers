<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Uniformisation des champs téléphoniques dans les interfaces administratives.
 *
 * Mission : tous les placeholders de téléphone des formulaires admin (Super Admin
 * ET les quatre Admin Centres — Tanger, Essaouira, Agadir, Tétouane) doivent
 * afficher exactement « +212 XXXXXXXXX ». Aucune donnée n'est modifiée : le
 * placeholder est purement visuel, n'est jamais préenregistré et remplace les
 * anciens exemples allemands « +49 » ainsi que les exemples fictifs chiffrés.
 *
 * Les vues Blade sont PARTAGÉES entre les rôles : couvrir les fichiers + une
 * rendue par rôle suffit à prouver les cinq contextes (exigence §3).
 */
class PhonePlaceholderUniformityTest extends TestCase
{
    use RefreshDatabase;

    private const PLACEHOLDER = '+212 XXXXXXXXX';

    /** Vues administratives contenant des champs téléphoniques. */
    private function phoneViews(): array
    {
        return [
            // Formulaire élève unique : les pages « Ajouter un élève » et
            // « Modifier le dossier élève » partagent le même composant, qui porte
            // donc désormais le placeholder (plus de duplication FR/AR possible).
            'students/form (create + edit)' => 'resources/views/components/students/form/personal.blade.php',
            'super-admin/stagiaires/create' => 'resources/views/super-admin/stagiaires/create.blade.php',
            'super-admin/stagiaires/edit'   => 'resources/views/super-admin/stagiaires/edit.blade.php',
            'teachers/create'            => 'resources/views/teachers/create.blade.php',
            'teachers/edit'              => 'resources/views/teachers/edit.blade.php',
            'users/create'               => 'resources/views/users/create.blade.php',
            'users/edit'                 => 'resources/views/users/edit.blade.php',
            'centers/create'             => 'resources/views/centers/create.blade.php',
            'centers/edit'               => 'resources/views/centers/edit.blade.php',
        ];
    }

    // ─── Analyse statique des vues partagées ─────────────────────────────────

    public function test_every_admin_phone_view_uses_the_standard_placeholder(): void
    {
        foreach ($this->phoneViews() as $label => $relative) {
            $contents = file_get_contents(base_path($relative));

            $this->assertStringContainsString(
                'placeholder="' . self::PLACEHOLDER . '"',
                $contents,
                "La vue {$label} doit utiliser le placeholder « " . self::PLACEHOLDER . " »."
            );
        }
    }

    public function test_no_admin_phone_view_keeps_the_german_example(): void
    {
        foreach ($this->phoneViews() as $label => $relative) {
            $contents = file_get_contents(base_path($relative));
            $this->assertStringNotContainsString('+49', $contents, "La vue {$label} contient encore un exemple « +49 ».");
        }
    }

    /**
     * Le placeholder est visuel uniquement : il ne doit JAMAIS se trouver dans un
     * attribut value= / old(...) (ni donc être préenregistré ou prérempli).
     */
    public function test_placeholder_is_never_used_as_a_field_value(): void
    {
        foreach ($this->phoneViews() as $label => $relative) {
            $contents = file_get_contents(base_path($relative));

            // Le placeholder ne doit jamais se trouver dans un value="…" ni un old(…).
            $this->assertDoesNotMatchRegularExpression(
                '/value="[^"]*XXXXXXX/',
                $contents,
                "La vue {$label} préremplit un champ avec le placeholder."
            );
            $this->assertDoesNotMatchRegularExpression(
                "/old\([^)]*XXXXXXX/",
                $contents,
                "La vue {$label} préremplit un champ avec le placeholder."
            );
        }
    }

    // ─── Rendu réel : Super Admin ────────────────────────────────────────────

    public function test_super_admin_forms_render_the_standard_placeholder(): void
    {
        $super = User::factory()->superAdmin()->create();
        $center = Center::factory()->create();

        // Étudiants.
        $student = Student::factory()->forCenter($center)->create(['phone' => null]);
        $this->actingAs($super)->get(route('students.create'))->assertOk()->assertSee(self::PLACEHOLDER, false);
        $this->actingAs($super)->get(route('students.edit', $student))->assertOk()->assertSee(self::PLACEHOLDER, false);

        // Stagiaires.
        $this->actingAs($super)->get(route('stagiaires.create'))->assertOk()->assertSee(self::PLACEHOLDER, false);
        $stagiaire = Stagiaire::create([
            'first_name' => 'Karim', 'last_name' => 'Benali', 'center_id' => $center->id,
        ]);
        $this->actingAs($super)->get(route('stagiaires.edit', $stagiaire))->assertOk()->assertSee(self::PLACEHOLDER, false);

        // Professeurs / Utilisateurs / Centres (champs téléphoniques).
        $this->actingAs($super)->get(route('teachers.create'))->assertOk()->assertSee(self::PLACEHOLDER, false);
        $this->actingAs($super)->get(route('users.create'))->assertOk()->assertSee(self::PLACEHOLDER, false);
        $this->actingAs($super)->get(route('centers.create'))->assertOk()->assertSee(self::PLACEHOLDER, false);

        // Aucun ancien exemple allemand résiduel dans le HTML rendu.
        $html = $this->actingAs($super)->get(route('students.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('+49', $html);
    }

    /**
     * À la création, le champ est vide (pas de value=) : seul le placeholder apparaît.
     */
    public function test_create_field_is_empty_and_only_shows_placeholder(): void
    {
        $super = User::factory()->superAdmin()->create();

        $html = $this->actingAs($super)->get(route('students.create'))->assertOk()->getContent();

        // Le champ téléphone porte le placeholder mais aucune valeur préenregistrée.
        $this->assertMatchesRegularExpression(
            '/name="phone"[^>]*placeholder="\+212 XXXXXXXXX"/',
            $html
        );
        $this->assertStringNotContainsString('value="+212 XXXXXXXXX"', $html);
    }

    /**
     * À la modification, un numéro existant est conservé tel quel ; le placeholder
     * reste présent en tant qu'indication (et n'écrase jamais la valeur).
     */
    public function test_edit_preserves_existing_international_number(): void
    {
        $super = User::factory()->superAdmin()->create();
        $center = Center::factory()->create();
        $existing = '+33 6 12 34 56 78'; // numéro international déjà enregistré

        $student = Student::factory()->forCenter($center)->create(['phone' => $existing]);

        $html = $this->actingAs($super)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee($existing, false)     // la valeur réelle est affichée
            ->assertSee(self::PLACEHOLDER, false) // le placeholder reste dans l'attribut
            ->getContent();

        // La value= du téléphone est le numéro réel, jamais le placeholder.
        $this->assertStringContainsString('value="' . $existing . '"', $html);
        $this->assertStringNotContainsString('value="' . self::PLACEHOLDER . '"', $html);
    }

    // ─── Rendu réel : les quatre Admin Centres concernés ─────────────────────

    public static function centerProvider(): array
    {
        return [
            'Tanger'    => ['Tanger', 'TGR'],
            'Essaouira' => ['Essaouira', 'ESS'],
            'Agadir'    => ['Agadir', 'AGD'],
            'Tétouane'  => ['Tétouane', 'TET'],
        ];
    }

    #[DataProvider('centerProvider')]
    public function test_center_admin_forms_render_the_standard_placeholder(string $centerName, string $code): void
    {
        $center = Center::factory()->create(['name' => $centerName, 'code' => $code]);
        $admin = User::factory()->centerAdmin($center->id)->create([
            'email' => 'phone.' . strtolower($code) . '@test.com',
            'status' => 'active',
        ]);

        // Étudiants et stagiaires : formulaires partagés accessibles à l'Admin Centre.
        $this->actingAs($admin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSee(self::PLACEHOLDER, false);

        $this->actingAs($admin)
            ->get(route('stagiaires.create'))
            ->assertOk()
            ->assertSee(self::PLACEHOLDER, false);

        // Aucun ancien exemple allemand dans le rendu.
        $html = $this->actingAs($admin)->get(route('students.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('+49', $html);
    }
}
