<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenant_upgrade_steps`: which one-off `ulams:upgrade` steps already ran for the platform
 * (`target` = `platform`) and for each tenant (`target` = the tenant slug). Platform database only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform() || Schema::hasTable('tenant_upgrade_steps')) {
            return;
        }

        Schema::create('tenant_upgrade_steps', function (Blueprint $table) {
            $table->id();
            $table->string('target', 191);
            $table->string('step', 191);
            $table->string('since', 64)->nullable();
            $table->timestamp('ran_at')->useCurrent();
            $table->unique(['target', 'step']);
        });
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform()) {
            return;
        }

        Schema::dropIfExists('tenant_upgrade_steps');
    }
};
