<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The append-only event log of streamed tutor answers (ADR 0105): `id` is the SSE event id. Idempotent. */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tutor_events')) {
            return;
        }
        Schema::create('tutor_events', function (Blueprint $table) {
            $table->id();
            $table->char('run_id', 26);
            $table->char('conversation_id', 26);
            $table->string('type', 32);
            $table->json('payload');
            $table->timestamp('created_at');
            $table->index(['run_id', 'id']);
            $table->index('conversation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_events');
    }
};
