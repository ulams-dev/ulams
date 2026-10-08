<?php

namespace EscolaLms\TemplatesEmail\Database\Seeders;

use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use Illuminate\Database\Seeder;

class TemplatesEmailSeeder extends Seeder
{
    public function run()
    {
        Template::createDefaultTemplatesForChannel(EmailChannel::class);
    }
}
