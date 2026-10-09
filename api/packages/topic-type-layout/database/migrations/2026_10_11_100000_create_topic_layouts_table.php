<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Topic type Layout (ADR 0052): a document of approved learner components plus a Markdown fallback.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('topic_layouts', function (Blueprint $table) {
            $table->id();
            $table->json('document');
            $table->string('schema_version', 16)->default('1');
            $table->longText('markdown_fallback');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_layouts');
    }
};
