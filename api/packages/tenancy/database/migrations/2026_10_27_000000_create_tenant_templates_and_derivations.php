<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * Tenant templates and derived tenants (ADR 0141, amendment). A template is a named starting point for new tenants (theme,
 * accent, demo flag, demo users, inheritable env overrides); a derivation records that a tenant was created for a purpose
 * (a department academy of a policy) by a parent tenant, once per (parent, purpose, reference), so the same request never
 * creates a second tenant and a parent cannot create more than its quota. Platform database only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform()) {
            return;
        }
        if (!Schema::hasTable('tenant_templates')) {
            Schema::create('tenant_templates', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 40)->unique();
                $table->string('name', 100);
                $table->string('theme', 40)->nullable();
                $table->string('accent', 7)->nullable();
                $table->boolean('demo')->default(false);
                $table->unsignedSmallInteger('users')->default(0);
                // encrypted by the model cast, allow-listed keys only (TenantNaming::inheritableKeys)
                $table->text('env_overrides')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('tenant_derivations')) {
            Schema::create('tenant_derivations', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_slug', 30)->unique();
                $table->string('parent_slug', 30)->index();
                $table->string('template_slug', 40)->nullable();
                $table->string('purpose', 32);
                $table->string('reference', 128);
                $table->string('requested_by', 190)->nullable();
                // active | deleted (the row stays as the record of who created what and when)
                $table->string('status', 12)->default('active');
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
                $table->unique(['parent_slug', 'purpose', 'reference']);
            });
        }
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform()) {
            return;
        }
        Schema::dropIfExists('tenant_derivations');
        Schema::dropIfExists('tenant_templates');
    }
};
