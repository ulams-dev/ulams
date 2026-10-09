<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per cmi5 launch (ADR 0046). `token_hash` is the SHA-256 of the one-time token in the
 * launch URL. Before the first fetch `expires_at` ends the launch window; the fetch sets `used_at`
 * and moves `expires_at` to the end of the LRS session.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('lrs_launch_tokens')) {
            return;
        }

        Schema::create('lrs_launch_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('registration');
            $table->unsignedBigInteger('au_id')->nullable();
            $table->uuid('access_uuid');
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lrs_launch_tokens');
    }
};
