<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWeightFieldToTopicGiftQuizzesTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_quizzes', function (Blueprint $table) {
            $table->unsignedInteger('weight')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_quizzes', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
}
