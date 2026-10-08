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
        Schema::table('meet_recordings', function (Blueprint $table) {
            $table->dropColumn('url_expiration_time_millis');
            $table->timestamp('url_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('meet_recordings', function (Blueprint $table) {
            $table->dropColumn('url_expires_at');
            $table->unsignedBigInteger('url_expiration_time_millis')->nullable();
        });
    }
};
