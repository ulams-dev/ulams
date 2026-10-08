<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderColumnToTopicGiftQuestionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->integer('order')->unsigned()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
}
