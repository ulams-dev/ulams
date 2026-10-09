<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fragment-level changes between two revisions (ADR 0031). */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('living_course_fragment_changes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('from_revision_id');
            $table->ulid('to_revision_id');
            // changed | moved | removed | added
            $table->string('kind', 16);
            $table->string('old_fragment_id', 16)->nullable();
            $table->string('new_fragment_id', 16)->nullable();
            // trivial | minor | substantive
            $table->string('magnitude', 16);
            $table->decimal('similarity', 4, 3)->default(0);
            $table->json('signals')->nullable();
            $table->json('word_diff')->nullable();
            $table->index('to_revision_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('living_course_fragment_changes');
    }
};
