<?php

use EscolaLms\Core\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'pages';

    public function up()
    {
        Schema::table(
            $this->table,
            function (Blueprint $table) {
                $table->timestamps();
            }
        );
    }

    public function down()
    {
        Schema::table(
            $this->table,
            function (Blueprint $table) {
                $table->dropTimestamps();
            }
        );
    }
};
