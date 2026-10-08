<?php

use EscolaLms\Core\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagesTable extends Migration
{
    private string $table = 'pages';

    public function up()
    {
        Schema::create(
            $this->table,
            function (Blueprint $table) {
                $table->id('id');
                $table->string('slug');
                $table->string('title');
                $table->foreignIdFor(User::class, 'author_id');
                $table->longText('content');
                $table->boolean('active')->default(true);
            }
        );
    }

    public function down()
    {
        Schema::dropIfExists($this->table);
    }
}
