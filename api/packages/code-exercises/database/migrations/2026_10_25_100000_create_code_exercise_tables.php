<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Code exercises (ADR 0170): the topic type (starter files, visible tests, limits) and the attempts
 * learners report from the browser runner. Practice attempts are self-reported by the learner's
 * browser, so they are stored with `verified = false`.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('topic_code_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('language', 16);
            $table->string('runtime', 16)->default('browser');
            $table->string('grading', 16)->default('practice');
            // [{path, content}]: the learner's starter files
            $table->json('files');
            // [{path, content}]: the tests the learner sees (hidden tests belong to the server runner, later)
            $table->json('visible_tests');
            $table->unsignedInteger('timeout_ms')->default(5000);
            $table->string('completion_rule', 16)->default('on_pass');
            $table->unsignedTinyInteger('pass_score')->default(100);
            $table->longText('text')->nullable();
            $table->timestamps();
        });

        Schema::create('code_exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('mode', 16)->default('practice');
            $table->boolean('verified')->default(false);
            $table->char('files_hash', 64)->nullable();
            $table->unsignedSmallInteger('passed');
            $table->unsignedSmallInteger('total');
            $table->unsignedTinyInteger('score');
            // [{name, status, message}], names and messages cut to short strings
            $table->json('results');
            $table->string('runner', 40)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'topic_id', 'attempt_no']);
            $table->index(['topic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_exercise_attempts');
        Schema::dropIfExists('topic_code_exercises');
    }
};
