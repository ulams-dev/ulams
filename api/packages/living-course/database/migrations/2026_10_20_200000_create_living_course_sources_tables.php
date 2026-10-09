<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Living Course: connections, source revisions and their own fragment copies (ADR 0030). Tenant
 * database. Courses stay untouched when this migration is rolled back.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('living_course_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('session_id')->index();
            $table->ulid('source_id')->unique();
            $table->string('connector', 32);
            $table->json('config')->nullable();
            // encrypted:array cast; never serialised in API resources
            $table->text('secrets')->nullable();
            $table->string('webhook_id', 26)->unique();
            // manual | hourly | daily | weekly
            $table->string('schedule', 16)->default('manual');
            $table->boolean('auto_analyse')->default(true);
            $table->json('settings')->nullable();
            // active | paused | error
            $table->string('status', 16)->default('active');
            $table->ulid('synced_revision_id')->nullable();
            $table->ulid('latest_revision_id')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamp('last_change_at')->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('living_course_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('source_id')->index();
            $table->ulid('connection_id')->index();
            $table->unsignedInteger('number');
            // initial | upload | git | url | plugin
            $table->string('origin', 16);
            // commit SHA, ETag or file SHA-256
            $table->string('origin_ref', 128)->nullable();
            // initial | manual | upload | poll | webhook
            $table->string('trigger', 16);
            $table->unsignedBigInteger('triggered_by')->nullable();
            // fetched | ingested | unchanged | no_impact | failed
            $table->string('status', 16)->default('fetched');
            $table->string('raw_path', 512)->nullable();
            $table->string('markdown_path', 512)->nullable();
            $table->string('normalised_sha256', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('fragment_count')->default(0);
            $table->unsignedInteger('token_estimate')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'number']);
        });

        Schema::create('living_course_revision_fragments', function (Blueprint $table) {
            $table->ulid('revision_id');
            $table->string('fragment_id', 16);
            $table->string('file_path', 512)->nullable();
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
            $table->string('normalised_hash', 64);
            $table->primary(['revision_id', 'fragment_id']);
        });
    }

    public function down(): void
    {
        foreach (['revision_fragments', 'revisions', 'connections'] as $table) {
            Schema::dropIfExists("living_course_{$table}");
        }
    }
};
