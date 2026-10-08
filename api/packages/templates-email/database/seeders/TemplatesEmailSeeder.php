<?php

namespace Ulams\TemplatesEmail\Database\Seeders;

use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Illuminate\Database\Seeder;

class TemplatesEmailSeeder extends Seeder
{
    public function run()
    {
        Template::createDefaultTemplatesForChannel(EmailChannel::class);
    }
}
