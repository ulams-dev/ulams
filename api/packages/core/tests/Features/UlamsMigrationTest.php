<?php

namespace Ulams\Core\Tests\Features;

use Ulams\Core\Migrations\UlamsMigration;
use Ulams\Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class UlamsMigrationTest extends TestCase
{
    public function testCreate(): void
    {
        $migrate = new UlamsMigration();
        Schema::dropIfExists('abc_test');

        $response = $migrate->create('abc_test', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        $this->assertNull($response);

        Schema::dropIfExists('abc_test');
    }

    public function testDuplicateTable(): void
    {
        $migrate = new UlamsMigration();
        Schema::dropIfExists('abc_test');

        $response = $migrate->create('abc_test', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
        DB::insert('insert into abc_test (id, title) values (?, ?)', [1, 'test']);

        $this->assertNull($response);
        $this->expectException(RuntimeException::class);

        $migrate->create('abc_test', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::dropIfExists('abc_test');
    }
}
