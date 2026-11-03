<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Every order event the LMS has seen, from the webhook or from reconciliation. The unique key is what
        // makes a redelivered event a no-op (ADR 0130). Payloads are never stored, only a digest.
        Schema::create('commerce_order_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 64);
            $table->string('order_id', 64);
            $table->unsignedBigInteger('sequence');
            $table->string('type', 32);
            $table->string('source', 16);
            $table->string('payload_sha256', 64);
            $table->timestamp('received_at');
            $table->timestamp('applied_at')->nullable();
            // applied, stale, rejected, failed; null while the job has not finished
            $table->string('outcome', 16)->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'order_id']);
        });

        // Projection of the provider's order: what the admin lists and what orders events in sequence.
        Schema::create('commerce_orders', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('external_order_id', 64);
            $table->string('number', 64)->nullable();
            $table->string('token', 191)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('state', 32)->nullable();
            $table->string('payment_state', 32)->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('total_minor')->nullable();
            $table->unsignedBigInteger('tax_minor')->nullable();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_order_id']);
        });

        // Where reconciliation stopped reading the provider's event feed.
        Schema::create('commerce_sync_state', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('channel', 64);
            $table->string('cursor', 191)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_applied')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_sync_state');
        Schema::dropIfExists('commerce_orders');
        Schema::dropIfExists('commerce_order_events');
    }
};
