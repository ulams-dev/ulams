<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicProjectsTable extends Migration
{
    public function up(): void
    {
        Schema::create('topic_projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->text('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_projects');
    }
}
