<?php
namespace Ulams\Invoices\Services\Contracts;

use Ulams\Cart\Models\Order;
use Ulams\Invoices\Invoice\Invoice;

interface InvoicesServiceContract
{
    public function saveInvoice(Order $order): string;

    public function createInvoice(Order $order): Invoice;
}
