<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicGiftAttemptAnswersTable extends Migration
{
    public function up(): void
    {
        Schema::create('topic_gift_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_gift_quiz_attempt_id')->constrained('topic_gift_quiz_attempts')->cascadeOnDelete();
            $table->foreignId('topic_gift_question_id')->constrained('topic_gift_questions')->cascadeOnDelete();
            $table->json('answer');
            $table->string('feedback')->nullable();
            $table->double('score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_gift_attempt_answers');
    }
}
