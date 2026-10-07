<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table): void {
            $table->dropForeign(['blueprint_id']);

            $table->foreign('blueprint_id')
                ->references('id')
                ->on('blueprints')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table): void {
            $table->dropForeign(['blueprint_id']);

            $table->foreign('blueprint_id')
                ->references('id')
                ->on('blueprints')
                ->cascadeOnDelete();
        });
    }
};
