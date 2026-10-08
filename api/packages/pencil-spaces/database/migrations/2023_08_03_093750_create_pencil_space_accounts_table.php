<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePencilSpaceAccountsTable extends Migration
{
    public function up(): void
    {
        Schema::create('pencil_space_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pencil_space_id');
            $table->string('pencil_space_email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencil_space_accounts');
    }
}
