<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluations', 'modality')) {
                $table->string('modality', 30)->default('in_person')->after('type');
                $table->index(['center_id', 'modality']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('evaluations', 'modality')) {
                $table->dropIndex(['center_id', 'modality']);
                $table->dropColumn('modality');
            }
        });
    }
};

