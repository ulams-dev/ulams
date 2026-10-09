<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course Builder tables (tenant database). Applied courses are ordinary LMS courses and survive a
 * rollback of this migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_builder_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('author_id')->index();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('draft');
            $table->json('brief')->nullable();
            $table->unsignedInteger('brief_version')->default(0);
            $table->json('state')->nullable();
            $table->ulid('current_version_id')->nullable();
            $table->ulid('applied_version_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('tokens_used')->default(0);
            $table->unsignedBigInteger('cost_micro_usd')->default(0);
            $table->unsignedBigInteger('budget_tokens')->nullable();
            $table->unsignedBigInteger('budget_micro_usd')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('course_builder_sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('session_id')->index();
            $table->string('original_name');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('path');
            $table->string('status', 16)->default('uploaded');
            $table->string('markdown_path')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('token_estimate')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('course_builder_fragments', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->ulid('source_id')->index();
            $table->unsignedInteger('ordinal');
            $table->json('heading_path');
            $table->string('section', 32)->nullable();
            $table->unsignedSmallInteger('level')->default(0);
            $table->text('text');
            $table->unsignedInteger('char_start');
            $table->unsignedInteger('char_end');
            $table->unsignedInteger('page_start')->nullable();
            $table->unsignedInteger('page_end')->nullable();
            $table->unsignedInteger('token_estimate');
            $table->string('content_hash', 64);
        });

        Schema::create('course_builder_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('session_id')->index();
            $table->unsignedInteger('number');
            $table->ulid('parent_id')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            // outline | content | patch | author | restore
            $table->string('kind', 16);
            $table->json('document');
            $table->json('diff_from_parent')->nullable();
            $table->string('origin', 16);
            $table->text('reason')->nullable();
            $table->string('status', 16);
            $table->string('element_id', 32)->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->json('ai_call_ids')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'number']);
        });

        Schema::create('course_builder_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('session_id')->index();
            $table->string('kind', 16);
            $table->string('status', 24)->default('queued');
            $table->string('stage', 32)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('input')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('course_builder_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('run_id')->index();
            $table->string('key', 64);
            $table->string('stage', 32);
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('cost_micro_usd')->default(0);
            $table->timestamps();
            $table->unique(['run_id', 'key']);
        });

        Schema::create('course_builder_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('session_id');
            $table->ulid('run_id')->nullable();
            $table->string('type', 32);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['session_id', 'id']);
        });

        Schema::create('course_builder_entity_map', function (Blueprint $table) {
            $table->id();
            $table->ulid('session_id');
            $table->string('element_id', 32);
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('fingerprint', 64)->nullable();
            $table->ulid('applied_version_id')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'element_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        foreach (['entity_map', 'events', 'steps', 'runs', 'versions', 'fragments', 'sources', 'sessions'] as $table) {
            Schema::dropIfExists("course_builder_{$table}");
        }
    }
};
