<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Topic type Interactive: a topic plays a package (pinned to a version, or the current one) between
 * two steps, as an inline frame or as the page background.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('topic_interactives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('value')->constrained('interactive_packages')->restrictOnDelete();
            // the pinned version; set from the current one when the topic is saved unless follow_latest
            $table->unsignedInteger('version')->nullable();
            $table->boolean('follow_latest')->default(false);
            $table->string('start_step', 64)->nullable();
            $table->string('end_step', 64)->nullable();
            $table->string('completion_rule', 16)->default('on_range_end');
            $table->unsignedTinyInteger('pass_score')->nullable();
            $table->string('display', 16)->default('inline');
            $table->unsignedSmallInteger('height')->default(640);
            $table->longText('text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_interactives');
    }
};
