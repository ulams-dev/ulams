<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeNameColumnsForConsultationsTable extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->renameColumn('started_at', 'active_from');
            $table->renameColumn('finished_at', 'active_to');
            $table->string('duration')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->renameColumn('active_from', 'started_at');
            $table->renameColumn('active_to', 'finished_at');
            $table->dropColumn('duration');
        });
    }
}
