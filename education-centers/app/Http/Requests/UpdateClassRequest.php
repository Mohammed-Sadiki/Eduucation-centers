<?php

namespace App\Http\Requests;

use App\Models\ClassRoom;
use App\Models\Formation;
use App\Support\AcademicPerimeter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var ClassRoom $class */
        $class = $this->route('class');

        return $class && $this->user()->can('update', $class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ClassRoom $class */
        $class = $this->route('class');
        $centerId = $class->center_id;

        return [
            'formation_id' => [
                'required',
                'integer',
                'exists:formations,id',
                function ($attribute, $value, $fail) use ($centerId) {
                    $formation = Formation::find($value);
                    if (! $formation) {
                        return;
                    }

                    if ($formation->center_id !== null && $formation->center_id != $centerId) {
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
                Rule::unique('classes', 'code')
                    ->where(function ($query) use ($centerId) {
                        return $query->where('center_id', $centerId);
                    })
                    ->ignore($class->id),
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
