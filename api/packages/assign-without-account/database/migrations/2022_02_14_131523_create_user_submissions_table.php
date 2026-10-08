<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSubmissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('access_url_id');
            $table->string('email', 255);
            $table->string('frontend_url');
            $table->integer('status');
            $table->timestamps();

            $table->foreign('access_url_id')->on('access_urls')->references('id')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_submissions');
    }
}
