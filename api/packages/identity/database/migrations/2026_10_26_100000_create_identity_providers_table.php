<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity providers of a tenant (single sign-on) and the links between their subjects and local users.
 * The client secret is encrypted with the tenant's APP_KEY (ADR 0066) and never leaves the API.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('identity_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            // oidc now; saml in a later milestone
            $table->string('protocol', 16);
            $table->boolean('enabled')->default(false);
            // domains of the organisation, e.g. ["example.com"]; used to decide who may be linked by e-mail
            $table->json('email_domains')->nullable();
            // the provider is authoritative for e-mail addresses of email_domains (allows linking an existing account)
            $table->boolean('trust_email')->default(false);
            // create an account on first sign-in
            $table->boolean('jit_enabled')->default(true);
            $table->string('default_role', 16)->default('student');
            // issuer, client_id, scopes, token_auth, userinfo
            $table->json('oidc')->nullable();
            $table->text('oidc_client_secret')->nullable();
            $table->string('oidc_secret_hint', 8)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('identity_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('provider_id');
            $table->unsignedBigInteger('user_id');
            // the `sub` of the ID token: stable for a person at one provider
            $table->string('subject', 255);
            $table->string('email_at_link', 255)->nullable();
            // jit | email_trust | password_confirm | admin | scim
            $table->string('linked_via', 24);
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('identity_providers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['provider_id', 'subject']);
            $table->unique(['provider_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_links');
        Schema::dropIfExists('identity_providers');
    }
};
