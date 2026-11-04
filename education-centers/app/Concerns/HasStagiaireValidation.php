<?php

namespace App\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Règles de validation partagées par les formulaires « Ajouter un stagiaire »
 * et « Modifier un stagiaire ».
 *
 * Principe de la refonte : les cartes saisissables du formulaire couvrent trois
 * sections — « Informations personnelles », « Informations du stage » (période et
 * prix convenus) et « Entreprise où le stagiaire effectuera son stage » — et tous
 * les champs affichés sont obligatoires, à l'exception des « Autres fichiers »
 * (seule section facultative).
 *
 * Les informations administratives qui ne figurent toujours pas dans le
 * formulaire (lieu de naissance, sexe, e-mail, adresse, numéro CIN, type de
 * stage, devise, statut, notes, diplômes et documents existants) restent
 * acceptées par le serveur — leurs formats sont toujours contrôlés — mais ne sont
 * plus exigées : aucun dossier existant n'est mis en défaut et aucune valeur déjà
 * enregistrée n'est effacée.
 *
 * Les lignes facultatives « Diplômes obtenus » et « Autres documents » conservent
 * leur règle « tout ou rien » : une ligne vide est ignorée, une ligne commencée
 * doit être entièrement complétée.
 */
trait HasStagiaireValidation
{
    /**
     * Champs des cartes du formulaire : obligatoires et formatés.
     *
     * Les fichiers CIN ne figurent pas ici : leur caractère obligatoire diffère
     * entre la création (toujours exigés) et la modification (un scan déjà
     * enregistré peut être conservé).
     */
    protected function stagiaireFieldRules(): array
    {
        return [
            // 1. Informations personnelles
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'nationality' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'emergency_contact_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],

            // 2. Informations du stage (3 champs, exigés) : période et montant
            // convenus. La date de fin ne peut jamais précéder la date de début,
            // et le prix reste un montant positif ou nul (devise par défaut MAD).
            'stage_start_date' => ['required', 'date'],
            'stage_end_date' => ['required', 'date', 'after_or_equal:stage_start_date'],
            'stage_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],

            // 3. Entreprise où le stagiaire effectuera son stage (3 champs, exigés)
            'company_name' => ['required', 'string', 'max:255'],
            'company_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'company_location' => ['required', 'string', 'max:255'],

            // Rattachement : jamais choisi par un Admin Centre (résolu côté serveur).
            'center_id' => ['required', 'integer', 'exists:centers,id'],

            // 4. Téléversements du formulaire. La photo de profil fait partie des
            // informations personnelles exigées : son caractère obligatoire est
            // fixé par chaque requête (à la modification, une photo déjà
            // enregistrée peut être conservée). Les « Autres fichiers » restent
            // la seule section facultative.
            'other_files' => ['nullable', 'array', 'max:10'],
            'other_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:8192'],

            // Champs administratifs conservés en base mais absents du formulaire :
            // facultatifs, leur format reste vérifié dès qu'une valeur est envoyée.
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'cin_number' => ['nullable', 'string', 'max:50'],
            'cin_issued_date' => ['nullable', 'date'],
            'cin_issued_place' => ['nullable', 'string', 'max:255'],
            'stage_type' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', Rule::in(['nouveau', 'en_cours', 'termine', 'suspendu', 'annule'])],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Alias envoyés par d'anciens formulaires d'édition (birth_date,
            // cin_delivered_at…) : leur valeur est reversée dans les champs
            // canoniques ci-dessus par prepareForValidation().
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'cin_delivered_at' => ['nullable', 'date'],
            'cin_delivered_place' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Règles conditionnelles des lignes dynamiques (diplômes et documents
     * administratifs). Ces sections ne figurent plus dans le formulaire refondu
     * mais restent acceptées par le serveur : les dossiers et les pièces
     * existants conservent leur mode d'alimentation.
     *
     * @return array<string, array>
     */
    protected function attachmentRowRules(): array
    {
        $rules = [
            'diplomas' => ['nullable', 'array'],
            'diplomas.*' => ['array'],
            'other_documents' => ['nullable', 'array'],
            'other_documents.*' => ['array'],
        ];

        foreach ($this->dynamicRows('diplomas') as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if ($this->rowIsFilled($row, ['title', 'institution', 'year'], "diplomas.{$index}.file")) {
                $rules["diplomas.{$index}.title"] = ['required', 'string', 'max:255'];
                $rules["diplomas.{$index}.institution"] = ['required', 'string', 'max:255'];
                $rules["diplomas.{$index}.year"] = ['required', 'integer', 'min:1900', 'max:2100'];
                $rules["diplomas.{$index}.file"] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
            }
        }

        foreach ($this->dynamicRows('other_documents') as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            // Le « type de pièce » est présélectionné dans le formulaire : lui seul
            // ne doit pas transformer une ligne vide en document ajouté.
            if ($this->rowIsFilled($row, ['title'], "other_documents.{$index}.file")) {
                $rules["other_documents.{$index}.title"] = ['required', 'string', 'max:255'];
                $rules["other_documents.{$index}.type"] = ['required', 'string',
                    Rule::in(['convention', 'attestation', 'certificat', 'cv', 'autre'])];
                $rules["other_documents.{$index}.file"] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
            }
        }

        return $rules;
    }

    /**
     * Libellés des champs utilisés dans les messages d'erreur du formulaire.
     */
    protected function stagiaireAttributes(): array
    {
        return [
            'first_name' => __('prénom'),
            'last_name' => __('nom'),
            'center_id' => __('centre'),
            'date_of_birth' => __('date de naissance'),
            'nationality' => __('nationalité'),
            'phone' => __('téléphone'),
            'whatsapp' => __('numéro WhatsApp'),
            'emergency_contact_phone' => __('téléphone d\'urgence'),
            'profile_photo' => __('photo de profil'),
            'other_files' => __('autres fichiers'),
            'other_files.*' => __('autre fichier'),
            'company_name' => __('nom de l\'entreprise'),
            'company_phone' => __('téléphone de l\'entreprise'),
            'company_location' => __('lieu de l\'entreprise'),
            'place_of_birth' => __('lieu de naissance'),
            'gender' => __('sexe'),
            'address' => __('adresse'),
            'email' => __('adresse e-mail'),
            'cin_number' => __('numéro CIN'),
            'cin_issued_date' => __('date de délivrance CIN'),
            'cin_issued_place' => __('lieu de délivrance CIN'),
            'cin_recto' => __('CIN recto'),
            'cin_verso' => __('CIN verso'),
            'stage_start_date' => __('date de début du stage'),
            'stage_end_date' => __('date de fin du stage'),
            'stage_type' => __('type de stage'),
            'stage_price' => __('prix du stage'),
            'currency' => __('devise'),
            'status' => __('statut'),
            'notes' => __('notes'),
            'diplomas.*.title' => __('nom du diplôme'),
            'diplomas.*.institution' => __('établissement du diplôme'),
            'diplomas.*.year' => __('année d\'obtention du diplôme'),
            'diplomas.*.file' => __('fichier du diplôme'),
            'other_documents.*.title' => __('titre du document'),
            'other_documents.*.type' => __('type de pièce'),
            'other_documents.*.file' => __('fichier du document'),
        ];
    }

    /**
     * Messages explicites pour les sections facultatives.
     */
    protected function stagiaireMessages(): array
    {
        return [
            'stage_start_date.required' => __('La date de début du stage est obligatoire.'),
            'stage_start_date.date' => __('La date de début du stage doit être une date valide.'),
            'stage_end_date.required' => __('La date de fin du stage est obligatoire.'),
            'stage_end_date.after_or_equal' => __('La date de fin du stage doit être postérieure ou égale à la date de début.'),
            'stage_price.required' => __('Le prix du stage est obligatoire.'),
            'stage_price.numeric' => __('Le prix du stage doit être un montant numérique valide.'),
            'stage_price.min' => __('Le prix du stage doit être supérieur ou égal à 0.'),
            'phone.regex' => __('Le numéro de téléphone est invalide.'),
            'whatsapp.regex' => __('Le numéro WhatsApp est invalide.'),
            'emergency_contact_phone.regex' => __('Le numéro de téléphone d\'urgence est invalide.'),
            'company_phone.regex' => __('Le numéro de téléphone de l\'entreprise est invalide.'),
            'company_name.required' => __('Le nom de l\'entreprise de stage est obligatoire.'),
            'company_location.required' => __('Le lieu de l\'entreprise de stage est obligatoire.'),
            'profile_photo.image' => __('La photo de profil doit être une image (JPG ou PNG).'),
            'profile_photo.max' => __('La photo de profil ne doit pas dépasser 5 Mo.'),
            'other_files.*.mimes' => __('Les fichiers joints doivent être au format PDF, JPG, PNG, DOC ou DOCX.'),
            'other_files.*.max' => __('Chaque fichier joint doit peser au maximum 8 Mo.'),
            'diplomas.*.file.required' => __('Ce diplôme nécessite son fichier scanné (PDF ou image).'),
            'diplomas.*.title.required' => __('Le nom du diplôme est obligatoire lorsque la ligne est utilisée.'),
            'diplomas.*.institution.required' => __('L\'établissement est obligatoire lorsque la ligne est utilisée.'),
            'diplomas.*.year.required' => __('L\'année d\'obtention est obligatoire lorsque la ligne est utilisée.'),
            'other_documents.*.file.required' => __('Ce document nécessite son fichier scanné (PDF ou image).'),
            'other_documents.*.title.required' => __('Le titre / la description est obligatoire lorsque la ligne est utilisée.'),
            'other_documents.*.type.required' => __('Le type de pièce est obligatoire lorsque la ligne est utilisée.'),
        ];
    }

    /**
     * Lignes réellement présentes dans la requête pour une section dynamique.
     */
    private function dynamicRows(string $key): array
    {
        $rows = $this->input($key);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Une ligne est considérée « ajoutée » dès qu'un de ses champs utiles est
     * renseigné ou qu'un fichier y a été téléversé. Les valeurs laissées à vide
     * par défaut (champ texte vide, type présélectionné non compté) ne la
     * rendent pas obligatoire.
     */
    private function rowIsFilled(array $row, array $textFields, string $filePath): bool
    {
        foreach ($textFields as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                return true;
            }
        }

        return $this->hasUpload($filePath);
    }

    /**
     * Un champ file de formulaire vide n'est pas téléversé : on ne le compte pas.
     */
    private function hasUpload(string $path): bool
    {
        $file = data_get($this->allFiles(), $path);

        return $file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }
}
