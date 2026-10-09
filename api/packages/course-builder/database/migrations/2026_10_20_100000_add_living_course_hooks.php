<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extension points used by the living-course package (Phase 3, ADR 0030). All columns are
 * nullable, so Phase 2 code and data are unaffected.
 *
 * - fragments.file_path: the file a fragment comes from (repositories, several web pages);
 * - versions.source_revisions: {sourceId: revisionId} the version was written from (null =
 *   built before Phase 3 / revision 1);
 * - entity_map.retired_at: set when the applier deactivated an entity instead of deleting it;
 * - entity_map.added_by_proposal_id: the update proposal that created the entity.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_builder_fragments', function (Blueprint $table) {
            $table->string('file_path', 512)->nullable();
        });
        Schema::table('course_builder_versions', function (Blueprint $table) {
            $table->json('source_revisions')->nullable();
        });
        Schema::table('course_builder_entity_map', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable();
            $table->ulid('added_by_proposal_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('course_builder_entity_map', function (Blueprint $table) {
            $table->dropColumn(['retired_at', 'added_by_proposal_id']);
        });
        Schema::table('course_builder_versions', function (Blueprint $table) {
            $table->dropColumn('source_revisions');
        });
        Schema::table('course_builder_fragments', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });
    }
};
