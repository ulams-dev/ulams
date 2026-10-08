<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingsColumnToConsultationsTable extends Migration
{

    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('duration')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
}
