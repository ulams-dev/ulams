<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('consultation_teachers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('consultation_id')->unsigned()->index();
            $table->bigInteger('teacher_id')->unsigned();

            $table->foreign('consultation_id')->references('id')->on('consultations')->onDelete('CASCADE');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('CASCADE');
        });
    }

    public function down()
    {
        Schema::dropIfExists('consultation_teachers');
    }
};
