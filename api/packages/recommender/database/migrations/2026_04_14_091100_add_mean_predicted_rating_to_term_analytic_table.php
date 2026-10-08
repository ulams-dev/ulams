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
            $table->decimal('mean_predicted_rating', 10, 5)->nullable();
            $table->string('satisfaction_status')->nullable();
            $table->timestamp('satisfaction_requested_at')->nullable();
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
            $table->dropColumn('mean_predicted_rating');
            $table->dropColumn('satisfaction_status');
            $table->dropColumn('satisfaction_requested_at');
        });
    }
};
