<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeMorphFieldsNullableInTasksTable extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('related_type')->nullable()->change();
            $table->unsignedBigInteger('related_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('related_type')->nullable(false)->change();
            $table->unsignedBigInteger('related_id')->nullable(false)->change();
        });
    }
}
