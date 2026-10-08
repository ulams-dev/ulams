<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMinPassScoreFieldToTopicGiftQuizzesTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_quizzes', function (Blueprint $table) {
            $table->double('min_pass_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_quizzes', function (Blueprint $table) {
            $table->dropColumn('min_pass_score');
        });
    }
}
