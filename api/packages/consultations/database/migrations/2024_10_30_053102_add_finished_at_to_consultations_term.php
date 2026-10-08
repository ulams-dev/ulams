<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('consultation_user_terms', function (Blueprint $table) {
            $table->id();

            $table->dateTime('executed_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->string('executed_status')->nullable();
            $table->string('reminder_status', 30)->nullable();

            $table->foreignId('consultation_user_id')->references('id')->on('consultation_user')->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('consultation_user_terms');
    }
};
