<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddGradedAtFieldToTopicGiftAttemptAnswersTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_gift_attempt_answers', function (Blueprint $table) {
            $table->timestamp('graded_at')->nullable();
        });

        // Answers created before manual-grading tracking are treated as already graded,
        // so legacy data is not mistaken for "awaiting grading".
        DB::table('topic_gift_attempt_answers')->update(['graded_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('topic_gift_attempt_answers', function (Blueprint $table) {
            $table->dropColumn('graded_at');
        });
    }
}
