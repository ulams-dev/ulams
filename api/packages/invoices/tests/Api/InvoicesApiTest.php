<?php

namespace Ulams\Invoices\Tests\Api;

use Ulams\Cart\Database\Seeders\CartPermissionSeeder;
use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\OrderItem;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductProductable;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Core\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Invoices\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class InvoicesApiTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    private Order $order;
    private User $admin;
    private User $user;
    private User $user2;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(CartPermissionSeeder::class);
        $this->admin =  $this->makeAdmin();
        $this->user =  $this->makeStudent();
        $this->user2 =  $this->makeStudent();
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

    public function testCanReadInvoices(): void
    {
        $response = $this->actingAs($this->user, 'api')->getJson('api/order-invoices/'.$this->order->getKey());

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function testCannotFindMissingOrder(): void
    {
        $response = $this->actingAs($this->user, 'api')->getJson('api/order-invoices/999999');

        $response->assertStatus(404);
    }

    public function testAdminCanReadInvoicesByOrderId(): void
    {
        $response = $this->actingAs($this->admin, 'api')->getJson('api/order-invoices/'.$this->order->getKey());

        $response->assertOk();
    }

    public function testOtherUsersCannotReadInvoicesOtherUser(): void
    {
        $response = $this->actingAs($this->user2, 'api')->getJson('api/order-invoices/'.$this->order->getKey());

        $response->assertForbidden();
    }

    public function testGuestCannotReadInvoices(): void
    {
        $response = $this->getJson('api/order-invoices/'.$this->order->getKey());

        $response->assertForbidden();
    }
}
