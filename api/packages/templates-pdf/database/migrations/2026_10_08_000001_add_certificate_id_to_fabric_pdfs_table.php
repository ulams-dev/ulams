<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Random, non-enumerable certificate id (also in vars as @VarCertificateId),
 * encoded in the verification QR code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_pdfs', function (Blueprint $table) {
            $table->string('certificate_id', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('fabric_pdfs', function (Blueprint $table) {
            $table->dropUnique(['certificate_id']);
            $table->dropColumn('certificate_id');
        });
    }
};
