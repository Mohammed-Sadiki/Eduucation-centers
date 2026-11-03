<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Socle ADDITIF et REVERSIBLE de la refonte des formations.
 *
 * Objectif : introduire la notion de « formation générale officielle »
 * (Centre → Formation générale → Classe) SANS supprimer la couche
 * Domaine/formation-spécialité existante et SANS reparenter les données
 * historiques (classes, inscriptions, paiements…). L'opération est strictement
 * additive : on ajoute des colonnes nullables/par défaut et une table de
 * correspondance ; les `down()` respectifs annulent exactement ces ajouts.
 *
 * Compatible MySQL/MariaDB (dev) et SQLite (suite de tests) : les contraintes
 * de clés étrangères sur la table existante `formations` ne peuvent être ajoutées
 * nativement par SQLite via ALTER ; elles sont donc appliquées uniquement sur
 * MySQL. La cohérence référentielle reste garantie par les modèles côté SQLite.
 */
return new class extends Migration
{
    private const UNIQUE_OFFICIAL_INDEX = 'formations_center_official_unique';

    private const IS_OFFICIAL_INDEX = 'formations_is_official_index';

    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table): void {
            if (! Schema::hasColumn('formations', 'is_official')) {
                $table->boolean('is_official')->default(false)->after('status');
            }

            if (! Schema::hasColumn('formations', 'official_code')) {
                $table->string('official_code')->nullable()->after('is_official');
            }

            if (! Schema::hasColumn('formations', 'teacher_id')) {
                $table->unsignedBigInteger('teacher_id')->nullable()->after('official_code');
            }

            if (! Schema::hasColumn('formations', 'merged_into_id')) {
                $table->unsignedBigInteger('merged_into_id')->nullable()->after('teacher_id');
            }
        });

        // Unicité : une seule formation officielle par code et par centre.
        // Les NULL (formations héritées) restent multiples (MySQL & SQLite).
        if (! $this->indexExists('formations', self::UNIQUE_OFFICIAL_INDEX)) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->unique(['center_id', 'official_code'], self::UNIQUE_OFFICIAL_INDEX);
            });
        }

        if (! $this->indexExists('formations', self::IS_OFFICIAL_INDEX)) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->index('is_official', self::IS_OFFICIAL_INDEX);
            });
        }

        // Clés étrangères : MySQL uniquement (SQLite ne sait pas les ajouter a posteriori).
        if ($this->isMysql()) {
            $this->addForeignKeyIfMissing('formations', 'teacher_id', 'teachers', 'id', 'nullOnDelete');
            $this->addForeignKeyIfMissing('formations', 'merged_into_id', 'formations', 'id', 'nullOnDelete');
        }

        // Table de correspondance (historique) : ancienne formation → formation officielle.
        if (! Schema::hasTable('formation_lineage')) {
            Schema::create('formation_lineage', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('official_formation_id')->nullable()
                    ->constrained('formations')->nullOnDelete();
                $table->foreignId('legacy_formation_id')->nullable()
                    ->constrained('formations')->nullOnDelete();
                $table->timestamps();

                $table->unique('legacy_formation_id');
                $table->index('official_formation_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_lineage');

        if ($this->indexExists('formations', self::UNIQUE_OFFICIAL_INDEX)) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE_OFFICIAL_INDEX);
            });
        }

        if ($this->indexExists('formations', self::IS_OFFICIAL_INDEX)) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->dropIndex(self::IS_OFFICIAL_INDEX);
            });
        }

        if ($this->isMysql()) {
            $this->dropForeignKeyIfPresent('formations', 'formations_teacher_id_foreign');
            $this->dropForeignKeyIfPresent('formations', 'formations_merged_into_id_foreign');
        }

        Schema::table('formations', function (Blueprint $table): void {
            foreach (['merged_into_id', 'teacher_id', 'official_code', 'is_official'] as $column) {
                if (Schema::hasColumn('formations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * Ajoute une clé étrangère si elle n'existe pas déjà (MySQL, idempotence).
     */
    private function addForeignKeyIfMissing(string $table, string $column, string $references, string $referencedColumn, string $onDelete): void
    {
        $fkName = "{$table}_{$column}_foreign";

        $existing = collect(Schema::getForeignKeys($table))->pluck('name')->all();

        if (in_array($fkName, $existing, true)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $referencedColumn, $onDelete, $fkName): void {
            $blueprint->foreign($column)
                ->references($referencedColumn)
                ->on($references)
                ->{$onDelete}()
                ->name($fkName);
        });
    }

    private function dropForeignKeyIfPresent(string $table, string $fkName): void
    {
        $existing = collect(Schema::getForeignKeys($table))->pluck('name')->all();

        if (! in_array($fkName, $existing, true)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($fkName): void {
            $blueprint->dropForeign($fkName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
