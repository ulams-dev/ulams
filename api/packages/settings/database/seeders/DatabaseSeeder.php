<?php

namespace EscolaLms\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use EscolaLms\Settings\Models\Setting;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        Setting::firstOrCreate([
            'group' => 'currencies',
            'key' => 'default',
            'value' => 'USD',
            'public' => true,
            'enumerable' => true,
            'type' => 'text'
        ]);

        Setting::firstOrCreate([
            'group' => 'currencies',
            'key' => 'enum',
            'value' => json_encode(["USD", "EUR"]),
            'public' => true,
            'enumerable' => true,
            'type' => 'json'
        ]);

        Setting::firstOrCreate([
            'group' => 'config',
            'key' => 'app.name',
            'value' => "app.name",
            'public' => true,
            'enumerable' => true,
            'type' => 'config'
        ]);

        Setting::firstOrCreate([
            'group' => 'config',
            'key' => 'app.env',
            'value' => "app.env",
            'public' => true,
            'enumerable' => true,
            'type' => 'config'
        ]);

        Setting::firstOrCreate([
            'group' => 'stripe',
            'key' => 'publishable_key',
            'value' => "pk_test_51Ig8icJ9tg9t712TnCR6sKY9OXwWoFGWH4ERZXoxUVIemnZR0B6Ei0MzjjeuWgOzLYKjPNbT8NbG1ku1T2pGCP4B00GnY0uusI",
            'public' => true,
            'enumerable' => true,
            'type' => 'text'
        ]);

        Setting::firstOrCreate([
            'group' => 'texts',
            'key' => 'cookie',
            'value' => "
## Cookie consent
Ordered

1. Lorem ipsum dolor sit amet
2. Consectetur adipiscing elit
3. Integer molestie lorem at massa

            ",
            'public' => true,
            'enumerable' => true,
            'type' => 'markdown'
        ]);

        Setting::firstOrCreate([
            'group' => 'images',
            'key' => 'tutor',
            'value' => "tutor_avatar.jpg",
            'public' => true,
            'enumerable' => true,
            'type' => 'image'
        ]);

        Setting::firstOrCreate([
            'group' => 'files',
            'key' => 'tutor',
            'value' => "tutor_avatar.jpg",
            'public' => true,
            'enumerable' => true,
            'type' => 'file'
        ]);
    }
}
