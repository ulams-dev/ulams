<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStationaryEventAuthorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_event_authors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('stationary_event_id');
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('stationary_event_id')->references('id')->on('stationary_events')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_event_authors');
    }
}
