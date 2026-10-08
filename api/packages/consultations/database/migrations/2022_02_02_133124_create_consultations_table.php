<?php

use EscolaLms\Consultations\Enum\ConsultationStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsultationsTable extends Migration
{

    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('author_id')->unsigned()->nullable();
            $table->integer('base_price')->nullable();
            $table->string('name', 255);
            $table->string('status')->default(ConsultationStatusEnum::DRAFT);
            $table->text('description');
            $table->text('calendar_url')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users')->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
}
