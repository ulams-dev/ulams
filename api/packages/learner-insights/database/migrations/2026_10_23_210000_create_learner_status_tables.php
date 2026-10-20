<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statuses, their transitions, per-course thresholds and cohort statistics (ADR 0058). Idempotent per
 * table. `learner_statuses.element_key` is the empty string for the course-level row, so the unique
 * index also holds for it (PostgreSQL treats NULLs as distinct).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('learner_statuses')) {
            Schema::create('learner_statuses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('course_id');
                $table->string('element_key', 64)->default('');
                $table->string('status', 16);
                $table->json('reasons');
                $table->string('scorer', 32);
                $table->string('scorer_version', 16);
                $table->timestamp('since');
                $table->timestamp('evaluated_at');
                $table->unique(['user_id', 'course_id', 'element_key'], 'learner_statuses_unique');
                $table->index(['course_id', 'status']);
            });
        }

        if (!Schema::hasTable('learner_status_transitions')) {
            Schema::create('learner_status_transitions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('course_id');
                $table->string('element_key', 64)->default('');
                $table->string('from', 16);
                $table->string('to', 16);
                $table->json('reasons');
                $table->timestamp('occurred_at');
                $table->index(['user_id', 'course_id', 'occurred_at'], 'learner_transitions_user_course');
                $table->index(['course_id', 'element_key', 'occurred_at'], 'learner_transitions_course_element');
            });
        }

        if (!Schema::hasTable('learner_insights_course_settings')) {
            Schema::create('learner_insights_course_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('course_id')->primary();
                $table->json('rules')->nullable();
                $table->json('switches')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('learner_insights_element_stats')) {
            Schema::create('learner_insights_element_stats', function (Blueprint $table) {
                $table->unsignedBigInteger('course_id');
                $table->string('element_key', 64);
                $table->double('median_seconds');
                $table->unsignedInteger('learners');
                $table->timestamp('computed_at');
                $table->primary(['course_id', 'element_key']);
            });
        }
    }

    public function down(): void
    {
        foreach (['learner_insights_element_stats', 'learner_insights_course_settings', 'learner_status_transitions', 'learner_statuses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
