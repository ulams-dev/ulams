<?php

namespace EscolaLms\Invoices\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Invoices\Http\Controllers\Swagger\InvoicesApiContract;
use EscolaLms\Invoices\Http\Requests\InvoicesReadRequest;
use EscolaLms\Invoices\Services\Contracts\InvoicesServiceContract;
use Exception;
use Illuminate\Http\Response;

class InvoicesApiController extends EscolaLmsBaseController implements InvoicesApiContract
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
