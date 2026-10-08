<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMaxScoreToTopicProjectsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_projects', function (Blueprint $table) {
            $table->double('max_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_projects', function (Blueprint $table) {
            $table->dropColumn('max_score');
        });
    }
}
