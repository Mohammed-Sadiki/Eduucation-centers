<?php

namespace App\Http\Requests;

use App\Models\ClassRoom;
use App\Models\Room;
use App\Models\Teacher;
use App\Services\ScheduleConflictService;
use App\Support\AcademicPerimeter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schedule = $this->route('schedule');

        return $schedule && $this->user()->can('update', $schedule);
    }

    public function rules(): array
    {
        $rules = [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'title' => ['nullable', 'string', 'max:150'],
            'type' => ['required', 'string', 'in:course,exam,workshop,other'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:scheduled,cancelled,completed'],
        ];

        if ($this->user()->isSuperAdmin()) {
            $rules['center_id'] = ['required', 'integer', 'exists:centers,id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'class_id.required' => __('La classe est obligatoire.'),
            'class_id.exists' => __('La classe sélectionnée est introuvable.'),
            'teacher_id.required' => __('Le professeur est obligatoire.'),
            'teacher_id.exists' => __('Le professeur sélectionné est introuvable.'),
            'room_id.required' => __('La salle est obligatoire.'),
            'room_id.exists' => __('La salle sélectionnée est introuvable.'),
            'date.required' => __('La date de la séance est obligatoire.'),
            'date.date' => __('La date doit être valide.'),
            'start_time.required' => __('L\'heure de début est obligatoire.'),
            'start_time.date_format' => __('L\'heure de début doit être au format HH:MM.'),
            'end_time.required' => "L'heure de fin est obligatoire.",
            'end_time.date_format' => __('L\'heure de fin doit être au format HH:MM.'),
            'end_time.after' => __('L\'heure de fin doit être strictement postérieure à l\'heure de début.'),
            'type.in' => __('Le type de séance sélectionné est invalide.'),
            'status.in' => __('Le statut sélectionné est invalide.'),
            'center_id.required' => __('Le centre est obligatoire pour le Super Admin.'),
            'center_id.exists' => __('Le centre sélectionné est introuvable.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $schedule = $this->route('schedule');

            // Le centre cible : imposé au centre de la séance ou de l'admin
            $targetCenterId = $user->isCenterAdmin() ? (int) $user->center_id : (int) ($this->input('center_id') ?? $schedule->center_id);

            $class = ClassRoom::find($this->input('class_id'));
            $teacher = Teacher::find($this->input('teacher_id'));
            $room = Room::find($this->input('room_id'));

            // 1. Contrôle d'isolation de centre : Classe, Professeur et Salle doivent appartenir au même centre
            if ($class && $class->center_id !== $targetCenterId) {
                $validator->errors()->add('class_id', __('La classe sélectionnée ne fait pas partie de ce centre.'));
            }

            if ($teacher && $teacher->center_id !== $targetCenterId) {
                $validator->errors()->add('teacher_id', __('Le professeur sélectionné ne fait pas partie de ce centre.'));
            }

            if ($room && $room->center_id !== $targetCenterId) {
                $validator->errors()->add('room_id', __('La salle sélectionnée ne fait pas partie de ce centre.'));
            }

            // 2. Statuts actifs
            if ($class && ! $class->isActive()) {
                $validator->errors()->add('class_id', __('La classe sélectionnée est inactive.'));
            }

            if ($teacher && ! $teacher->isActive()) {
                $validator->errors()->add('teacher_id', __('Le professeur sélectionné est inactif.'));
            }

            if ($room && ! $room->isActive()) {
                $validator->errors()->add('room_id', __('La salle sélectionnée est inactive.'));
            }

            // 2 bis. Architecture obligatoire : la classe doit relever d'une
            // formation générale active du périmètre autorisé.
            if ($class && ! $validator->errors()->has('class_id')) {
                $perimeter = AcademicPerimeter::for($user, $targetCenterId);
                if (! $perimeter->allowsClass((int) $class->id)) {
                    $validator->errors()->add('class_id', __('La classe doit appartenir à une formation générale active de votre périmètre.'));
                }
            }

            // 3. Professeur affecté à la classe
            if ($class && $teacher && ! $class->hasTeacher($teacher)) {
                $validator->errors()->add(
                    'teacher_id',
                    __('Le professeur :full_name n\'est pas affecté à la classe :name.', ['full_name' => $teacher->full_name, 'name' => $class->perimeter_label])
                );
            }

            // 4. Détection des conflits d'horaires (en ignorant la séance courante)
            $date = $this->input('date');
            $startTime = $this->input('start_time');
            $endTime = $this->input('end_time');
            $status = $this->input('status');

            if ($date && $startTime && $endTime && $startTime < $endTime && $status !== 'cancelled' && $teacher && $room && $class) {
                $conflictService = app(ScheduleConflictService::class);
                $conflicts = $conflictService->checkConflicts(
                    $date,
                    $startTime,
                    $endTime,
                    (int) $teacher->id,
                    (int) $room->id,
                    (int) $class->id,
                    (int) $schedule->id // Ignore self
                );

                foreach ($conflicts as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }
}
