<?php
namespace EscolaLms\Invoices\Services\Contracts;

use EscolaLms\Cart\Models\Order;
use LaravelDaily\Invoices\Invoice;

interface InvoicesServiceContract
{
    public function saveInvoice(Order $order): string;

    public function createInvoice(Order $order): Invoice;
}
