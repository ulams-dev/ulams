<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenant_operations`: one row per tenant creation or deletion requested through the platform API
 * (ADR 0078), with the steps and their state, so a client can poll `GET /api/platform/operations/{id}`.
 * Platform database only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!TenantContext::isPlatform() || Schema::hasTable('tenant_operations')) {
            return;
        }

        Schema::create('tenant_operations', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('kind', 16);
            $table->string('tenant_slug', 64)->index();
            $table->string('status', 16)->index();
            $table->json('steps')->nullable();
            $table->json('input')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (!TenantContext::isPlatform()) {
            return;
        }

        Schema::dropIfExists('tenant_operations');
    }
};
