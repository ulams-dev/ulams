<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring tool a SCORM package was exported from (e.g. `adapt`), detected on upload, for labels
 * and reports. Nullable: older packages stay unlabelled.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('scorm', function (Blueprint $table) {
            $table->string('source_format', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scorm', function (Blueprint $table) {
            $table->dropColumn('source_format');
        });
    }
};
