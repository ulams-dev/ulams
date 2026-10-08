<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShortDescColumnToStationaryEventsTable extends Migration
{
    public function up(): void
    {
        Schema::table('stationary_events', function (Blueprint $table) {
            $table->string('short_desc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stationary_events', function (Blueprint $table) {
            $table->dropColumn('short_desc');
        });
    }
}
