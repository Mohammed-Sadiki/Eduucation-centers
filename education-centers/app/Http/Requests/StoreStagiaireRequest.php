<?php

namespace App\Http\Requests;

use App\Concerns\HasStagiaireValidation;
use App\Models\Stagiaire;
use Illuminate\Foundation\Http\FormRequest;

class StoreStagiaireRequest extends FormRequest
{
    use HasStagiaireValidation;

    public function authorize(): bool
    {
        return $this->user()->can('create', Stagiaire::class);
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

        // Le centre d'un Admin Centre est résolu côté serveur : sa requête ne peut
        // jamais imposer un autre centre. Pour le Super Admin, le choix du formulaire
        // est conservé et devient obligatoire.
        if ($this->user()?->isCenterAdmin() || $this->user()?->isSecretary()) {
            $this->merge(['center_id' => $this->user()->center_id]);
        }
    }

    /**
     * Tout est obligatoire, à l'exception des sections « Diplômes obtenus » et
     * « Autres documents », dont les lignes vides sont ignorées.
     */
    public function rules(): array
    {
        return $this->stagiaireFieldRules() + [
            // À la création, les deux scans de la CIN et la photo de profil font
            // partie du dossier : obligatoires.
            'cin_recto' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'cin_verso' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
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
