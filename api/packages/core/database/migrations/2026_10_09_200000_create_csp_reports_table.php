<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregated Content Security Policy violation reports (ADR 0044). One row per directive, blocked
 * host and page path; no full URLs and no query strings are kept.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('csp_reports')) {
            return;
        }

        Schema::create('csp_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('directive', 64);
            $table->string('blocked_host', 255);
            $table->string('document_path', 255);
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->unique(['directive', 'blocked_host', 'document_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csp_reports');
    }
};
