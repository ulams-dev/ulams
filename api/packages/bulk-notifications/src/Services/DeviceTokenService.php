<?php

namespace EscolaLms\BulkNotifications\Services;

use EscolaLms\BulkNotifications\Dtos\CreateDeviceTokenDto;
use EscolaLms\BulkNotifications\Models\DeviceToken;
use EscolaLms\BulkNotifications\Repositories\Contracts\DeviceTokenRepositoryContract;
use EscolaLms\BulkNotifications\Services\Contracts\DeviceTokenServiceContract;

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
