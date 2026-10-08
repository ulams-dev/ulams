<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductColumnInConsultationUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consultation_user', function (Blueprint $table) {
            $table->bigInteger('product_id')->unsigned()->nullable();
            if (Schema::hasTable('products')) {
                $table->foreign('product_id')->on('products')->references('id')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consultation_user', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }
}
