<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicGiftQuizAttemptsTable extends Migration
{
    public function up(): void
    {
        Schema::create('topic_gift_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->dateTime('started_at');
            $table->dateTime('end_at')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_gift_quiz_id')->constrained('topic_gift_quizzes')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_gift_quiz_attempts');
    }
}
