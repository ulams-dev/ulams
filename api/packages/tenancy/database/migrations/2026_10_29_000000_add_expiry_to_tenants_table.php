<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenants.expires_at` and `tenants.preview_group`: a throwaway tenant (a preview academy per pull request, plan
 * docs/plans/phase-7-course-as-code.md 8.3) is deleted by `ulams:tenant:reap` once it has expired; the group (one
 * repository) caps how many previews exist at once. Platform database only, like the tenants table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'expires_at')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('preview_group', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform() || !Schema::hasColumn('tenants', 'expires_at')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'preview_group']);
        });
    }
};
