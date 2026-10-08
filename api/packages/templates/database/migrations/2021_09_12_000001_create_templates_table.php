<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTemplatesTable extends Migration
{
    private string $table = 'templates';

    public function up()
    {
        Schema::create(
            $this->table,
            function (Blueprint $table) {
                $table->id('id');
                $table->string('name');
                $table->string('type'); // PDF, email, notification
                $table->string('vars_set')->default('certificates'); // certificates, email_ceortificate, whatever
                $table->longText('content');
                $table->timestamps();
            }
        );
    }

    public function down()
    {
        Schema::dropIfExists($this->table);
    }
}
