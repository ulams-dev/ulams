<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a Passport personal access token as a scoped token (ADR 0074). Tokens without a row
 * (login, LTI, demo) are unscoped and behave as before. The token string itself is never stored:
 * Passport keeps only the token id, the secret is a signed JWT returned once.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_token_meta', function (Blueprint $table) {
            $table->id();
            $table->string('token_id', 100)->unique();
            $table->string('kind', 16)->default('cli'); // cli | agent | ci | integration
            $table->string('agent_name', 100)->nullable();
            $table->string('created_via', 16)->default('admin'); // admin | cli | device
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();

            $table->foreign('token_id')->references('id')->on('oauth_access_tokens')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_meta');
    }
};
