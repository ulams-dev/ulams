<?php

use EscolaLms\Core\Migrations\EscolaMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsTable extends EscolaMigration
{
    public function up()
    {
        $this->create('reports', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('metric');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reports');
    }
}
