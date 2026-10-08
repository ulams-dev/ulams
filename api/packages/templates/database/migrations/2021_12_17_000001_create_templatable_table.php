<?php

use EscolaLms\Templates\Models\Template;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTemplatableTable extends Migration
{
    private string $table = 'templatables';

    public function up()
    {
        Schema::create(
            $this->table,
            function (Blueprint $table) {
                $table->id();
                $table->string('channel');
                $table->string('event');
                $table->foreignIdFor(Template::class);
                $table->morphs('templatable');
                $table->unique(['template_id', 'templatable_type', 'templatable_id'], 'unique_template_templatable_pair');
                $table->unique(['channel', 'event', 'templatable_type', 'templatable_id'], 'one_template_per_channel_and_event');
                $table->timestamps();
            }
        );
    }

    public function down()
    {
        Schema::dropIfExists($this->table);
    }
}
