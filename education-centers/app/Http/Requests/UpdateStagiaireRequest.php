<?php

namespace App\Http\Requests;

use App\Concerns\HasStagiaireValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStagiaireRequest extends FormRequest
{
    use HasStagiaireValidation;

    public function authorize(): bool
    {
        $stagiaire = $this->route('stagiaire');

        return $stagiaire && $this->user()->can('update', $stagiaire);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('currency')) {
            $this->merge(['currency' => default_currency()]);
        }
        if ($this->filled('birth_date') && ! $this->filled('date_of_birth')) {
            $this->merge(['date_of_birth' => $this->input('birth_date')]);
        }
        if ($this->filled('birth_place') && ! $this->filled('place_of_birth')) {
            $this->merge(['place_of_birth' => $this->input('birth_place')]);
        }
        if ($this->filled('cin_delivered_at') && ! $this->filled('cin_issued_date')) {
            $this->merge(['cin_issued_date' => $this->input('cin_delivered_at')]);
        }
        if ($this->filled('cin_delivered_place') && ! $this->filled('cin_issued_place')) {
            $this->merge(['cin_issued_place' => $this->input('cin_delivered_place')]);
        }
        if ($this->input('gender') === 'homme') {
            $this->merge(['gender' => 'male']);
        } elseif ($this->input('gender') === 'femme') {
            $this->merge(['gender' => 'female']);
        } elseif ($this->input('gender') === 'autre') {
            $this->merge(['gender' => 'other']);
        }

        // Centre résolu côté serveur pour un Admin Centre : impossible de déplacer
        // un stagiaire vers un centre qui n'est pas le sien.
        if ($this->user()?->isCenterAdmin() || $this->user()?->isSecretary()) {
            $this->merge(['center_id' => $this->user()->center_id]);
        }
    }

    /**
     * Mêmes règles que la création. Les scans CIN déjà enregistrés peuvent être
     * conservés : un nouveau fichier n'est exigé que si aucun n'est présent.
     */
    public function rules(): array
    {
        $stagiaire = $this->route('stagiaire');

        return $this->stagiaireFieldRules() + [
            'cin_recto' => [
                Rule::requiredIf(fn () => ! $stagiaire || ! $stagiaire->cin_recto_path),
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
            ],
            'cin_verso' => [
                Rule::requiredIf(fn () => ! $stagiaire || ! $stagiaire->cin_verso_path),
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
            ],
            // La photo de profil est exigée sauf si une photo est déjà enregistrée
            // (préservation des dossiers existants lors de la modification).
            'profile_photo' => [
                Rule::requiredIf(fn () => ! $stagiaire || ! $stagiaire->profile_photo_path),
                'image', 'mimes:jpg,jpeg,png', 'max:5120',
            ],
        ] + $this->attachmentRowRules();
    }

    public function attributes(): array
    {
        return $this->stagiaireAttributes();
    }

    public function messages(): array
    {
        return $this->stagiaireMessages();
    }
}
