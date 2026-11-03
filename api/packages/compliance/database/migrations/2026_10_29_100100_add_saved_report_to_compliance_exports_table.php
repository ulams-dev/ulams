<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An export can now come from a saved report (ADR 0194): which one, and by which route it left (download, schedule, feed, cli). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_exports', function (Blueprint $table) {
            $table->string('saved_report_id', 26)->nullable()->index();
            $table->string('channel', 16)->default('download');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_exports', function (Blueprint $table) {
            $table->dropColumn(['saved_report_id', 'channel']);
        });
    }
};
