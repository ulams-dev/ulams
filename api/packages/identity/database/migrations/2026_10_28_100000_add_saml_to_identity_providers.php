<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAML 2.0 (milestone ID-2): the provider's SAML settings, the IdP signing certificates it trusts, the SP key pairs
 * (private keys encrypted with the tenant's APP_KEY by the model cast), the replay table of assertion ids and the
 * map from a Passport token to the IdP session it came from (single logout).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('identity_providers', function (Blueprint $table) {
            // entity_id, sso_url, slo_url, metadata_url, name_id_format, want_encrypted_assertions, allow_sha1, attribute_map
            $table->json('saml')->nullable();
            // trusted IdP signing certificates: pem body, sha256 fingerprint, validity, source
            $table->json('saml_idp_certs')->nullable();
            // this SP's key pair per provider (signs requests, decrypts assertions); the next pair is published first
            $table->text('saml_sp_key')->nullable();
            $table->text('saml_sp_cert')->nullable();
            $table->text('saml_sp_next_key')->nullable();
            $table->text('saml_sp_next_cert')->nullable();
            $table->timestamp('metadata_refreshed_at')->nullable();
        });

        Schema::create('saml_replays', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('provider_id');
            // sha256 of the assertion (or logout request) id: ids are chosen by the IdP, never stored raw
            $table->string('id_hash', 64);
            $table->timestamp('expires_at')->index();

            $table->foreign('provider_id')->references('id')->on('identity_providers')->cascadeOnDelete();
            $table->unique(['provider_id', 'id_hash']);
        });

        Schema::create('identity_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('provider_id');
            $table->unsignedBigInteger('user_id');
            // Passport token id of the login that came out of this IdP session
            $table->string('token_id', 100)->unique();
            $table->string('name_id', 512);
            $table->string('name_id_format', 255)->nullable();
            $table->string('session_index', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            // a LogoutRequest of ours waiting for the IdP's answer: sha256 of its id and where to send the browser after
            $table->string('logout_request_hash', 64)->nullable()->index();
            $table->text('logout_return_to')->nullable();

            $table->foreign('provider_id')->references('id')->on('identity_providers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['provider_id', 'name_id']);
            $table->index(['provider_id', 'session_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_sessions');
        Schema::dropIfExists('saml_replays');
        Schema::table('identity_providers', function (Blueprint $table) {
            $table->dropColumn(['saml', 'saml_idp_certs', 'saml_sp_key', 'saml_sp_cert', 'saml_sp_next_key', 'saml_sp_next_cert', 'metadata_refreshed_at']);
        });
    }
};
