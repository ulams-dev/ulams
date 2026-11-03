<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course releases (ADR 0192, CV-1): one row per successful apply of an approved version to the LMS course, the
 * readable timeline of what learners saw. The version history keeps every proposal, rejection and undo; a release is
 * written by the apply step only, in the transaction that sets `applied_version_id`.
 *
 * `variant_id` is reserved for course variants (CV-4); null is the base branch.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_builder_releases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('session_id')->index();
            $table->ulid('variant_id')->nullable();
            $table->unsignedInteger('number');
            $table->ulid('version_id');
            $table->ulid('previous_release_id')->nullable();
            $table->unsignedBigInteger('course_id')->index();
            $table->string('trigger', 16);
            $table->json('source_revisions')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->json('summary');
            $table->timestamp('released_at');
            $table->unique(['session_id', 'variant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_builder_releases');
    }
};
