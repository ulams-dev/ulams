<?php

namespace Ulams\Invoices\Tests\Services;

use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\OrderItem;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductProductable;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Core\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Invoices\Services\Contracts\InvoicesServiceContract;
use Ulams\Invoices\Tests\TestCase;
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
