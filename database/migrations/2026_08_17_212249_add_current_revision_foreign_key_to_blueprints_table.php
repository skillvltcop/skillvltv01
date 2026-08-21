<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->foreign('current_revision_id')
                ->references('id')
                ->on('blueprint_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropForeign([
                'current_revision_id',
            ]);
        });
    }
};