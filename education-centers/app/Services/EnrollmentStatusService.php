<?php

namespace App\Services;

use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Support\AcademicPerimeter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentStatusService
{
    /**
     * Matrice des transitions valides de statut :
     * pending       -> under_review, cancelled, rejected
     * under_review  -> accepted, rejected, cancelled
     * accepted      -> cancelled
     */
    protected array $allowedTransitions = [
        Enrollment::STATUS_PENDING => [
            Enrollment::STATUS_UNDER_REVIEW,
            Enrollment::STATUS_CANCELLED,
            Enrollment::STATUS_REJECTED,
        ],
        Enrollment::STATUS_UNDER_REVIEW => [
            Enrollment::STATUS_ACCEPTED,
            Enrollment::STATUS_REJECTED,
            Enrollment::STATUS_CANCELLED,
        ],
        Enrollment::STATUS_ACCEPTED => [
            Enrollment::STATUS_CANCELLED,
        ],
        Enrollment::STATUS_REJECTED => [],
        Enrollment::STATUS_CANCELLED => [],
    ];

    /**
     * Vérifie si une transition de statut est valide.
     */
    public function canTransition(string $currentStatus, string $newStatus): bool
    {
        if ($currentStatus === $newStatus) {
            return true;
        }

        return in_array($newStatus, $this->allowedTransitions[$currentStatus] ?? [], true);
    }

    /**
     * Effectue la transition de statut avec transaction, verrouillage pessimiste et dates automatiques.
     *
     * @throws ValidationException
     */
    public function changeStatus(
        Enrollment $enrollment,
        string $newStatus,
        ?string $decisionNotes = null,
        ?int $assignClassId = null
    ): Enrollment {
        if ($enrollment->status === $newStatus) {
            return $enrollment;
        }

        if (! $this->canTransition($enrollment->status, $newStatus)) {
            throw ValidationException::withMessages([
                'status' => __('La transition du statut \\":status_label\\" vers \\":newStatus\\" n\'est pas autorisée.', ['status_label' => $enrollment->status_label, 'newStatus' => $newStatus]),
            ]);
        }

        return DB::transaction(function () use ($enrollment, $newStatus, $decisionNotes, $assignClassId) {
            // Recharger l'inscription avec verrou pessimiste
            $lockedEnrollment = Enrollment::lockForUpdate()->find($enrollment->id);

            $targetClassId = $assignClassId ?? $lockedEnrollment->class_id;

            // Si passage à 'accepted' avec une classe, vérifier atomiquement la capacité
            if ($newStatus === Enrollment::STATUS_ACCEPTED && $targetClassId) {
                $class = ClassRoom::lockForUpdate()->find($targetClassId);

                if (! $class) {
                    throw ValidationException::withMessages([
                        'class_id' => __('La classe sélectionnée est introuvable.'),
                    ]);
                }

                if (! $class->isActive()) {
                    throw ValidationException::withMessages([
                        'class_id' => __('La classe ":name" est inactive.', ['name' => $class->perimeter_label]),
                    ]);
                }

                // Appartenance à la formation de l'inscription : appréciée par
                // formation générale, une ancienne sous-formation et sa formation
                // générale décrivant la même classe-mère.
                if (! AcademicPerimeter::sameGeneralFormation($class->formation_id, $lockedEnrollment->formation_id)) {
                    throw ValidationException::withMessages([
                        'class_id' => __('La classe \\":name\\" n\'appartient pas à la formation de cette inscription.', ['name' => $class->perimeter_label]),
                    ]);
                }

                if ($class->capacity !== null) {
                    // Compter les inscriptions déjà acceptées dans cette classe (hors celle-ci si elle l'était déjà)
                    $currentAcceptedCount = Enrollment::where('class_id', $class->id)
                        ->where('status', Enrollment::STATUS_ACCEPTED)
                        ->where('id', '!=', $lockedEnrollment->id)
                        ->lockForUpdate()
                        ->count();

                    if ($currentAcceptedCount >= $class->capacity) {
                        throw ValidationException::withMessages([
                            'class_id' => __('Impossible d\'accepter l\'inscription : la classe \\":name\\" est complète (:currentAcceptedCount/:capacity places).', ['name' => $class->perimeter_label, 'currentAcceptedCount' => $currentAcceptedCount, 'capacity' => $class->capacity]),
                        ]);
                    }
                }
            }

            // Gestion des dates automatiques
            $now = Carbon::now();
            $updateData = [
                'status' => $newStatus,
            ];

            if ($assignClassId !== null) {
                $updateData['class_id'] = $assignClassId;
            }

            if ($decisionNotes !== null) {
                $updateData['decision_notes'] = $decisionNotes;
            }

            if ($newStatus === Enrollment::STATUS_ACCEPTED) {
                $updateData['accepted_at'] = $now;
                $updateData['decision_date'] = $now->toDateString();
            } elseif ($newStatus === Enrollment::STATUS_REJECTED) {
                $updateData['decision_date'] = $now->toDateString();
            } elseif ($newStatus === Enrollment::STATUS_CANCELLED) {
                $updateData['cancelled_at'] = $now;
            }

            $lockedEnrollment->update($updateData);

            return $lockedEnrollment;
        });
    }
}
