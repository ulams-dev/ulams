<?php

namespace Ulams\Cart\Http\Requests;

use Ulams\Cart\Models\Product;
use Illuminate\Support\Facades\Gate;

class ProductRecursiveCancelRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return Gate::allows('cancelProductRecursive', Product::class);
    }
}
