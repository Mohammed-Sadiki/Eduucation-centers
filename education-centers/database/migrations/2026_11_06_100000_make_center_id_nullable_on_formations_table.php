<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend center_id nullable sur la table formations.
 *
 * NULL signifie "Tous les centres" : la formation est visible dans
 * tous les centres et n'est rattachée à aucun centre spécifique.
 *
 * Impact sur les contraintes existantes :
 *  - L'index unique (center_id, code) reste valide : dans MySQL et MariaDB,
 *    les NULL sont toujours distincts dans un index unique, donc plusieurs
 *    formations "tous les centres" peuvent coexister.
 *  - La clé étrangère vers centers est supprimée (car NULL n'a pas de référent),
 *    puis recréée comme nullable avec onDelete('set null') pour gérer la
 *    suppression d'un centre sans perdre la formation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            // Supprimer la contrainte FK existante avant de modifier la colonne.
            // Le nom de la FK suit la convention Laravel : formations_center_id_foreign
            $table->dropForeign(['center_id']);

            // Rendre center_id nullable (NULL = Tous les centres).
            $table->unsignedBigInteger('center_id')->nullable()->change();

            // Recréer la FK : SET NULL si le centre est supprimé (la formation
            // devient alors "tous les centres" automatiquement).
            $table->foreign('center_id')
                ->references('id')
                ->on('centers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            // Supprimer la nouvelle FK nullable.
            $table->dropForeign(['center_id']);

            // Toute formation ayant center_id NULL doit recevoir un center_id
            // valide avant le rollback (sinon la contrainte NOT NULL échoue).
            // On utilise le premier centre disponible comme valeur de repli.
            $firstCenterId = \App\Models\Center::query()->orderBy('id')->value('id');
            if ($firstCenterId) {
                \Illuminate\Support\Facades\DB::table('formations')
                    ->whereNull('center_id')
                    ->update(['center_id' => $firstCenterId]);
            }

            // Remettre center_id NOT NULL.
            $table->unsignedBigInteger('center_id')->nullable(false)->change();

            // Recréer la FK originale (cascade delete).
            $table->foreign('center_id')
                ->references('id')
                ->on('centers')
                ->cascadeOnDelete();
        });
    }
};

