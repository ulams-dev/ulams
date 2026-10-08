<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUserSubmissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_submissions', function (Blueprint $table) {
            $table->dropForeign(['access_url_id']);
            $table->dropColumn('access_url_id');
            $table->dropColumn('frontend_url');

            $table->string('status')->change();

            $table->morphs('morphable');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('user_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('access_url_id');
            $table->string('frontend_url');

            $table->foreign('access_url_id')
                ->on('access_urls')
                ->references('id')
                ->cascadeOnDelete();
        });
    }
}
