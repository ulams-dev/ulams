<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAgendaColumnToStationaryEventsTable extends Migration
{
    public function up(): void
    {
        Schema::table('stationary_events', function (Blueprint $table) {
            $table->json('agenda')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stationary_events', function (Blueprint $table) {
            $table->dropColumn('agenda');
        });
    }
}
