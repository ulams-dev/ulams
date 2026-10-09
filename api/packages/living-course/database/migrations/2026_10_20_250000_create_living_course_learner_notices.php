<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What learners are told after an accepted update (ADR 0033). Notices never change progress. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('living_course_learner_notices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('topic_id');
            // 0 when the notice is about the topic as a whole
            $table->unsignedBigInteger('gift_question_id')->default(0);
            // topic_updated | question_reattempt | topic_retired | course_extended
            $table->string('kind', 24);
            $table->ulid('proposal_id');
            $table->text('message')->nullable();
            // open | done | dismissed
            $table->string('status', 12)->default('open');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->unique(['user_id', 'kind', 'topic_id', 'gift_question_id', 'proposal_id'], 'lc_notice_unique');
            $table->index(['user_id', 'course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('living_course_learner_notices');
    }
};
