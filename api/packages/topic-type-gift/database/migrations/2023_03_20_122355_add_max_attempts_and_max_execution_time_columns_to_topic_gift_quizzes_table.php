<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMaxAttemptsAndMaxExecutionTimeColumnsToTopicGiftQuizzesTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_quizzes', function (Blueprint $table) {
            $table->integer('max_attempts')->nullable();
            $table->integer('max_execution_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_quizzes', function (Blueprint $table) {
            $table->dropColumn(['max_attempts', 'max_execution_time']);
        });
    }
}
