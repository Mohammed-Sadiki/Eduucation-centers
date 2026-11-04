<?php

namespace App\Http\Requests;

use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Student;
use App\Support\AcademicPerimeter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin() || $this->user()->isCenterAdmin();
    }

    public function rules(): array
    {
        $rules = [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'formation_id' => ['required', 'integer', 'exists:formations,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'application_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->user()->isSuperAdmin()) {
            $rules['center_id'] = ['required', 'integer', 'exists:centers,id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'student_id.required' => __('L\'étudiant est obligatoire.'),
            'student_id.exists' => __('L\'étudiant sélectionné est introuvable.'),
            'formation_id.required' => __('La formation est obligatoire.'),
            'formation_id.exists' => __('La formation sélectionnée est introuvable.'),
            'class_id.exists' => __('La classe sélectionnée est introuvable.'),
            'application_date.required' => __('La date de candidature est obligatoire.'),
            'application_date.date' => __('La date de candidature doit être une date valide.'),
            'center_id.required' => __('Le centre est obligatoire pour le Super Admin.'),
            'center_id.exists' => __('Le centre sélectionné est introuvable.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $targetCenterId = $user->isCenterAdmin() ? (int) $user->center_id : (int) $this->input('center_id');

            $student = Student::find($this->input('student_id'));
            $formation = Formation::find($this->input('formation_id'));
            $classId = $this->input('class_id');
            $class = $classId ? ClassRoom::find($classId) : null;

            // 1. Contrôle d'isolation de centre : Étudiant et Formation doivent appartenir au centre cible
            if ($student && $student->center_id !== $targetCenterId) {
                $validator->errors()->add('student_id', __('L\'étudiant sélectionné n\'appartient pas à ce centre.'));
            }

            if ($formation && $formation->center_id !== $targetCenterId) {
                $validator->errors()->add('formation_id', __('La formation sélectionnée n\'appartient pas à ce centre.'));
            }

            // 1 bis. Architecture obligatoire : une inscription ne peut viser
            // qu'une formation générale. Une ancienne sous-formation n'est plus
            // une option, même envoyée par requête directe.
            if ($formation && $formation->center_id === $targetCenterId
                && ! AcademicPerimeter::isCurrentFormation($formation)) {
                $validator->errors()->add('formation_id', __('La formation sélectionnée n\'est plus proposée. Choisissez la formation générale équivalente.'));
            }

            // 2. Contrôle de la classe si renseignée
            if ($class) {
                if ($class->center_id !== $targetCenterId) {
                    $validator->errors()->add('class_id', __('La classe sélectionnée n\'appartient pas à ce centre.'));
                }

                // Classe rattachée à la formation choisie : appréciée par
                // formation générale (alias historiques compris).
                if ($formation && ! $validator->errors()->has('class_id')
                    && ! AcademicPerimeter::sameGeneralFormation($class->formation_id, $formation->id)) {
                    $validator->errors()->add('class_id', __('La classe sélectionnée ne fait pas partie de la formation choisie.'));
                }

                if (! $class->isActive()) {
                    $validator->errors()->add('class_id', __('La classe sélectionnée est inactive.'));
                }

                // Vérification de capacité si classe renseignée
                if ($class->capacity !== null) {
                    $acceptedCount = Enrollment::where('class_id', $class->id)
                        ->where('status', Enrollment::STATUS_ACCEPTED)
                        ->count();

                    if ($acceptedCount >= $class->capacity) {
                        $validator->errors()->add(
                            'class_id',
                            __('La classe :name a atteint sa capacité maximale (:acceptedCount/:capacity places).', ['name' => $class->perimeter_label, 'acceptedCount' => $acceptedCount, 'capacity' => $class->capacity])
                        );
                    }
                }
            }

            // 3. Statuts actifs
            if ($student && ! $student->isActive()) {
                $validator->errors()->add('student_id', __('L\'étudiant sélectionné est inactif.'));
            }

            if ($formation && ! $formation->isActive()) {
                $validator->errors()->add('formation_id', __('La formation sélectionnée est inactive.'));
            }

            // 4. Détection des doublons d'inscriptions actives
            // Un étudiant ne peut pas avoir deux inscriptions actives (pending, under_review, accepted) pour la même formation
            if ($student && $formation) {
                $duplicateExists = Enrollment::where('student_id', $student->id)
                    ->whereIn('formation_id', AcademicPerimeter::equivalentIds($formation->id))
                    ->whereIn('status', [
                        Enrollment::STATUS_PENDING,
                        Enrollment::STATUS_UNDER_REVIEW,
                        Enrollment::STATUS_ACCEPTED,
                    ])
                    ->exists();

                if ($duplicateExists) {
                    $validator->errors()->add(
                        'student_id',
                        __('Cet étudiant possède déjà une inscription active ou en attente pour la formation :name.', ['name' => $formation->general_formation_name])
                    );
                }
            }
        });
    }
}
