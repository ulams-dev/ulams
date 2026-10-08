<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenants.demo`: the tenant runs in demo mode (DEMO_MODE=true in its env file, see
 * packages/demo). Platform database only, like the tenants table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'demo')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('demo')->default(false)->after('accent');
        });
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasColumn('tenants', 'demo')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('demo');
        });
    }
};
