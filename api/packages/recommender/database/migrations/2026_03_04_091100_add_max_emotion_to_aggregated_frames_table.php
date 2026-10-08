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
        Schema::table('aggregated_frames', function (Blueprint $table) {
            $table->string('max_emotion')->nullable();
            $table->decimal('max_emotion_value', 30, 25)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('aggregated_frames', function (Blueprint $table) {
            $table->dropColumn('max_emotion');
            $table->dropColumn('max_emotion_value');
        });
    }
};
