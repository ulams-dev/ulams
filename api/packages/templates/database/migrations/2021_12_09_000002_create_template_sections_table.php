<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTemplateSectionsTable extends Migration
{
    private string $table = 'template_sections';

    public function up()
    {
        Schema::create(
            $this->table,
            function (Blueprint $table) {
                $table->id('id');
                $table->string('key');
                $table->text('content');
                $table->foreignId('template_id');
                $table->timestamps();
            }
        );
    }

    public function down()
    {
        Schema::dropIfExists($this->table);
    }
}
