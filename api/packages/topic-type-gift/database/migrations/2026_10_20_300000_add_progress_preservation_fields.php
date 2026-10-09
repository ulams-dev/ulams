<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Living Course (ADR 0033): a content update must never change a past result.
 *
 * - topic_gift_quiz_attempts.max_score: the maximum score at the time the attempt started, so
 *   adding or removing questions later does not change the percentage of past attempts. Null on
 *   rows created before this migration (they keep the live sum until `gift:snapshot-max-scores`).
 * - topic_gift_questions.archived_at: a question with learner answers is archived instead of
 *   deleted; archived questions are excluded from new attempts and from the maximum score.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('topic_gift_quiz_attempts', function (Blueprint $table) {
            $table->double('max_score')->nullable();
        });
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
        Schema::table('topic_gift_quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('max_score');
        });
    }
};
