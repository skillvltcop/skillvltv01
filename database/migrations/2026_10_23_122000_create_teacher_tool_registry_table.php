<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_tool_registry', function (Blueprint $table): void {
            $table->string('slug')->primary();
            $table->string('namespace');
            $table->string('view');
            $table->boolean('is_enabled')->default(true)->index();
            // Keep the actor identifier for audit context without coupling registry
            // retention to the lifecycle of a user record.
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_tool_registry');
    }
};
