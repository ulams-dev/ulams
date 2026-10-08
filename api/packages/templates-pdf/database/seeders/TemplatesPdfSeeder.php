<?php

namespace Ulams\TemplatesPdf\Database\Seeders;

use Ulams\Templates\Facades\Template;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Illuminate\Database\Seeder;

class TemplatesPdfSeeder extends Seeder
{
    public function run()
    {
        Template::createDefaultTemplatesForChannel(PdfChannel::class);
    }
}
