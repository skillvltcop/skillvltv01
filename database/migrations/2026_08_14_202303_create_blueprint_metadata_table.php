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
        Schema::create('blueprint_metadata', function (Blueprint $table) {
            $table->ulid('blueprint_id')->primary();

            $table->json('taxonomy');
            $table->json('documentation');
            $table->json('discovery')->nullable();
            $table->json('lifecycle_metadata');

            $table->timestamps();

            $table->foreign('blueprint_id')
                ->references('id')
                ->on('blueprints')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprint_metadata');
    }
};
