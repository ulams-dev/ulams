<?php

namespace EscolaLms\BulkNotifications\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class OrderDto implements DtoContract, InstantiateFromRequest
{
    private string $orderBy;

    private string $orderDirection;

    public function __construct(?string $orderBy, ?string $orderDirection)
    {
        $this->orderBy = $orderBy ?? 'id';
        $this->orderDirection = $orderDirection ?? 'desc';
    }

    public function getOrderBy(): string
    {
        return $this->orderBy;
    }

    public function getOrderDirection(): string
    {
        return $this->orderDirection;
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new self($request->get('order_by'), $request->get('order'));
    }

    public function toArray(): array
    {
        return [
            'order_by' => $this->getOrderBy(),
            'order' => $this->getOrderDirection()
        ];
    }
}
