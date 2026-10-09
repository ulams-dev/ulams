<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LTI Names and Role Provisioning Services (NRPS 2.0): a registered tool may read the member list
 * of the courses it is linked in only when the registration says so. Off by default.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('lti_tools', function (Blueprint $table) {
            $table->boolean('nrps_enabled')->default(false)->after('share_email');
        });
    }

    public function down(): void
    {
        Schema::table('lti_tools', function (Blueprint $table) {
            $table->dropColumn('nrps_enabled');
        });
    }
};
