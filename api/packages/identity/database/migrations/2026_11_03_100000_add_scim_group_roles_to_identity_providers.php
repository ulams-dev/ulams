<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCIM groups (milestone ID-5): which role a member of a SCIM-managed group gets, by the group's display name
 * (`{"Instructors": "tutor"}`). Only `student` and `tutor` can be mapped; no mapping can grant `admin`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('identity_providers', function (Blueprint $table) {
            $table->json('scim_group_roles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('identity_providers', function (Blueprint $table) {
            $table->dropColumn('scim_group_roles');
        });
    }
};
