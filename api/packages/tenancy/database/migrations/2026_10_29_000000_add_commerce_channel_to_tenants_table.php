<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenants.commerce_channel` and `tenants.commerce_webhook_secret`: the tenant's channel at the commerce provider and
 * the HMAC secret of its order-event webhook (ADR 0130, 0131). The secret is encrypted by the model cast, so replicas
 * can rebuild the env file. Null while the tenant has no commerce channel. Platform database only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasTable('tenants')) {
            return;
        }

        foreach (['commerce_channel' => 64, 'commerce_webhook_secret' => null] as $column => $length) {
            if (!Schema::hasColumn('tenants', $column)) {
                Schema::table('tenants', function (Blueprint $table) use ($column, $length) {
                    $length === null ? $table->text($column)->nullable() : $table->string($column, $length)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasTable('tenants')) {
            return;
        }

        foreach (['commerce_channel', 'commerce_webhook_secret'] as $column) {
            if (Schema::hasColumn('tenants', $column)) {
                Schema::table('tenants', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
