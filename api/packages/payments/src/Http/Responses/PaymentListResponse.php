<?php

namespace EscolaLms\Payments\Http\Responses;

use EscolaLms\Payments\Http\Resources\PaymentCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;

class PaymentListResponse implements Responsable
{
    /** @var Collection|LengthAwarePaginator $collection */
    private $collection;

    /**
     * @param Collection|LengthAwarePaginator $collection
     */
    public function __construct($collection)
    {
        $this->collection = $collection;
    }

    public function toResponse($request)
    {
        return PaymentCollection::make($this->collection)->toResponse($request);
    }
}
