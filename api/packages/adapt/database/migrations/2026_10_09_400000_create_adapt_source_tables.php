<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adapt Path B (spec 1.2, behind ADAPT_SOURCE_ENABLED): the Adapt JSON source of a course,
 * versioned, built into a SCORM package by the isolated build worker (ADR 0013).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('adapt_sources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('current_version')->default(0);
            $table->string('status', 16)->default('draft'); // draft | building | built | failed
            $table->unsignedInteger('built_version')->nullable();
            $table->unsignedBigInteger('scorm_id')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();
        });

        Schema::create('adapt_source_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adapt_source_id')->constrained('adapt_sources')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('source'); // course, config, contentObjects, articles, blocks, components
            $table->string('change_note', 500)->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['adapt_source_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adapt_source_versions');
        Schema::dropIfExists('adapt_sources');
    }
};
