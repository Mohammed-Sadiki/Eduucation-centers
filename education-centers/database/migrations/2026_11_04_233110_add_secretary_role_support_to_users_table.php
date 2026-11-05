<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Le champ `role` sur la table `users` est un varchar(30) avec index (depuis 2026_10_11_160642_add_role_to_users_table).
     * Cette migration formalise l'introduction du rôle `secretary` sans modification de structure destructive ni perte de données.
     */
    public function up(): void
    {
        // Pas de modification structurelle requise car `role` est déjà une colonne string(30) indexée.
        // Les comptes existants (super_admin, center_admin, teacher, student) restent 100% intacts.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Aucun rollback destructif.
    }
};
