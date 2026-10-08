<?php

namespace Ulams\Courses\Database\Seeders;

use Ulams\TopicTypes\Database\Seeders\CoursesWithTopicSeeder;
use Illuminate\Database\Seeder;

class CoursesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(CoursesWithTopicSeeder::class);
    }
}
