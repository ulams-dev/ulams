<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificate lifecycle (Phase 6.1): expiry, revocation, the course version the certificate attests
 * and the certificate that replaced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_pdfs', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->string('course_version', 64)->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fabric_pdfs', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['expires_at', 'revoked_at', 'revoked_reason', 'revoked_by', 'course_version', 'superseded_by_id']);
        });
    }
};
