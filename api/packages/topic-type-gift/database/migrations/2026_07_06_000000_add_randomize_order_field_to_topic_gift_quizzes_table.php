<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRandomizeOrderFieldToTopicGiftQuizzesTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_quizzes', function (Blueprint $table) {
            $table->boolean('randomize_order')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_quizzes', function (Blueprint $table) {
            $table->dropColumn('randomize_order');
        });
    }
}
