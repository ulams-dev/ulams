<?php

namespace Ulams\Cart\Http\Resources;

use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Facades\Shop;
use Ulams\Cart\Models\ProductProductable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductableGenericResource extends JsonResource
{
    protected ?ProductProductable $productProductable = null;

    public function __construct(Productable $productable, ?ProductProductable $productProductable = null)
    {
        assert($productable instanceof Model);
        parent::__construct($productable);
        $this->productProductable = $productProductable;
    }

    public function getProductable(): Productable
    {
        return $this->resource;
    }

    public function getProductProductable(): ?ProductProductable
    {
        return $this->productProductable;
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->getProductable()->getKey(),
            'morph_class' => $this->getProductable()->getMorphClass(),
            'productable_id' => $this->getProductable()->getKey(),
            'productable_type' => Shop::canonicalProductableClass($this->getProductable()->getMorphClass()),
            'quantity' => $this->getProductProductable() ? $this->getProductProductable()->quantity : 1,
            'name' => $this->getProductable()->getName(),
            'description' => $this->getProductable()->getDescription(),
            'position' => $this->getProductProductable()?->position,
        ];
    }
}
