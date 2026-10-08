<?php

namespace EscolaLms\BulkNotifications\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class CreateDeviceTokenDto implements DtoContract, InstantiateFromRequest
{
    private string $token;

    private int $userId;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->userId = auth()->id();
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function toArray(): array
    {
        return [
            'token' => $this->getToken(),
            'user_id' => $this->getUserId(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('token'),
        );
    }
}
