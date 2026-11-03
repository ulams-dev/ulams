<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Whether a saved report is offered as a read-only OData feed to BI tools (ADR 0194). Off until an administrator who manages reports turns it on. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->boolean('feed_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->dropColumn('feed_enabled');
        });
    }
};
