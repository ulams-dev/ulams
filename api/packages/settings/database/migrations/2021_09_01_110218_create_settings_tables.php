<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSettingsTables extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key',100);
            $table->string('group',100);
            $table->text('value');
            $table->boolean('public')->default(false);
            $table->boolean('enumerable')->default(false);
            $table->integer('sort')->default(0);
            $table->enum('type', ['text', 'markdown', 'json', 'image', 'file', 'config'])->default('text');
            $table->timestamps();
            
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('settings');
    }
}
