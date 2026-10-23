<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A schedule can send a saved report instead of a compliance dataset (ADR 0194). Existing rows keep working: they have
 * no saved report and still name a dataset and filters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_report_schedules', function (Blueprint $table) {
            $table->string('saved_report_id', 26)->nullable()->index();
            // recipients the last run could not send to (lost access, no e-mail address), for the schedule's owner
            $table->json('skipped_recipient_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('compliance_report_schedules', function (Blueprint $table) {
            $table->dropColumn(['saved_report_id', 'skipped_recipient_ids']);
        });
    }
};
