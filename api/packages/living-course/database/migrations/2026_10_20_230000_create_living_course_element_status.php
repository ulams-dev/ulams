<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staleness of course elements against their sources (plan 10.1). */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('living_course_element_status', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('session_id');
            $table->string('element_id', 32);
            // in_sync | pending | dismissed | source_removed
            $table->string('status', 16);
            $table->string('element_type', 16)->nullable();
            $table->string('label', 160)->nullable();
            $table->timestamp('since')->nullable();
            $table->ulid('proposal_id')->nullable();
            $table->json('fragment_ids')->nullable();
            $table->boolean('answer_check')->default(false);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['session_id', 'element_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('living_course_element_status');
    }
};
