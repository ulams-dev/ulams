<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskNotesTable extends Migration
{
    public function up(): void
    {
        Schema::create('task_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('note');
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_notes');

        Schema::table('tasks', function (Blueprint $table) {
            $table->text('note')->nullable();
        });
    }
}
