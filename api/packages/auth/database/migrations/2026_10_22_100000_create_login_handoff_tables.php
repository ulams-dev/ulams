<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browser login hand-off (ADR 0121): the state of a login that goes through another party (social
 * login now, OIDC and SAML later) and the one-time code that carries the result back to the
 * front end. Only hashes of the secrets are stored; both kinds of row are single use.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('login_handoff_states', function (Blueprint $table) {
            $table->id();
            $table->string('state_hash', 64)->unique();
            $table->string('protocol', 16);
            $table->string('provider', 64);
            // host of the tenant API that started the login; the callback must arrive on the same host
            $table->string('host', 255);
            $table->text('return_to');
            // S256 challenge of the verifier the front end keeps in its own httpOnly cookie
            $table->string('challenge', 64);
            $table->text('data')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('login_handoff_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->string('kind', 16); // login | complete
            $table->string('host', 255);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('challenge', 64);
            $table->boolean('remember_me')->default(false);
            $table->text('payload')->nullable(); // encrypted
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_handoff_codes');
        Schema::dropIfExists('login_handoff_states');
    }
};
