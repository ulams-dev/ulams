<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategoryIdFieldToTopicGiftQuestionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->unsignedInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories');
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
    }
}
