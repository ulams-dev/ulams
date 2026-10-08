<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meet_recordings', function (Blueprint $table) {
            $table->id();

            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->dateTime('term');

            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->string('url')->nullable();
            $table->unsignedBigInteger('url_expiration_time_millis')->nullable();

            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'term']);
            $table->index(['model_type', 'model_id']);
            $table->index(['model_type', 'term']);
        });

        Schema::create('meet_recording_screens', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->dateTime('term');
            $table->string('file_path');
            $table->timestamp('file_timestamp');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meet_recordings');
        Schema::dropIfExists('meet_recording_screens');
    }
};
