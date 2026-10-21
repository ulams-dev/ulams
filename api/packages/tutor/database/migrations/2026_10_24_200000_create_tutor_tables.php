<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Course chunk index, learner-owned conversations and the daily usage counter (ADR 0060). Idempotent per table. */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tutor_chunks')) {
            Schema::create('tutor_chunks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('course_id')->index();
                $table->unsignedBigInteger('topic_id')->nullable();
                $table->char('element_id', 26)->nullable();
                $table->json('fragment_ids')->nullable();
                $table->string('label', 255);
                $table->text('text');
                $table->string('language', 10);
                $table->string('source_signature', 64); // what the index was built from (applied version or content hash)
            });
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE tutor_chunks ADD COLUMN tsv tsvector');
                DB::statement('CREATE INDEX tutor_chunks_tsv_idx ON tutor_chunks USING GIN (tsv)');
            }
        }

        if (!Schema::hasTable('tutor_conversations')) {
            Schema::create('tutor_conversations', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('course_id');
                $table->timestamp('created_at');
                $table->timestamp('last_message_at');
                $table->timestamp('expires_at');
                $table->index(['user_id', 'course_id', 'last_message_at']);
                $table->index('expires_at');
            });
        }

        if (!Schema::hasTable('tutor_messages')) {
            Schema::create('tutor_messages', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->char('conversation_id', 26)->index();
                $table->string('role', 8); // learner | tutor
                $table->text('text');
                $table->json('citations')->nullable();
                $table->string('refusal', 20)->nullable();
                $table->json('flags')->nullable();
                $table->string('ai_call_id', 40)->nullable();
                $table->timestamp('created_at');
            });
        }

        if (!Schema::hasTable('tutor_daily_usage')) {
            Schema::create('tutor_daily_usage', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->date('date');
                $table->unsignedInteger('questions')->default(0);
                $table->primary(['user_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        foreach (['tutor_daily_usage', 'tutor_messages', 'tutor_conversations', 'tutor_chunks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
