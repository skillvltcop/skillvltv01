<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blueprint_revisions', function (Blueprint $table) {
            $table->unique(
                ['blueprint_id', 'id'],
                'blueprint_revisions_blueprint_id_id_unique',
            );
        });

        Schema::table('blueprints', function (Blueprint $table) {
            $table->foreign(
                ['id', 'current_revision_id'],
                'blueprints_current_revision_belongs_to_blueprint_fk',
            )
                ->references(['blueprint_id', 'id'])
                ->on('blueprint_revisions');
        });

        Schema::table('executions', function (Blueprint $table) {
            $table->foreign(
                ['blueprint_id', 'revision_id'],
                'executions_blueprint_revision_belongs_to_blueprint_fk',
            )
                ->references(['blueprint_id', 'id'])
                ->on('blueprint_revisions');
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->dropForeign(
                'executions_blueprint_revision_belongs_to_blueprint_fk',
            );
        });

        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropForeign(
                'blueprints_current_revision_belongs_to_blueprint_fk',
            );
        });

        Schema::table('blueprint_revisions', function (Blueprint $table) {
            $table->dropUnique(
                'blueprint_revisions_blueprint_id_id_unique',
            );
        });
    }
};