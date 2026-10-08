<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmi5sTable extends Migration
{
    public function up()
    {
        Schema::create('cmi5s', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('iri')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cmi5s');
    }
}
