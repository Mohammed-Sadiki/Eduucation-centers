<?php

namespace App\Services;

use App\Models\ClassRoom;
use App\Models\Formation;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Règles métier des formations générales officielles (Centre → Formation → Classe).
 *
 * Point clé : ces contraintes ne s'appliquent QU'AUX formations officielles
 * (`is_official`). Les formations héritées (non migrées) restent régies par
 * l'ancien comportement, conformément à la règle « ne pas imposer les nouvelles
 * contraintes aux anciennes données tant qu'elles ne sont pas migrées ».
 */
class OfficialFormationService
{
    /**
     * Crée une classe rattachée à une formation officielle en garantissant la
     * limite de 2 classes sous verrou de ligne (protection contre la concurrence :
     * deux créations simultanées ne peuvent pas dépasser le quota).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createClass(Formation $formation, array $attributes): ClassRoom
    {
        return DB::transaction(function () use ($formation, $attributes): ClassRoom {
            if ($formation->isOfficial()) {
                // Verrou optimiste de la ligne formation pendant le comptage/création.
                Formation::query()->whereKey($formation->id)->lockForUpdate()->first();

                $this->assertClassSlot($formation->fresh());
            }

            /** @var ClassRoom $class */
            $class = $formation->classes()->create($attributes);

            return $class;
        });
    }

    /**
     * Vérifie qu'un emplacement de classe reste disponible pour une formation
     * officielle. No-op pour une formation héritée.
     *
     * @throws ValidationException
     */
    public function assertClassSlot(Formation $formation): void
    {
        if (! $formation->isOfficial()) {
            return;
        }

        if ($formation->classesCount() >= Formation::MAX_CLASSES) {
            throw ValidationException::withMessages([
                'formation_id' => __('Une formation générale ne peut pas dépasser :max classes.', [
                    'max' => Formation::MAX_CLASSES,
                ]),
            ]);
        }
    }

    /**
     * Attache (ou remplace) le professeur responsable d'une formation officielle.
     * Un seul responsable maximum : la colonne unique remplace le précédent.
     * Le professeur doit appartenir au même centre que la formation.
     *
     * @throws ValidationException
     */
    public function assignResponsible(Formation $formation, Teacher $teacher): Formation
    {
        if (! $formation->isOfficial()) {
            throw ValidationException::withMessages([
                'teacher_id' => __('Le professeur responsable ne peut être assigné qu\'à une formation générale officielle.'),
            ]);
        }

        if ((int) $teacher->center_id !== (int) $formation->center_id) {
            throw ValidationException::withMessages([
                'teacher_id' => __('Ce professeur n\'appartient pas au centre de cette formation.'),
            ]);
        }

        $formation->teacher_id = $teacher->id;
        $formation->save();

        return $formation;
    }

    /**
     * Retire le professeur responsable (remplacement possible sans perte
     * d'historique : les liens classe↔professeur et les données existantes
     * ne sont pas touchés).
     */
    public function detachResponsible(Formation $formation): Formation
    {
        $formation->teacher_id = null;
        $formation->save();

        return $formation;
    }
}
