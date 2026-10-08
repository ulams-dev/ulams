<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropBasePriceFieldFromStationaryEventsTable extends Migration
{
    public function up(): void
    {
        Schema::table('stationary_events', function (Blueprint $table) {
            $table->dropColumn('base_price');
        });
    }

    public function down(): void
    {
        Schema::table('stationary_events', function (Blueprint $table) {
            $table->integer('base_price')->nullable();
        });
    }
}
