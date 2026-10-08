<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeContentColumn extends Migration
{
    private string $table = 'template_sections';

    public function up()
    {
        Schema::table(
            $this->table,
            function (Blueprint $table) {
                $table->longText('content')->change();
            }
        );
    }

    public function down()
    {
        Schema::table(
            $this->table,
            function (Blueprint $table) {
                $table->text('content')->change();
            }
        );
    }
}
