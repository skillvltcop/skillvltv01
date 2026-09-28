<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blueprint_revisions', function (Blueprint $table) {
            $table->dropForeign([
                'parent_revision_id',
            ]);

            $table->foreign(
                ['blueprint_id', 'parent_revision_id'],
                'blueprint_revisions_parent_belongs_to_blueprint_fk',
            )
                ->references(['blueprint_id', 'id'])
                ->on('blueprint_revisions');
        });
    }

    public function down(): void
    {
        Schema::table('blueprint_revisions', function (Blueprint $table) {
            $table->dropForeign(
                'blueprint_revisions_parent_belongs_to_blueprint_fk',
            );

            $table->foreign('parent_revision_id')
                ->references('id')
                ->on('blueprint_revisions')
                ->nullOnDelete();
        });
    }
};