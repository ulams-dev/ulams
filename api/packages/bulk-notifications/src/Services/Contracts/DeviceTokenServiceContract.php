<?php

namespace EscolaLms\BulkNotifications\Services\Contracts;

use EscolaLms\BulkNotifications\Dtos\CreateDeviceTokenDto;
use EscolaLms\BulkNotifications\Models\DeviceToken;

interface DeviceTokenServiceContract
{
    public function create(CreateDeviceTokenDto $dto): DeviceToken;
}
