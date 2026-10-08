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
        Schema::table('term_analytics', function (Blueprint $table) {
            $table->dropUnique('term_analytics_model_type_model_id_term_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('term_analytics', function (Blueprint $table) {
            $table->unique(['model_type', 'model_id', 'term']);
        });
    }
};
