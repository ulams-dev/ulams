<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time per Course Builder pipeline stage (tenant database, ADR 0194). Unlike the AG-UI events it is
 * derived from, these rows are not pruned: they live as long as the session they belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authoring_stage_timings', function (Blueprint $table) {
            $table->id();
            $table->ulid('session_id')->index();
            $table->ulid('run_id');
            // outline | generate | apply | ingest | interview | patch | action | sync | variant
            $table->string('run_kind', 16);
            // a step name (lessons, grounding, interactions, quizzes, metadata, assemble, apply, ingest, variant:<key>)
            // or the run kind when the run had no steps (the outline run)
            $table->string('stage', 64);
            $table->timestamp('started_at', 3);
            $table->timestamp('finished_at', 3)->nullable();
            $table->decimal('seconds', 10, 3)->nullable();
            // exact: both ends recorded | coarse: whole run from the run row | incomplete: closed by the run end, or still open
            $table->string('quality', 16)->default('exact');
            // the backfill and the live listener must never write the same stage twice
            $table->unique(['run_id', 'stage', 'started_at']);
            $table->index(['run_id', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authoring_stage_timings');
    }
};
