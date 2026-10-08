<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicGiftQuizzesTable extends Migration
{
    public function up(): void
    {
        Schema::create('topic_gift_quizzes', function (Blueprint $table) {
            $table->id();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_gift_quizzes');
    }
}
