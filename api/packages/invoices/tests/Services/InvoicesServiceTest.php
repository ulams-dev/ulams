<?php

namespace EscolaLms\Invoices\Tests\Services;

use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Models\ProductProductable;
use EscolaLms\Cart\Tests\Mocks\ExampleProductable;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Invoices\Services\Contracts\InvoicesServiceContract;
use EscolaLms\Invoices\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class InvoicesServiceTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    protected InvoicesServiceContract $service;

    private Order $order;
    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoicesServiceContract::class);
        $this->user =  $this->makeStudent();
        $this->order = Order::factory()->for($this->user)->create();
        $products = [
            ...Product::factory()->count(5)->create(),
        ];
        foreach ($products as $product) {
            $productable = ExampleProductable::factory()->create();
            $product->productables()->save(new ProductProductable([
                'productable_type' => ExampleProductable::class,
                'productable_id' => $productable->getKey()
            ]));
        }

        foreach ($products as $product) {
            $orderItem = new OrderItem();
            $orderItem->buyable()->associate($product);
            $orderItem->quantity = 1;
            $orderItem->order_id = $this->order->getKey();
            $orderItem->save();
        }
    }

    public function testSaveInvoices(): void
    {
        $response = $this->service->saveInvoice($this->order);

        $this->assertFileExists(storage_path('app/public').'/'.$response);

        unlink(storage_path('app/public').'/'.$response);

        $this->assertFileDoesNotExist(storage_path('app/public').'/'.$response);
    }
}
