<?php

use Ulams\Core\Migrations\UlamsMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImageCachesTable extends UlamsMigration
{
    public function up(): void
    {
        Schema::create('image_caches', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('hash_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_caches');
    }
}
