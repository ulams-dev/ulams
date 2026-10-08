<?php

namespace Database\Seeders;

use Ulams\Auth\Database\Seeders\UserGroupsSeeder;
use Ulams\Cart\Database\Seeders\OrdersSeeder;
use Ulams\Categories\Database\Seeders\CategoriesSeeder;
use Ulams\Courses\Database\Seeders\CoursesSeeder;
use Ulams\Courses\Database\Seeders\ProgressSeeder;
use Ulams\Pages\Database\Seeders\DatabaseSeeder as PagesDatabaseSeeder;
use Ulams\Payments\Database\Seeders\PaymentsSeeder;
use Ulams\Settings\Database\Seeders\DatabaseSeeder as SettingsDatabaseSeeder;
use Ulams\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder;
use Ulams\Tags\Database\Seeders\TagsSeeder;
use Ulams\Webinar\Database\Seeders\WebinarsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // first populate roles & permissions
        $this->call(PermissionsSeeder::class);

        // check is this hasn't been called already 
        if (\App\Models\User::all()->count() > 1) {
            echo "Already seeded! \n\n\n";
            return;
        }

        // create users
        $this->call(UserTableSeeder::class);

        // then populate content
        $this->call(CategoriesSeeder::class);
        $this->call(CoursesSeeder::class);
        $this->call(OrdersSeeder::class);
        $this->call(ProgressSeeder::class);
        $this->call(PaymentsSeeder::class);
        $this->call(PagesDatabaseSeeder::class);
        $this->call(SettingsDatabaseSeeder::class);
        $this->call(UserGroupsSeeder::class);
        $this->call(ConsultationsSeeder::class);
        $this->call(TemplatesEmailSeeder::class);
        $this->call(TagsSeeder::class);
        $this->call(WebinarsSeeder::class);
    }
}
