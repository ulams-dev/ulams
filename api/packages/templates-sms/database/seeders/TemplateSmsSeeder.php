<?php

namespace Ulams\TemplatesSms\Database\Seeders;

use Ulams\Templates\Facades\Template;
use Ulams\TemplatesSms\Core\SmsChannel;
use Illuminate\Database\Seeder;

class TemplateSmsSeeder extends Seeder
{
    public function run()
    {
        Template::createDefaultTemplatesForChannel(SmsChannel::class);
    }
}
