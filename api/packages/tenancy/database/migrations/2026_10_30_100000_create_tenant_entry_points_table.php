<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenant_entry_points` and its audit trail (ADR 0195): branded front hosts of one tenant that aim at a
 * department, a course or a role. Platform database only (the front resolves a request host before it knows
 * the tenant); tenant databases run the same migration set, so it is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform()) {
            return;
        }

        if (!Schema::hasTable('tenant_entry_points')) {
            Schema::create('tenant_entry_points', function (Blueprint $table) {
                $table->string('id', 26)->primary();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('host')->unique();
                $table->string('key', 40);
                $table->string('kind', 16);
                $table->json('target')->nullable();
                $table->string('label', 120);
                $table->json('theme')->nullable();
                $table->json('seo')->nullable();
                $table->string('locale', 12)->nullable();
                $table->string('status', 16)->default('pending_dns')->index();
                $table->string('verification_token', 64)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'key']);
            });
        }

        if (!Schema::hasTable('tenant_entry_point_audits')) {
            Schema::create('tenant_entry_point_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                // no foreign key: the trail outlives the entry point it describes
                $table->string('entry_point_id', 26)->nullable()->index();
                $table->string('action', 16);
                $table->string('host');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_email')->nullable();
                $table->json('changes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform()) {
            return;
        }

        Schema::dropIfExists('tenant_entry_point_audits');
        Schema::dropIfExists('tenant_entry_points');
    }
};
