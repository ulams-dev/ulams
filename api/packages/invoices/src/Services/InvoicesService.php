<?php

namespace Ulams\Invoices\Services;

use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\OrderItem;
use Ulams\Invoices\Services\Contracts\InvoicesServiceContract;
use Ulams\Invoices\Invoice\Invoice;
use Ulams\Invoices\Invoice\InvoiceItem;
use Ulams\Invoices\Invoice\InvoiceParty;
use Illuminate\Database\Eloquent\Collection;

class InvoicesService implements InvoicesServiceContract
{
    public function saveInvoice(Order $order): string
    {
        $invoice = $this->createInvoice($order);
        $invoice->save('public');

        return $invoice->getFilename();
    }

    public function createInvoice(Order $order): Invoice
    {
        $customer = $this->prepareCustomer($order);
        $items = $this->prepareProducts($order->items);
        $notes = $this->prepareNote($order);
        $name = $this->filter_filename($customer->name . '_fv_' . $order->id);

        return Invoice::make()
            ->name($name)
            ->status(__($order->status_name))
            ->buyer($customer)
            ->sequence($order->getKey())
            ->date($order->created_at)
            ->addItems($items)
            ->notes($notes)
            ->filename($name);
    }

    private function prepareCustomer(Order $order): InvoiceParty
    {
        if ($order->client_taxid) {
            $name = $order->client_company ?? $order->client_name ?? ($order->user->first_name . " " . $order->last_name) ?? '';
        } else {
            $name = $order->client_name ?? $order->client_company ?? ($order->user->first_name . " " . $order->last_name) ?? '';
        }

        return InvoiceParty::fromArray([
            'name' => $name,
            'vat' => $order->client_taxid ?? '',
            'address' => $order->client_street . ' ' . $order->client_postal . ' ' . $order->client_city,
            'custom_fields' => [
                'country' => $order->client_country,
                'order number' => $order->id,
            ],
        ]);
    }

    private function prepareProducts(Collection $items): array
    {
        $products = [];
        /** @var OrderItem $item */
        foreach ($items as $item) {
            $products[] = new InvoiceItem(
                title: (string) ($item->name ?? $item->title ?? $item->buyable->name ?? $item->buyable->title),
                pricePerUnit: $item->price / 100,
                quantity: (float) $item->quantity,
                discount: (float) ($item->discount ?? 0),
                taxPercentage: (float) $item->tax_rate,
                description: $item->description ?: null,
            );
        }

        return $products;
    }

    private function prepareNote(Order $order): string
    {
        return $order->note ?? '';
    }

    private function filter_filename(string $name): string
    {
        $name = str_replace(array_merge(
            array_map('chr', range(0, 31)),
            array('<', '>', ':', '"', '/', '\\', '|', '?', '*', ' ')
        ), '', $name);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $name = mb_strcut(pathinfo($name, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($name)) . ($ext ? '.' . $ext : '');

        return $name;
    }
}
