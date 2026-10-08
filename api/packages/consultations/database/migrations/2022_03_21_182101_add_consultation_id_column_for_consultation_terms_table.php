<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConsultationIdColumnForConsultationTermsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consultation_terms', function (Blueprint $table) {
            $table->bigInteger('consultation_id')->unsigned()->nullable();
            $table->foreign('consultation_id')->on('consultations')->references('id')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consultation_terms', function (Blueprint $table) {
            $table->dropColumn('consultation_id');
        });
    }
}
