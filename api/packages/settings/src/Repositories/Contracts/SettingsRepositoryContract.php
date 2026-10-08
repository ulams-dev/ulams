<?php

namespace Ulams\Settings\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Settings\Models\Setting;

interface SettingsRepositoryContract extends BaseRepositoryContract
{

    public function findOrCreate(array $data): Setting;
}
