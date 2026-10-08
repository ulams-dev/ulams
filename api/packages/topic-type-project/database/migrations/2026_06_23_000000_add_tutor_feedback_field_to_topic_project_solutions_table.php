<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTutorFeedbackFieldToTopicProjectSolutionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_project_solutions', function (Blueprint $table) {
            $table->text('tutor_feedback')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_project_solutions', function (Blueprint $table) {
            $table->dropColumn('tutor_feedback');
        });
    }
}
