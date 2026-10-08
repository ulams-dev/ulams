<?php

use EscolaLms\StationaryEvents\Enum\StationaryEventStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusColumnToStationaryEventsTable extends Migration
{
    public function up(): void
    {
        Schema::table('stationary_events', function (Blueprint $table) {
            $table->string('status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stationary_events', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
