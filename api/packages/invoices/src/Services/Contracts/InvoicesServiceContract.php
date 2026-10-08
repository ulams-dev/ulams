<?php
namespace Ulams\Invoices\Services\Contracts;

use Ulams\Cart\Models\Order;
use LaravelDaily\Invoices\Invoice;

interface InvoicesServiceContract
{
    public function saveInvoice(Order $order): string;

    public function createInvoice(Order $order): Invoice;
}
