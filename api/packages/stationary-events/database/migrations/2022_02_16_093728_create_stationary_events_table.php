<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStationaryEventsTable extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->dateTime('started_at');
            $table->dateTime('finished_at');
            $table->integer('base_price')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->string('place')->nullable();
            $table->string('program')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_events');
    }
}
