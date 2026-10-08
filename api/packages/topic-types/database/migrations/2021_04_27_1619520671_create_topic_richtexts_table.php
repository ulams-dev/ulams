<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicRichtextsTable extends Migration
{
    public function up()
    {
        Schema::create('topic_richtexts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->mediumText('value');
        });
    }

    public function down()
    {
        Schema::dropIfExists('topic_richtexts');
    }
}
