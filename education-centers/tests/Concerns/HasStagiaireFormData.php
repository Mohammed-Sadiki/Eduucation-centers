<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Constructeur de données du formulaire stagiaire.
 *
 * Le formulaire « Ajouter un stagiaire » exige désormais toutes ses informations
 * personnelles (dont WhatsApp, téléphone d'urgence, photos CIN et photo de profil),
 * les trois champs de l'entreprise de stage ainsi que la section « Informations du
 * stage » (dates de début/fin et prix). Les autres champs hérités de l'ancien
 * formulaire (email, adresse, type de stage, notes…) restent acceptés mais
 * facultatifs. Les tests partagent donc un jeu de données complet, qu'ils
 * dégradent volontairement pour vérifier un refus précis.
 */
trait HasStagiaireFormData
{
    /**
     * Jeu de données valide couvrant toutes les sections obligatoires.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function stagiairePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Karim',
            'last_name' => 'Benani',
            'date_of_birth' => '1998-05-14',
            'nationality' => 'Marocaine',
            'phone' => '+212612345678',
            'whatsapp' => '+212612345679',
            'emergency_contact_phone' => '+212522334455',
            'company_name' => 'Atlas Technologies',
            'company_phone' => '+212527000000',
            'company_location' => 'Casablanca, Maroc',
            // Section « Informations du stage » : trois champs obligatoires.
            'stage_start_date' => '2026-11-01',
            'stage_end_date' => '2027-04-30',
            'stage_price' => 1500.00,
            // Champs hérités, désormais facultatifs mais toujours acceptés.
            'place_of_birth' => 'Casablanca',
            'gender' => 'male',
            'email' => 'karim.benani@example.com',
            'address' => '12 Rue de Rivoli, Paris',
            'cin_number' => 'AB123456',
            'cin_issued_date' => '2020-01-15',
            'cin_issued_place' => 'Préfecture Paris',
            'stage_type' => "Stage de fin d'études",
            'currency' => 'EUR',
            'status' => 'nouveau',
            'notes' => 'Candidature spontanée acceptée.',
        ], $overrides);
    }

    /**
     * Les deux scans CIN exigés à la création.
     *
     * @return array<string, UploadedFile>
     */
    protected function cinFiles(): array
    {
        return [
            'cin_recto' => UploadedFile::fake()->create('cin_recto.pdf', 120, 'application/pdf'),
            'cin_verso' => UploadedFile::fake()->create('cin_verso.pdf', 120, 'application/pdf'),
        ];
    }

    /**
     * Photo de profil exigée à la création (et tant qu'aucune photo n'existe).
     */
    protected function profilePhotoFile(): UploadedFile
    {
        return UploadedFile::fake()->image('profil.jpg', 200, 200);
    }

    /**
     * Données du formulaire + pièces obligatoires : dossier complet accepté.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function stagiairePayloadWithCin(array $overrides = []): array
    {
        return $this->stagiairePayload($overrides)
            + $this->cinFiles()
            + ['profile_photo' => $this->profilePhotoFile()];
    }

    /**
     * Retire un champ obligatoire (ou le vide) pour provoquer un refus.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withoutField(array $payload, string $field): array
    {
        unset($payload[$field]);

        return $payload;
    }
}
