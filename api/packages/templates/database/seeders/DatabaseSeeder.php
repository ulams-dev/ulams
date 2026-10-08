<?php

namespace EscolaLms\Templates\Database\Seeders;

use Illuminate\Database\Seeder;
use EscolaLms\Templates\Models\Template;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database Seeders.
     *
     * @return void
     */
    public function run()
    {
        Template::factory()
            ->count(10)
            ->create();
    }
}
