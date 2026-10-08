<?php

namespace EscolaLms\Settings\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use EscolaLms\Settings\Models\Setting;

interface SettingsRepositoryContract extends BaseRepositoryContract
{

    public function findOrCreate(array $data): Setting;
}
