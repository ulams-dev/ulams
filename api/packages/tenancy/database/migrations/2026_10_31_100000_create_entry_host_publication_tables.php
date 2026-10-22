<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * The trusted entry hosts of an academy (ADR 0195, EP-3).
 *
 * `tenant_entry_hosts` is the academy's own copy of its active entry hosts: the origin check of the API, the return
 * URLs of the redirect logins, the frames that may embed interactive content and the session hand-off read it, and a
 * tenant process cannot reach the platform table. Only the platform writes it, through `ulams:entry-points:publish`;
 * no API route does. It is created in every database (empty where nobody publishes to it).
 *
 * `tenant_entry_host_publications` (platform only) remembers a hash of the list last published to each academy, so
 * the reconcile job republishes exactly the academies whose copy is out of date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_entry_hosts')) {
            Schema::create('tenant_entry_hosts', function (Blueprint $table) {
                $table->string('host')->primary();
                $table->string('origin');
                $table->string('kind', 16);
                $table->string('key', 40);
                $table->timestamp('published_at')->useCurrent();
            });
        }

        if (TenantContext::isPlatform() && !Schema::hasTable('tenant_entry_host_publications')) {
            Schema::create('tenant_entry_host_publications', function (Blueprint $table) {
                $table->foreignId('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
                $table->string('hash', 64);
                $table->timestamp('published_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_entry_host_publications');
        Schema::dropIfExists('tenant_entry_hosts');
    }
};
