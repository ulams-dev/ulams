<?php

use Ulams\Core\Migrations\UlamsMigration;
use Ulams\Reports\Models\Report;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeasurementsTable extends UlamsMigration
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
