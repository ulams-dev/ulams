<?php

use EscolaLms\Core\Migrations\EscolaMigration;
use EscolaLms\Reports\Models\Report;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeasurementsTable extends EscolaMigration
{
    public function up()
    {
        $this->create('measurements', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(Report::class);
            $table->string('label');
            $table->integer('value');
            $table->morphs('measurable');
        });
    }

    public function down()
    {
        Schema::dropIfExists('measurements');
    }
}
