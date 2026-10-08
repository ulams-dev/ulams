<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsultationProposedTermsTale extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consultation_proposed_terms', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('consultation_id')->unsigned();
            $table->dateTime('proposed_at');
            $table->timestamps();

            $table->foreign('consultation_id')->on('consultations')->references('id')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consultation_proposed_terms');
    }
}
