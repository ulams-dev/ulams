<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outgoing webhooks (Phase 7.3): endpoints a tenant registers, one delivery per event and endpoint,
 * and one row per attempt. The signing secret is stored encrypted (it is needed to sign) and is
 * shown to the admin once.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('url', 2048);
            $table->string('description', 255)->nullable();
            // ["*"] or event types such as course.completed
            $table->json('events');
            $table->string('api_version', 16);
            $table->text('secret');
            $table->string('secret_hint', 8);
            $table->boolean('enabled')->default(true);
            $table->string('disabled_reason', 255)->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('endpoint_id');
            $table->string('event_id', 40)->index();
            $table->string('event_type', 64)->index();
            $table->longText('payload');
            // pending | retrying | succeeded | failed | cancelled
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->boolean('is_test')->default(false);
            $table->ulid('replay_of')->nullable();
            // one fact fired as several domain events (one per reviewer) is delivered once per endpoint
            $table->string('dedupe_key', 191)->nullable();
            $table->timestamps();

            $table->foreign('endpoint_id')->references('id')->on('webhook_endpoints')->cascadeOnDelete();
            $table->index(['endpoint_id', 'created_at']);
            $table->unique(['endpoint_id', 'dedupe_key']);
        });

        Schema::create('webhook_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('delivery_id');
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('delivery_id')->references('id')->on('webhook_deliveries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_attempts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
