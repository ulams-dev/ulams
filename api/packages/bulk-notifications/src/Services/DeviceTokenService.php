<?php

namespace Ulams\BulkNotifications\Services;

use Ulams\BulkNotifications\Dtos\CreateDeviceTokenDto;
use Ulams\BulkNotifications\Models\DeviceToken;
use Ulams\BulkNotifications\Repositories\Contracts\DeviceTokenRepositoryContract;
use Ulams\BulkNotifications\Services\Contracts\DeviceTokenServiceContract;

class DeviceTokenService implements DeviceTokenServiceContract
{

    public function __construct(private DeviceTokenRepositoryContract $deviceTokenRepository)
    {
    }

    public function create(CreateDeviceTokenDto $dto): DeviceToken
    {
        $deviceToken = $this->deviceTokenRepository->findToken($dto->getToken());

        if (!$deviceToken) {
            /** @var DeviceToken $deviceToken */
            $deviceToken = $this->deviceTokenRepository->create($dto->toArray());
        }
        else {
            /** @var DeviceToken $deviceToken */
            $deviceToken = $this->deviceTokenRepository->update(['user_id' => $dto->getUserId()], $deviceToken->getKey());
        }

        return $deviceToken;
    }

}
