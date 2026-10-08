<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTutorFeedbackFieldToTopicGiftQuizAttemptsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_quiz_attempts', function (Blueprint $table) {
            $table->text('tutor_feedback')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('tutor_feedback');
        });
    }
}
