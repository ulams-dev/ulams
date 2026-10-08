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
        Schema::create('aggregated_frames', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('external_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->dateTime('term');

            $table->dateTime('window_start')->index();
            $table->dateTime('window_end')->index();
            $table->unsignedInteger('count')->default(0);

            $table->decimal('sum_attention', 30, 25)->nullable();
            $table->decimal('sum_emotions_angry', 30, 25)->nullable();
            $table->decimal('sum_emotions_disgusted', 30, 25)->nullable();
            $table->decimal('sum_emotions_fearful', 30, 25)->nullable();
            $table->decimal('sum_emotions_happy', 30, 25)->nullable();
            $table->decimal('sum_emotions_neutral', 30, 25)->nullable();
            $table->decimal('sum_emotions_sad', 30, 25)->nullable();
            $table->decimal('sum_emotions_surprised', 30, 25)->nullable();

            $table->decimal('avg_attention', 30, 25)->nullable();
            $table->decimal('avg_emotions_angry', 30, 25)->nullable();
            $table->decimal('avg_emotions_disgusted', 30, 25)->nullable();
            $table->decimal('avg_emotions_fearful', 30, 25)->nullable();
            $table->decimal('avg_emotions_happy', 30, 25)->nullable();
            $table->decimal('avg_emotions_neutral', 30, 25)->nullable();
            $table->decimal('avg_emotions_sad', 30, 25)->nullable();
            $table->decimal('avg_emotions_surprised', 30, 25)->nullable();

            $table->decimal('median_attention', 30, 25)->nullable();
            $table->decimal('median_emotions_angry', 30, 25)->nullable();
            $table->decimal('median_emotions_disgusted', 30, 25)->nullable();
            $table->decimal('median_emotions_fearful', 30, 25)->nullable();
            $table->decimal('median_emotions_happy', 30, 25)->nullable();
            $table->decimal('median_emotions_neutral', 30, 25)->nullable();
            $table->decimal('median_emotions_sad', 30, 25)->nullable();
            $table->decimal('median_emotions_surprised', 30, 25)->nullable();

            $table->dateTime('aggregated_at')->nullable();
            $table->dateTime('send_at')->nullable();

            $table->boolean('should_break')->nullable();
            $table->decimal('break_confidence', 30, 25)->nullable();
            $table->unsignedInteger('recommended_in_minutes')->nullable();
            $table->string('reasoning')->nullable();
            $table->string('algorithm')->nullable();
            $table->decimal('processing_time_ms', 30, 25)->nullable();

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
        Schema::dropIfExists('aggregated_frames');
    }
};
