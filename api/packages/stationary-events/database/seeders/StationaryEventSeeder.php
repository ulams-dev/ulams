<?php

namespace Ulams\StationaryEvents\Database\Seeders;

use Ulams\StationaryEvents\Models\StationaryEvent;
use Illuminate\Database\Seeder;

class StationaryEventSeeder extends Seeder
{
    public function run()
    {
        StationaryEvent::factory(10)->create();
    }
}
