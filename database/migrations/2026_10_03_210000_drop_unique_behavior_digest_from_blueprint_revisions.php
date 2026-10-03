<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blueprint_revisions', function (Blueprint $table): void {
            $table->dropUnique(['behavior_digest']);
        });
    }

    public function down(): void
    {
        Schema::table('blueprint_revisions', function (Blueprint $table): void {
            $table->unique('behavior_digest');
        });
    }
};
