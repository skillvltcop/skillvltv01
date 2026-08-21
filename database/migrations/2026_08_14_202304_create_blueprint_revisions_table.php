<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blueprint_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('blueprint_id')
                ->constrained('blueprints')
                ->cascadeOnDelete();

            $table->string('revision_number');

            $table->foreignUlid('parent_revision_id')
                ->nullable()
                ->constrained('blueprint_revisions')
                ->nullOnDelete();

            $table->string('behavior_digest', 71);

            $table->json('contracts');
            $table->json('logic');
            $table->json('outputs');
            $table->json('policies');

            $table->timestamps();

            $table->unique([
                'blueprint_id',
                'revision_number',
            ]);

            $table->unique('behavior_digest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprint_revisions');
    }
};
