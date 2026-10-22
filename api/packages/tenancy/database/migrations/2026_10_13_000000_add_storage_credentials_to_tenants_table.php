<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenants.storage_key` and `tenants.storage_secret`: the tenant's own object-store key pair (ADR 0041),
 * encrypted by the model cast, so replicas can rebuild the env files. Null while the tenant uses the
 * platform keys. Platform database only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasTable('tenants')) {
            return;
        }

        foreach (['storage_key', 'storage_secret'] as $column) {
            if (!Schema::hasColumn('tenants', $column)) {
                Schema::table('tenants', function (Blueprint $table) use ($column) {
                    $table->text($column)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasTable('tenants')) {
            return;
        }

        foreach (['storage_key', 'storage_secret'] as $column) {
            if (Schema::hasColumn('tenants', $column)) {
                Schema::table('tenants', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
