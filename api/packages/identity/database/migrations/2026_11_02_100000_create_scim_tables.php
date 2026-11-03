<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCIM 2.0 provisioning (milestone ID-4): the service user whose tokens a provider's SCIM client uses, and the table
 * that maps a SCIM resource of one provider to a local user or group. Keeping the external ids here leaves the
 * `users` and `groups` tables unchanged. A deprovisioned resource keeps its row with `deleted_at` set (a tombstone),
 * so the same userName can be provisioned again and the account is never removed by default.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('identity_providers', function (Blueprint $table) {
            // the passwordless service user that holds this provider's SCIM tokens (kind `scim`); null until the first token
            $table->unsignedBigInteger('scim_user_id')->nullable()->unique();
        });

        Schema::create('identity_external_ids', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('provider_id');
            // user | group
            $table->string('resource_type', 8);
            $table->unsignedBigInteger('local_id');
            // the client's own id for the resource (SCIM externalId)
            $table->string('external_id', 255)->nullable();
            // SCIM userName as sent, and its lower-case key (unique per tenant, case-insensitive); users only
            $table->string('user_name', 255)->nullable();
            $table->string('user_name_key', 255)->nullable();
            // scim | hris | jit
            $table->string('source', 8)->default('scim');
            // attributes that round-trip through SCIM and have no column of their own (displayName, title, enterprise)
            $table->json('meta')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('identity_providers')->cascadeOnDelete();
            $table->unique(['provider_id', 'resource_type', 'local_id'], 'identity_external_ids_local');
            $table->unique(['provider_id', 'resource_type', 'external_id'], 'identity_external_ids_external');
            $table->unique(['resource_type', 'user_name_key'], 'identity_external_ids_user_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_external_ids');
        Schema::table('identity_providers', function (Blueprint $table) {
            $table->dropUnique(['scim_user_id']);
            $table->dropColumn('scim_user_id');
        });
    }
};
