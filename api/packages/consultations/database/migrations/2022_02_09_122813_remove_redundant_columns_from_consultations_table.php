<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveRedundantColumnsFromConsultationsTable extends Migration
{

    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('duration')->nullable();
        });
    }
}
