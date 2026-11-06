<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Services\FinanceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Student $student */
        $student = $this->route('student');

        return $student && $this->user()->can('update', $student);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Mêmes champs que la création (formulaire unique) ; les colonnes retirées du
     * parcours de création restent acceptées en nullable pour ne casser aucun appel
     * historique, sans jamais être exigées.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            // Colonnes absentes du formulaire refondu : conservées par compatibilité
            // (autres flux, appels existants), jamais requises.
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Le statut n'est plus un champ du formulaire : la bascule se fait par
            // la route students.toggle-status. Il reste accepté s'il est transmis.
            'status' => ['nullable', Rule::in(['active', 'inactive'])],

            // ── Pièces et fichiers : facultatifs, rien d'existant n'est supprimé ──
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'cin_files' => ['nullable', 'array', 'max:6'],
            'cin_files.*' => ['image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'other_files' => ['nullable', 'array', 'max:10'],
            'other_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:8192'],

            // ── Formation et finance (identiques à la création) ──────────────
            'formation_id' => ['nullable', 'integer', 'exists:formations,id'],
            'final_price' => ['nullable', 'numeric', 'min:0'],
            'payment_option' => ['nullable', 'in:avance,paye'],
            'avance_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Contrôles croisés : tarif officiel relu en base, bornes du prix final,
     * cloisonnement du centre, et protection des versements déjà enregistrés.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Student $student */
            $student = $this->route('student');

            // Le centre du dossier n'est jamais modifié : c'est lui qui fait foi.
            $centerId = (int) $student->center_id;

            $formation = $this->filled('formation_id')
                ? Formation::find($this->input('formation_id'))
                : null;

            $enrollment = $this->currentEnrollment();

            if ($formation) {
                // La formation déjà inscrite au dossier reste enregistrable telle
                // quelle, même si elle a quitté le catalogue (inactivée depuis) :
                // une simple mise à jour ne doit pas forcer sa modification.
                $isCurrentFormation = $enrollment
                    && (int) $enrollment->formation_id === (int) $formation->id;

                if (! $formation->isActive() && ! $isCurrentFormation) {
                    $validator->errors()->add('formation_id', __('La formation sélectionnée est inactive.'));
                }

                if ($formation->center_id !== null && (int) $formation->center_id !== $centerId) {
                    $validator->errors()->add('formation_id', __('La formation sélectionnée n\'appartient pas au centre du dossier.'));
                }

                // Historique financier protégé : une inscription déjà payée ne peut
                // pas être réaffectée à une autre formation (les versements
                // existants deviendraient incohérents).
                if ($enrollment
                    && (int) $enrollment->formation_id !== (int) $formation->id
                    && $this->paidOnEnrollment($enrollment) > 0.01) {
                    $validator->errors()->add('formation_id', __('Cette inscription comporte déjà des versements : sa formation ne peut pas être modifiée depuis le dossier.'));
                }

                // ── Prix final : le tarif officiel fait foi, relu en base ──
                $official = FinanceService::getFormationAnnualPrice($formation);

                if ($this->filled('final_price')) {
                    $finalPrice = $this->input('final_price');

                    if (! preg_match('/^\d+(\.\d{1,2})?$/', (string) $finalPrice)) {
                        $validator->errors()->add('final_price', __('Le prix final ne peut contenir que deux décimales au maximum.'));
                    } elseif ((float) $finalPrice > $official + 0.01) {
                        $validator->errors()->add('final_price', __('Le prix final ne peut pas dépasser le tarif officiel de la formation (:price).', [
                            'price' => format_price($official, 'MAD'),
                        ]));
                    }
                }
            }

            // ── Nouveau versement : uniquement si une option est réellement choisie ──
            if ($this->filled('payment_option')) {
                if (! $formation) {
                    $validator->errors()->add('formation_id', __('Une formation est requise pour enregistrer un paiement.'));

                    return;
                }

                // Base du solde : le prix qui sera réellement dû après la mise à
                // jour (prix final transmis et borné, sinon valeur déjà enregistrée).
                $payable = $enrollment
                    ? $enrollment->getPayableAmount()
                    : FinanceService::getFormationAnnualPrice($formation);

                if ($this->filled('final_price')) {
                    $reference = $enrollment
                        ? $enrollment->officialPriceReference()
                        : FinanceService::getFormationAnnualPrice($formation);

                    $payable = max(0.0, min((float) $this->input('final_price'), $reference));
                }

                $remaining = max(0.0, $payable - ($enrollment ? $this->paidOnEnrollment($enrollment) : 0.0));

                if ($remaining <= 0.01) {
                    $validator->errors()->add('payment_option', __('Cette inscription est déjà entièrement réglée : aucun nouveau versement n\'est nécessaire.'));

                    return;
                }

                if ($this->input('payment_option') === 'avance') {
                    $advance = $this->input('avance_amount');

                    if (! $this->filled('avance_amount')) {
                        $validator->errors()->add('avance_amount', __('Le montant de l\'avance est requis.'));
                    } elseif ((float) $advance > $remaining + 0.01) {
                        $validator->errors()->add('avance_amount', __('Le montant de l\'avance ne peut pas dépasser le reste à payer (:price).', [
                            'price' => format_price($remaining, 'MAD'),
                        ]));
                    }
                }
            }
        });
    }

    /**
     * Inscription de référence du dossier (la plus récente non annulée).
     */
    public function currentEnrollment(): ?Enrollment
    {
        /** @var Student $student */
        return $this->route('student')->currentEnrollment();
    }

    /**
     * Total déjà encaissé pour cette inscription (jamais modifié par la mise à jour).
     */
    public function paidOnEnrollment(Enrollment $enrollment): float
    {
        return $enrollment->paidAmount();
    }

    /**
     * Custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'first_name' => __('prénom'),
            'last_name' => __('nom'),
            'date_of_birth' => __('date de naissance'),
            'place_of_birth' => __('lieu de naissance'),
            'gender' => __('genre'),
            'nationality' => __('nationalité'),
            'address' => __('adresse'),
            'postal_code' => __('code postal'),
            'city' => __('ville'),
            'country' => __('pays'),
            'phone' => __('téléphone'),
            'whatsapp' => __('numéro WhatsApp'),
            'emergency_contact_name' => __('nom du contact d’urgence'),
            'emergency_contact_phone' => __('téléphone du contact d’urgence'),
            'emergency_contact_relation' => __('lien avec le contact d’urgence'),
            'notes' => __('remarques'),
            'status' => __('statut'),
            'profile_photo' => __('photo de profil'),
            'cin_files' => __('photos de la CIN'),
            'cin_files.*' => __('photo de la CIN'),
            'other_files' => __('autres fichiers'),
            'other_files.*' => __('fichier'),
            'formation_id' => __('formation'),
            'final_price' => __('prix final accordé'),
            'payment_option' => __('type de paiement'),
            'avance_amount' => __('montant de l\'avance'),
        ];
    }
}
