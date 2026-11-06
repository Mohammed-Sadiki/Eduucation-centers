<?php

namespace App\Http\Requests;

use App\Models\ClassRoom;
use App\Models\Formation;
use App\Support\AcademicPerimeter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ClassRoom::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $authUser = $this->user();

        // Détermination du centre cible
        $targetCenterId = $authUser->isCenterAdmin() ? $authUser->center_id : $this->input('center_id');

        return [
            'center_id' => [
                $authUser->isSuperAdmin() ? 'required' : 'nullable',
                'exists:centers,id',
            ],
            'formation_id' => [
                'required',
                'integer',
                'exists:formations,id',
                function ($attribute, $value, $fail) use ($targetCenterId) {
                    $formation = Formation::find($value);
                    if (! $formation) {
                        return;
                    }

                    if ($targetCenterId && $formation->center_id !== null && $formation->center_id != $targetCenterId) {
                        $fail(__('La formation sélectionnée n\'appartient pas au même centre que la classe.'));

                        return;
                    }

                    // Architecture obligatoire : une classe est rattachée à une
                    // formation générale, jamais à une ancienne sous-formation.
                    if (! AcademicPerimeter::isCurrentFormation($formation)) {
                        $fail(__('La formation sélectionnée n\'est plus proposée. Choisissez la formation générale équivalente.'));
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('classes', 'code')->where(function ($query) use ($targetCenterId) {
                    return $query->where('center_id', $targetCenterId);
                }),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
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
            'formation_id' => __('formation associée'),
            'name' => __('intitulé de la classe'),
            'code' => __('code de la classe'),
            'description' => __('description'),
            'capacity' => __('capacité d’accueil'),
            'start_date' => __('date de début'),
            'end_date' => __('date de fin'),
            'status' => __('statut'),
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('Le code de classe est déjà utilisé au sein de ce centre.'),
            'capacity.min' => __('La capacité de la classe doit être strictement supérieure à 0.'),
            'end_date.after_or_equal' => __('La date de fin doit être postérieure ou égale à la date de début.'),
        ];
    }
}
