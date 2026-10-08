<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicGiftQuestionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('topic_gift_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_gift_quiz_id')->constrained('topic_gift_quizzes')->cascadeOnDelete();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_gift_questions');
    }
}
