<?php

namespace App\Policies;

use App\Models\Stagiaire;
use App\Models\User;

class StagiairePolicy
{
    /**
     * Interception avant toute vérification.
     * Le Super Admin a tous les droits sur les dossiers stagiaires.
     */
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Voir la liste des stagiaires.
     *
     * Super Admin : tous les centres autorisés.
     * Responsable Centre & Secrétaire : leur centre uniquement.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isCenterAdmin() || $authUser->isSecretary();
    }

    /**
     * Consulter un dossier stagiaire : uniquement un dossier de son centre.
     */
    public function view(User $authUser, Stagiaire $stagiaire): bool
    {
        return ($authUser->isCenterAdmin() || $authUser->isSecretary())
            && $authUser->center_id !== null
            && (int) $authUser->center_id === (int) $stagiaire->center_id;
    }

    /**
     * Créer un dossier stagiaire.
     * Super Admin et Secrétaire uniquement.
     * Admin Centre : strictement lecture seule (consultation uniquement).
     */
    public function create(User $authUser): bool
    {
        return $authUser->isSecretary() && $authUser->center_id !== null;
    }

    /**
     * Modifier un dossier stagiaire de son centre.
     * Super Admin et Secrétaire uniquement.
     * Admin Centre : strictement lecture seule (consultation uniquement).
     */
    public function update(User $authUser, Stagiaire $stagiaire): bool
    {
        return $authUser->isSecretary()
            && $authUser->center_id !== null
            && (int) $authUser->center_id === (int) $stagiaire->center_id;
    }

    /**
     * Supprimer un dossier stagiaire : opération réservée au Super Admin.
     * Admin Centre et Secrétaire : non autorisés.
     */
    public function delete(User $authUser, Stagiaire $stagiaire): bool
    {
        return false;
    }
}
