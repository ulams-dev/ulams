<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * The tenant registry lives in the platform database only. Tenant databases run the same
 * migration set, so the migration is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform() || Schema::hasTable('tenants')) {
            return;
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('theme')->nullable();
            $table->string('accent', 16)->nullable();
            $table->string('api_host')->unique();
            $table->string('front_host');
            $table->string('admin_host');
            $table->string('db_name');
            $table->string('db_user');
            $table->text('db_password');
            $table->text('app_key');
            $table->text('passport_private_key')->nullable();
            $table->text('passport_public_key')->nullable();
            $table->string('bucket');
            $table->string('redis_prefix');
            $table->string('status')->default('provisioning');
            $table->json('steps')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform()) {
            return;
        }

        Schema::dropIfExists('tenants');
    }
};
