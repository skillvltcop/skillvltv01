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
        Schema::create('blueprints', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('canonical_name');
            $table->string('namespace');

            $table->string('owner_type');
            $table->string('owner_id');

            $table->string('lifecycle_status')
                ->default('draft');

            $table->ulid('current_revision_id')
                ->nullable();

            $table->timestamps();

            $table->unique(['namespace', 'canonical_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
