<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryStationaryEventTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_stationary_event', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stationary_event_id');
            $table->unsignedInteger('category_id');
            $table->timestamps();

            $table->foreign('stationary_event_id')->on('stationary_events')->references('id')->cascadeOnDelete();
            $table->foreign('category_id')->on('categories')->references('id')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('category_stationary_event');
    }
}
