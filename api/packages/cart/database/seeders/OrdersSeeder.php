<?php

namespace Ulams\Cart\Database\Seeders;

use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\OrderItem;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\User;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Models\User as ModelsUser;
use Ulams\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\WithFaker;

class OrdersSeeder extends Seeder
{
    use WithFaker;

    public function run()
    {
        $student = User::role(UserRole::STUDENT)->first();
        if (!$student) {
            // This will only be called if Users were not seeded before CartSeeder was Called
            $users = User::factory()->count(10)->create();
            /** @var User $user */
            foreach ($users as $user) {
                $user->assignRole(UserRole::STUDENT);
            }
        }

        $students = User::role(UserRole::STUDENT)->take(10)->get();

        /** @var User $student */
        foreach ($students as $student) {
            /** @var Collection $products */
            $products = Product::inRandomOrder()->take(rand(1, 5))->get();
            if ($products->count() === 0) {
                $this->call(ProductsSeeder::class);
                $products = Product::inRandomOrder()->take(rand(1, 5))->get();
            }
            $price = $products->reduce(fn ($acc, Product $product) => $acc + $product->getBuyablePrice(), 0);

            /** @var Order $order */
            $order = Order::factory()->has(Payment::factory()->state([
                'amount' => $price,
                'user_id' => $student->getKey(),
            ]))
                ->afterCreating(
                    fn (Order $order) => $order->items()->saveMany(
                        $products->map(
                            function (Product $product) {
                                return OrderItem::query()->make([
                                    'quantity' => 1,
                                    'buyable_id' => $product->getKey(),
                                    'buyable_type' => $product->getMorphClass(),
                                ]);
                            }
                        )
                    )
                )->create([
                    'user_id' => $student->getKey(),
                    'total' => $price,
                    'subtotal' => $price,
                ]);

            $products->each(fn (Product $product) => app(ProductServiceContract::class)->attachProductToUser($product, $student));
        }
    }
}
