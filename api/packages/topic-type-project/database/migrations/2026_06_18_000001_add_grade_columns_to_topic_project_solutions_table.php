<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGradeColumnsToTopicProjectSolutionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_project_solutions', function (Blueprint $table) {
            $table->double('score')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_project_solutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graded_by');
            $table->dropColumn(['score', 'graded_at']);
        });
    }
}
