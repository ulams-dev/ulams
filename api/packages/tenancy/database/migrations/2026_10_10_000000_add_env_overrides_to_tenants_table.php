<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenants.env_overrides`: encrypted JSON with the tenant's own values for the inheritable
 * platform settings (AI key, driver, models; ADR 0063). Platform database only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'env_overrides')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->text('env_overrides')->nullable();
        });
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasColumn('tenants', 'env_overrides')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('env_overrides');
        });
    }
};
