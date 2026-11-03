<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request counters per scoped token and day (Phase 7.3 "usage stats"). Counts only; no paths, bodies or
 * IP addresses (the agent audit log has those for writes). A row is created on the first request of a day.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_token_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->string('token_id', 100);
            $table->date('day');
            $table->unsignedInteger('requests')->default(0);
            // 4xx and 5xx answers except 429
            $table->unsignedInteger('errors')->default(0);
            // refused by the token's own rate limit
            $table->unsignedInteger('rate_limited')->default(0);
            $table->timestamps();

            $table->unique(['token_id', 'day']);
            $table->foreign('token_id')->references('id')->on('oauth_access_tokens')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_usage_daily');
    }
};
