<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_audit_events', function (Blueprint $table): void {
            $table->id();
            // Intentionally not a foreign key: audit evidence survives user deletion.
            $table->string('actor_user_id')->index();
            $table->string('action');
            $table->string('resource_type');
            $table->string('resource_id');
            $table->string('permission')->nullable();
            $table->text('reason');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_audit_events');
    }
};
