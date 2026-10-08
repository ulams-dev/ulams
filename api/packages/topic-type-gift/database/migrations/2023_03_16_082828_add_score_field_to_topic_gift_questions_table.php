<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScoreFieldToTopicGiftQuestionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->integer('score')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->dropColumn('score');
        });
    }
}
