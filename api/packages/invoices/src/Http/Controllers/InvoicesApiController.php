<?php

namespace Ulams\Invoices\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Invoices\Http\Controllers\Swagger\InvoicesApiContract;
use Ulams\Invoices\Http\Requests\InvoicesReadRequest;
use Ulams\Invoices\Services\Contracts\InvoicesServiceContract;
use Exception;
use Illuminate\Http\Response;

class InvoicesApiController extends UlamsBaseController implements InvoicesApiContract
{
    private InvoicesServiceContract $invoicesService;

    public function __construct(
        InvoicesServiceContract $invoicesService
    ) {
        $this->invoicesService = $invoicesService;
    }

    /**
     * @throws Exception
     */
    public function read(InvoicesReadRequest $request): Response
    {
        return $this->invoicesService->createInvoice($request->getOrder())->stream();
    }
}
