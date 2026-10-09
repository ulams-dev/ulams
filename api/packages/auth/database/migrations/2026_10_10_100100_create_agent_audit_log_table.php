<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of what scoped tokens did (ADR 0074): one row per mutating request, plus
 * reads of the `users` and `reports` areas. No request or response bodies are stored.
 * No foreign keys on purpose: the rows must outlive a revoked or purged token.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('token_id', 100)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('agent_name', 100)->nullable();
            $table->string('client', 32)->nullable(); // X-Ulams-Client: cli | mcp | ...
            $table->string('user_agent', 255)->nullable();
            $table->string('method', 8);
            $table->string('route_name', 150)->nullable();
            $table->string('path', 500);
            $table->json('route_params')->nullable();
            $table->unsignedSmallInteger('status');
            $table->boolean('dry_run')->default(false);
            $table->string('idempotency_key', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['token_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_audit_log');
    }
};
