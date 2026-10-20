<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cached remediations (no personal data) and who was shown which (ADR 0059). Idempotent per table. */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('learner_remediations')) {
            Schema::create('learner_remediations', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('topic_id')->nullable();
                $table->char('session_id', 26);
                $table->char('element_id', 26);
                $table->char('applied_version_id', 26)->nullable();
                $table->char('pattern_key', 64);
                $table->string('kind', 20);
                $table->string('language', 10);
                $table->json('document')->nullable();
                $table->json('citations')->nullable();
                $table->string('status', 12); // generating | ready | failed | retired
                $table->json('flags')->nullable();
                $table->json('ai_call_ids')->nullable();
                $table->timestamp('created_at');
                $table->timestamp('generated_at')->nullable();
                $table->unique(['pattern_key', 'kind'], 'learner_remediations_pattern');
                $table->index(['course_id', 'topic_id', 'status']);
            });
        }

        if (!Schema::hasTable('learner_remediation_assignments')) {
            Schema::create('learner_remediation_assignments', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->char('remediation_id', 26);
                $table->string('element_key', 64);
                $table->json('reasons');
                $table->boolean('new_generation')->default(false);
                $table->timestamp('created_at');
                $table->timestamp('shown_at')->nullable();
                $table->string('feedback', 12)->nullable(); // helpful | not_helpful
                $table->timestamp('hidden_at')->nullable();
                $table->unique(['user_id', 'remediation_id'], 'learner_assignments_unique');
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_remediation_assignments');
        Schema::dropIfExists('learner_remediations');
    }
};
