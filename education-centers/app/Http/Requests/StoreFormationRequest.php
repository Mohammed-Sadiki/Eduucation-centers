<?php

namespace App\Http\Requests;

use App\Models\Formation;
use App\Support\DomainRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Formation::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $authUser = $this->user();

        // Détermination du center_id cible pour la contrainte d'unicité
        $targetCenterId = $authUser->isCenterAdmin() ? $authUser->center_id : $this->input('center_id');

        return [
            'center_id' => [
                $authUser->isSuperAdmin() ? 'required' : 'nullable',
                'exists:centers,id',
            ],
            // Domaine de rattachement : column métier réelle `domain` (slug),
            // obligatoire pour que la formation apparaisse dans le dashboard du
            // domaine concerné. Validé contre le catalogue (base ou repli canonique).
            'domain' => ['required', 'string', Rule::in(DomainRegistry::slugs())],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('formations', 'code')->where(function ($query) use ($targetCenterId) {
                    return $query->where('center_id', $targetCenterId);
                }),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'duration_weeks' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(['MAD', 'EUR', 'USD', 'GBP', 'CHF'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'center_id' => __('centre éducatif'),
            'domain' => __('formation générale'),
            'name' => __('intitulé de la formation'),
            'code' => __('code de la formation'),
            'description' => __('description du programme'),
            'duration_hours' => __('durée en heures'),
            'duration_weeks' => __('durée en semaines'),
            'price' => __('tarif'),
            'currency' => __('devise'),
            'status' => __('statut'),
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('Le code de formation est déjà utilisé au sein de ce centre.'),
        ];
    }
}
