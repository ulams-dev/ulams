<?php

namespace Ulams\Cart\Http\Requests;

use Ulams\Cart\Models\Product;

class PaymentProductRequest extends PaymentRequest
{

    public function getProductId(): int
    {
        return (int) $this->route('id');
    }

    public function getProduct(): Product
    {
        /** @var Product $product */
        $product = Product::findOrFail($this->getProductId());
        return $product;
    }
}
