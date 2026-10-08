<?php

namespace EscolaLms\TemplatesEmail\Observers;

use EscolaLms\Settings\Models\Setting;
use EscolaLms\TemplatesEmail\Jobs\CompleteGlobalVariableJob;
use Illuminate\Support\Str;

class SettingObserver
{
    public function saved(Setting $setting): void
    {
        $key =  Str::ucfirst($setting->key) .  Str::ucfirst($setting->type);
        $name = '@GlobalSettings' . Str::ucfirst(Str::camel(preg_replace('/[^a-z0-9]+/i', ' ', ($key))));
        CompleteGlobalVariableJob::dispatch($name);
    }
}
