<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PostCoursesSeeder;
use Ulams\Scorm\Database\Seeders\DatabaseSeeder as ScormSeeder;

class FullDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(DatabaseSeeder::class);
        // TODO: H5P libraries and demo content are imported by the H5P service, not by Laravel:
        // run `make h5p-seed` (docker compose exec h5p node dist/cli/seed.js --samples) before
        // this seeder so PostCoursesSeeder can attach H5P topics. See api/h5p/README.md "Seeding".
        $this->call(ScormSeeder::class);
        $this->call(PostCoursesSeeder::class);

        // real data
        // $this->call(CyfrowyDobrostanSeeder::class);
    }
}
