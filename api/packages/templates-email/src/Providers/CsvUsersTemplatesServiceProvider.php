<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\CsvUsers\Events\UlamsImportedNewUserTemplateEvent;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\CsvUsers\ImportedNewUserVariables;
use Illuminate\Support\ServiceProvider;

class CsvUsersTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(UlamsImportedNewUserTemplateEvent::class, EmailChannel::class, ImportedNewUserVariables::class);
    }
}
