<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course variants by level and role (ADR 0192, CV-4). A variant is a branch of one builder session: it shares the
 * session's sources and citations, has its own versions and entity map, and is applied as its own LMS course. The
 * session row stays the base branch, so there is no variant row for the base and nothing existing is rewritten.
 *
 * - `course_builder_versions.variant_id`: null for the base branch;
 * - `course_builder_entity_map.variant_id`: '' for the base branch (an empty string, not null, because a nullable
 *   column in a unique key does not deduplicate nulls on MySQL and PostgreSQL), part of the unique key.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_builder_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('session_id')->index();
            $table->string('key', 64);
            $table->string('label');
            $table->string('level', 16)->nullable();
            $table->string('role', 64)->nullable();
            $table->json('audience_brief');
            $table->json('plan')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->ulid('current_version_id')->nullable();
            $table->ulid('applied_version_id')->nullable();
            $table->ulid('based_on_version_id')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['session_id', 'key']);
        });

        Schema::table('course_builder_versions', function (Blueprint $table) {
            $table->ulid('variant_id')->nullable()->index();
        });

        Schema::table('course_builder_entity_map', function (Blueprint $table) {
            $table->dropUnique(['session_id', 'element_id', 'entity_type']);
            $table->string('variant_id', 26)->default('');
            $table->unique(['session_id', 'variant_id', 'element_id', 'entity_type'], 'cb_entity_map_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('course_builder_entity_map', function (Blueprint $table) {
            $table->dropUnique('cb_entity_map_branch_unique');
        });
        \Illuminate\Support\Facades\DB::table('course_builder_entity_map')->where('variant_id', '!=', '')->delete();
        Schema::table('course_builder_entity_map', function (Blueprint $table) {
            $table->dropColumn('variant_id');
            $table->unique(['session_id', 'element_id', 'entity_type']);
        });
        Schema::table('course_builder_versions', function (Blueprint $table) {
            $table->dropColumn('variant_id');
        });
        Schema::dropIfExists('course_builder_variants');
    }
};
