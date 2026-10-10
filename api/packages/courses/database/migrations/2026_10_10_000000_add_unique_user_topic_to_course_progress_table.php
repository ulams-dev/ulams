<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two requests that build a learner's progress at the same time (the course list and the course
 * progress, fired together by the front) both saw "no row yet" and both inserted one. The duplicate
 * stayed incomplete next to the completed one, so the course was never seen as finished until a
 * later update happened to touch the other row. One row per learner and topic is now enforced.
 */
return new class extends Migration {
    public function up(): void
    {
        // keep one row per learner and topic: a completed one first, then the oldest
        $keeper = fn (string $alias) => "(select k.id from course_progress k where k.user_id = {$alias}.user_id"
            . " and k.topic_id = {$alias}.topic_id order by k.status desc, k.id asc limit 1)";

        DB::statement(
            'update course_user_attendances set course_progress_id = ' . $keeper('p')
            . ' from course_progress p where p.id = course_user_attendances.course_progress_id'
        );
        DB::statement('delete from course_progress where id <> ' . $keeper('course_progress'));

        Schema::table('course_progress', function (Blueprint $table) {
            $table->unique(['user_id', 'topic_id'], 'course_progress_user_topic_unique');
        });
    }

    public function down(): void
    {
        Schema::table('course_progress', function (Blueprint $table) {
            $table->dropUnique('course_progress_user_topic_unique');
        });
    }
};
