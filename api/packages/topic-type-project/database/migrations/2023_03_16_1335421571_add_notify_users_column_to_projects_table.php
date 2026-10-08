<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotifyUsersColumnToProjectsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_projects', function (Blueprint $table) {
            $table->json('notify_users')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_projects', function (Blueprint $table) {
            $table->dropColumn('notify_users');
        });
    }
}
