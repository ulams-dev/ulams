<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Update proposals and their items (ADR 0030). */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('living_course_proposals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedInteger('number');
            $table->ulid('session_id')->index();
            $table->ulid('source_id')->index();
            $table->ulid('from_revision_id');
            $table->ulid('to_revision_id');
            $table->ulid('base_version_id')->nullable();
            $table->ulid('result_version_id')->nullable();
            $table->ulid('run_id')->nullable();
            // analysing | ready | applying | applied | no_impact | awaiting_analysis | budget_blocked | rejected | superseded | failed
            $table->string('status', 24);
            // manual | upload | poll | webhook
            $table->string('trigger', 16)->default('manual');
            $table->json('counts')->nullable();
            $table->unsignedBigInteger('estimated_cost_micro_usd')->default(0);
            $table->unsignedBigInteger('cost_micro_usd')->default(0);
            $table->text('learner_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'number']);
        });

        Schema::create('living_course_proposal_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('proposal_id')->index();
            $table->string('group_key', 64);
            // blueprint element id, or the new fragment id for an uncovered section
            $table->string('element_id', 32);
            // course | lesson | objective | block | question | section
            $table->string('element_type', 16);
            $table->string('label', 160)->nullable();
            // update | citation_remap | remove | no_change | manual | uncovered
            $table->string('kind', 16);
            $table->json('change_ids')->nullable();
            $table->json('fragment_ids')->nullable();
            $table->text('reason')->nullable();
            // minor | major
            $table->string('severity', 8)->default('minor');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            // none | minor | major | answer_changed | removed
            $table->string('change_class', 16)->nullable();
            // unchanged | changed | unsure
            $table->string('answer_status', 16)->nullable();
            $table->boolean('answer_check')->default(false);
            // pending | accepted | rejected | conflict | stale
            $table->string('status', 16)->default('pending');
            $table->json('flags')->nullable();
            $table->unsignedSmallInteger('regenerations')->default(0);
            $table->json('ai_call_ids')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['proposal_id', 'element_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('living_course_proposal_items');
        Schema::dropIfExists('living_course_proposals');
    }
};
