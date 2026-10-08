<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeFieldToTopicGiftQuestionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->string('type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_gift_questions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}
