<?php

namespace Ulams\BulkNotifications\Services\Contracts;

use Ulams\BulkNotifications\Dtos\CreateDeviceTokenDto;
use Ulams\BulkNotifications\Models\DeviceToken;

interface DeviceTokenServiceContract
{
    public function create(CreateDeviceTokenDto $dto): DeviceToken;
}
