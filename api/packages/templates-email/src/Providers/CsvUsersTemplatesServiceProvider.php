<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\CsvUsers\Events\EscolaLmsImportedNewUserTemplateEvent;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\CsvUsers\ImportedNewUserVariables;
use Illuminate\Support\ServiceProvider;

class CsvUsersTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(EscolaLmsImportedNewUserTemplateEvent::class, EmailChannel::class, ImportedNewUserVariables::class);
    }
}
