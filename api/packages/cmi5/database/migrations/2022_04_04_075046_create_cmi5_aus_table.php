<?php

use EscolaLms\Cmi5\Models\Cmi5;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmi5AusTable extends Migration
{
    public function up()
    {
        Schema::create('cmi5_aus', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('iri')->nullable();
            $table->string('url');
            $table->foreignIdFor(Cmi5::class)->constrained();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cmi5_aus');
    }
}
