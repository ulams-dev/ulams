<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device login for the CLI (ADR 0075): RFC 8628 wire format, our own storage. Codes are kept only
 * as keyed hashes. The scoped token minted on approval waits here, encrypted, until the first poll
 * collects it (then the column is cleared), so the row never keeps a usable credential.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_authorizations', function (Blueprint $table) {
            $table->id();
            $table->string('device_code_hash', 64)->unique();
            $table->string('user_code_hash', 64)->index();
            $table->string('client_name', 100);
            $table->string('agent_name', 100)->nullable();
            $table->json('requested_scopes');
            $table->json('approved_scopes')->nullable();
            $table->string('status', 16)->default('pending'); // pending | approved | denied | consumed | expired
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('token_id', 100)->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_authorizations');
    }
};
