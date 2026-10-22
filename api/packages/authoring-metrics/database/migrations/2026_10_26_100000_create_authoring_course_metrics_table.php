<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One snapshot per course (tenant database, ADR 0194): time, cost and element counts of how it was produced.
 * Recomputed from the recorded runs, calls and the applied blueprint; never edited by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authoring_course_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->primary();
            $table->ulid('session_id')->index();
            // wall time (seconds) of the runs that build and change the course
            $table->decimal('generation_seconds', 12, 3)->default(0); // outline, generate, apply, sync
            $table->decimal('ai_edit_seconds', 12, 3)->default(0); // patch (element chat edits)
            $table->decimal('interview_seconds', 12, 3)->default(0); // the author answering questions
            $table->decimal('model_seconds', 12, 3)->default(0); // summed model latency, for comparison with the wall time
            $table->decimal('author_minutes', 12, 1)->nullable(); // null: review time is not recorded (AM-5)
            $table->unsignedBigInteger('cost_build_micro_usd')->default(0);
            $table->unsignedBigInteger('cost_maintenance_micro_usd')->default(0);
            $table->unsignedInteger('llm_calls')->default(0);
            $table->unsignedInteger('llm_calls_failed')->default(0);
            $table->unsignedInteger('lessons')->default(0);
            $table->unsignedInteger('questions')->default(0);
            $table->unsignedInteger('blocks')->default(0);
            $table->unsignedInteger('cited_blocks')->default(0);
            $table->unsignedInteger('ai_elements')->default(0); // everything the AI produced: the four AI classes below plus unknown
            $table->unsignedInteger('ai_accepted_unchanged')->default(0);
            $table->unsignedInteger('ai_edited_by_author')->default(0);
            $table->unsignedInteger('ai_revised_on_request')->default(0);
            $table->unsignedInteger('ai_unknown')->default(0); // produced before element provenance was recorded: never guessed
            $table->unsignedInteger('author_elements')->default(0);
            $table->unsignedInteger('learner_minutes_estimate')->default(0);
            $table->unsignedSmallInteger('method_version');
            $table->timestamp('computed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authoring_course_metrics');
    }
};
