<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainButtonsSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected Center $centerBerlin;
    protected Center $centerMunich;
    protected User $superAdmin;
    protected User $adminBerlin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centerBerlin = Center::factory()->create([
            'name' => 'Centre Berlin',
            'code' => 'BER',
            'city' => 'Berlin',
        ]);

        $this->centerMunich = Center::factory()->create([
            'name' => 'Centre Munich',
            'code' => 'MUN',
            'city' => 'Munich',
        ]);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->adminBerlin = User::factory()->centerAdmin($this->centerBerlin->id)->create();
    }

    /**
     * TEST A : Sur la page d'accueil admin (admin.home), la carte Formations
     * expose le bouton « + Ajouter un élève » qui ouvre le formulaire
     * d'inscription élève (students.create) avec nom, CIN, formation et
     * paiement. Les boutons ne sont plus portés par la page Domaines.
     */
    public function test_button_add_student_opens_student_form_with_personal_info_and_payment(): void
    {
        // 1. Super Admin
        $responseSA = $this->actingAs($this->superAdmin)->get(route('admin.home'));
        $responseSA->assertOk();
        $responseSA->assertSee('id="btn-add-student-domaines"', false);
        $responseSA->assertSee(route('students.create'), false);

        // Accès direct à students.create
        $formSA = $this->actingAs($this->superAdmin)->get(route('students.create'));
        $formSA->assertOk();
        $formSA->assertSee('Ajouter un élève');
        $formSA->assertSee('Informations personnelles');
        $formSA->assertSee('Pièces et fichiers');
        $formSA->assertSee('Paiement');

        // 2. Admin Centre
        $responseCA = $this->actingAs($this->adminBerlin)->get(route('admin.home'));
        $responseCA->assertOk();
        $responseCA->assertSee('id="btn-add-student-ca-domaines"', false);
        $responseCA->assertSee(route('students.create'), false);

        $formCA = $this->actingAs($this->adminBerlin)->get(route('students.create'));
        $formCA->assertOk();
        $formCA->assertSee('Ajouter un élève');
        $formCA->assertSee('Informations personnelles');
        $formCA->assertSee('Pièces et fichiers');
        $formCA->assertSee('Paiement');
    }

    /**
     * TEST B : Sur la page d'accueil admin, la carte Formations expose deux
     * boutons aux destinations strictement distinctes :
     *  - « + Ajouter une formation » → sélecteur de configuration des six
     *    formations générales officielles (formations-generales.picker) ;
     *  - « Accéder aux formations »  → catalogue des six cartes (formations-
     *    generales.index). Aucun des deux n'expose de champ élève ou paiement.
     */
    public function test_button_add_formation_opens_catalog_formation_form_without_student_fields(): void
    {
        // 1. Super Admin : les deux boutons mènent à des pages différentes.
        $responseSA = $this->actingAs($this->superAdmin)->get(route('admin.home'));
        $responseSA->assertOk();
        $responseSA->assertSee('id="btn-add-formation-domaines"', false);
        $responseSA->assertSee(route('formations-generales.picker'), false);
        $responseSA->assertSee(route('formations-generales.index'), false);
        $responseSA->assertSee('Ajouter une formation');
        $responseSA->assertSee('Accéder aux formations');

        // Le sélecteur de configuration n'expose aucun champ élève ni paiement.
        $pickerSA = $this->actingAs($this->superAdmin)->get(route('formations-generales.picker'));
        $pickerSA->assertOk();
        $pickerSA->assertDontSee('Photos CIN');
        $pickerSA->assertDontSee('Numéro WhatsApp');
        $pickerSA->assertDontSee('Numéro d\'urgence');
        $pickerSA->assertDontSee('Informations personnelles');
        $pickerSA->assertDontSee('Paiement : Avance ou Payé');

        // Le catalogue des six cartes n'expose pas non plus de champ élève.
        $catalogSA = $this->actingAs($this->superAdmin)->get(route('formations-generales.index'));
        $catalogSA->assertOk();
        $catalogSA->assertDontSee('Photos CIN');
        $catalogSA->assertDontSee('Informations personnelles');

        // 2. Admin Centre : mêmes destinations distinctes.
        $responseCA = $this->actingAs($this->adminBerlin)->get(route('admin.home'));
        $responseCA->assertOk();
        $responseCA->assertSee('id="btn-add-formation-ca-domaines"', false);
        $responseCA->assertSee(route('formations-generales.picker'), false);
        $responseCA->assertSee(route('formations-generales.index'), false);

        $pickerCA = $this->actingAs($this->adminBerlin)->get(route('formations-generales.picker'));
        $pickerCA->assertOk();
        $pickerCA->assertDontSee('Photos CIN');
        $pickerCA->assertDontSee('Numéro WhatsApp');
        $pickerCA->assertDontSee('Numéro d\'urgence');
    }

    /**
     * TEST C : Enregistrement d'une formation dans le catalogue
     * Vérifie qu'elle apparaît dans le catalogue SANS créer d'élève, d'inscription ou de paiement.
     */
    public function test_formation_store_creates_catalog_entry_without_student_or_payment(): void
    {
        $initialStudents = Student::count();
        $initialPayments = Payment::count();

        $formationData = [
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'name' => 'Développeur Full Stack Web',
            'code' => 'FSW-2026',
            'description' => 'Formation intensive en développement web moderne.',
            'duration_hours' => 300,
            'duration_weeks' => 16,
            'price' => 12000,
            'currency' => 'MAD',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('formations.store'), $formationData);

        $response->assertRedirect();

        // 1. La formation existe bien
        $this->assertDatabaseHas('formations', [
            'name' => 'Développeur Full Stack Web',
            'code' => 'FSW-2026',
            'domain' => 'informatique',
            'center_id' => $this->centerBerlin->id,
        ]);

        // 2. Aucun élève ni paiement n'a été créé
        $this->assertEquals($initialStudents, Student::count(), 'Aucun dossier élève ne doit être créé par ce formulaire.');
        $this->assertEquals($initialPayments, Payment::count(), 'Aucun paiement ne doit être créé par ce formulaire.');
    }

    /**
     * TEST D : Non-régression — Le formulaire élève (students.create / students.store)
     * fonctionne toujours et crée l'élève + le paiement dans Gestion financière.
     */
    public function test_student_store_creates_student_and_payment(): void
    {
        $formation = Formation::factory()->create([
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'annual_price' => 5000,
            'price' => 5000,
        ]);

        $studentData = [
            'first_name' => 'Karim',
            'last_name' => 'Alami',
            'center_id' => $this->centerBerlin->id,
            'domain' => 'informatique',
            'formation_id' => $formation->id,
            'payment_option' => 'paye',
            'date_of_birth' => '2000-01-15',
            'nationality' => 'Marocaine',
            'phone' => '+212600112233',
            'whatsapp' => '+212600112233',
            'emergency_contact_phone' => '+212600112244',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('students.store'), $studentData);

        $response->assertRedirect();

        // Vérifier la création de l'élève
        $this->assertDatabaseHas('students', [
            'first_name' => 'Karim',
            'last_name' => 'Alami',
            'center_id' => $this->centerBerlin->id,
        ]);

        // Vérifier la création du paiement dans Gestion financière
        $this->assertDatabaseHas('payments', [
            'center_id' => $this->centerBerlin->id,
            'formation_id' => $formation->id,
            'amount' => 5000,
        ]);
    }
}
