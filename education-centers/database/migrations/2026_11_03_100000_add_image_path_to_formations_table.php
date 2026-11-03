<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne `image_path` aux formations générales personnalisées.
 * Strictement additive : null pour toutes les formations existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('formations', 'image_path')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->string('image_path')->nullable()->after('teacher_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('formations', 'image_path')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->dropColumn('image_path');
            });
        }
    }
};
