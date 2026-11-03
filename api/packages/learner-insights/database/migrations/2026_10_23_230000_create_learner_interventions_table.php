<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nudges (and later remediations) offered to a learner, with what came of them (ADR 0058, 0059). */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('learner_interventions')) {
            return;
        }
        Schema::create('learner_interventions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->string('element_key', 64)->nullable();
            $table->string('kind', 16); // nudge | remediation
            $table->string('ref_id', 40)->nullable();
            $table->string('outcome', 16); // pending | recovered | not_recovered | suppressed
            $table->string('detail', 40)->nullable(); // why a nudge was suppressed
            $table->unsignedSmallInteger('window_days')->default(14);
            $table->timestamp('created_at');
            $table->timestamp('resolved_at')->nullable();
            $table->index(['user_id', 'course_id', 'kind', 'created_at'], 'learner_interventions_user_course');
            $table->index(['course_id', 'kind', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_interventions');
    }
};
