<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsultationCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_consultation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consultation_id');
            $table->unsignedInteger('category_id');
            $table->timestamps();
        });
        Schema::table('category_consultation', function (Blueprint $table) {
            $table->foreign('consultation_id')->on('consultations')->references('id')->cascadeOnDelete();
            $table->foreign('category_id')->on('categories')->references('id')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('category_consultation');
    }
}
