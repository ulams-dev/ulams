<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Critique results of the generate → critique → fix loop (ADR 0051): one row per critic and iteration. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_builder_critiques', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('session_id')->index();
            $table->ulid('version_id')->nullable()->index();
            $table->string('element_id', 40)->index();
            $table->string('critic', 24);
            $table->unsignedSmallInteger('iteration')->default(0);
            $table->string('verdict', 8);
            $table->json('issues')->nullable();
            $table->string('ai_call_id', 40)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_builder_critiques');
    }
};
