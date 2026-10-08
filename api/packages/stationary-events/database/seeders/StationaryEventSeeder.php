<?php

namespace EscolaLms\StationaryEvents\Database\Seeders;

use EscolaLms\StationaryEvents\Models\StationaryEvent;
use Illuminate\Database\Seeder;

class StationaryEventSeeder extends Seeder
{
    public function run()
    {
        StationaryEvent::factory(10)->create();
    }
}
