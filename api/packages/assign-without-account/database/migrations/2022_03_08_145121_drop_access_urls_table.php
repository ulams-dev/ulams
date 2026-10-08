<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropAccessUrlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('access_urls');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('access_urls', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->morphs('modelable');
            $table->unique(['modelable_id', 'modelable_type']);
            $table->timestamps();
        });
    }
}
