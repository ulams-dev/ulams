<?php

namespace Ulams\TemplatesEmail\Database\Seeders;

use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;
use Illuminate\Database\Seeder;

class TemplatesEmailSeeder extends Seeder
{
    public function run()
    {
        // One default template per language and event (config `seed_locales`: en, pl, zh). An empty list creates
        // a single template without a language, as before per-language templates.
        Template::createDefaultTemplatesForChannel(
            EmailChannel::class,
            (array) config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.seed_locales', [])
        );
    }
}
