<?php

namespace EscolaLms\Pages\Database\Seeders;

use Illuminate\Database\Seeder;
use EscolaLms\Pages\Models\Page;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database Seeders.
     *
     * @return void
     */
    public function run()
    {
        Page::factory()
            ->count(10)
            ->create();
    }
}
