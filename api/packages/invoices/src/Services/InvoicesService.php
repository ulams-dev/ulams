<?php

namespace EscolaLms\Invoices\Services;

use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Invoices\Services\Contracts\InvoicesServiceContract;
use Illuminate\Database\Eloquent\Collection;
use LaravelDaily\Invoices\Facades\Invoice;
use LaravelDaily\Invoices\Invoice as InvoiceModel;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;

class InvoicesService implements InvoicesServiceContract
{
    public function saveInvoice(Order $order): string
    {
        $invoice = $this->createInvoice($order);
        $invoice->save('public');

        return $invoice->filename;
    }

    public function createInvoice(Order $order): InvoiceModel
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
            ->template('invoice')
            ->filename($name);
    }

    private function prepareCustomer(Order $order): Party
    {
        if ($order->client_taxid) {
            $name = $order->client_company ?? $order->client_name ?? ($order->user->first_name . " " . $order->last_name) ?? '';
        } else {
            $name = $order->client_name ?? $order->client_company ?? ($order->user->first_name . " " . $order->last_name) ?? '';
        }

        return new Party([
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
            $products[] = (new InvoiceItem())
                ->title($item->name ?? $item->title ?? $item->buyable->name ?? $item->buyable->title)
                ->description($item->description ?? '')
                ->pricePerUnit($item->price/100)
                ->taxByPercent($item->tax_rate)
                ->quantity($item->quantity)
                ->discount($item->discount ?? 0);
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
