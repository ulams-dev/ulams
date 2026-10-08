<?php

namespace EscolaLms\Categories\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class CategorySortDto implements DtoContract, InstantiateFromRequest
{
    private array $orders;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function toArray(): array
    {
        return [];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->get('orders')
        );
    }
}
