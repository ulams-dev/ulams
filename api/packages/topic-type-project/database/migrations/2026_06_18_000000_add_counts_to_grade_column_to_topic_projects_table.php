<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountsToGradeColumnToTopicProjectsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_projects', function (Blueprint $table) {
            $table->boolean('counts_to_grade')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('topic_projects', function (Blueprint $table) {
            $table->dropColumn('counts_to_grade');
        });
    }
}
