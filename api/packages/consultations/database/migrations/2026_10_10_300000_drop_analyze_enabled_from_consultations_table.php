<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The switch was stored but never read (the AI recording analysis it belonged to is gone). */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('consultations', 'analyze_enabled')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->dropColumn('analyze_enabled');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('consultations', 'analyze_enabled')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->boolean('analyze_enabled')->nullable();
            });
        }
    }
};
