<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous review time (ADR 0194, AM-5). No user id, no IP address, no user agent and no element id, by design:
 * the only link between two rows is a random per-page-load nonce that is never stored anywhere else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authoring_activity_minutes', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id');
            // the start of an active minute, UTC
            $table->timestamp('minute_start');
            // random per page load, kept only in the browser's memory
            $table->char('tab_nonce', 22);
            // studio | admin
            $table->string('surface', 8);
            $table->primary(['course_id', 'minute_start', 'tab_nonce', 'surface']);
            $table->index('minute_start');
        });

        Schema::create('authoring_course_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id');
            $table->date('day');
            $table->string('surface', 8);
            $table->unsignedInteger('author_minutes');
            $table->primary(['course_id', 'day', 'surface']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authoring_course_daily');
        Schema::dropIfExists('authoring_activity_minutes');
    }
};
