<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveParticipantTable extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('consultations_participants');
    }

    public function down(): void
    {
        Schema::create('consultations_participants', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('consultation_id')->unsigned()->index();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('status')->default(null)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('SET NULL');
            $table->foreign('consultation_id')->references('id')->on('consultations')->onDelete('CASCADE');
        });
    }
}
