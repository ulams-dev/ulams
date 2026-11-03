<?php

namespace Ulams\Commerce\Tests\Support;

use Illuminate\Support\Facades\DB;
use Ulams\Cart\Enums\OrderStatus;
use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\OrderItem;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductProductable;
use Ulams\Cart\Models\ProductUser;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Models\User;

/** Wellms cart rows (products, orders, subscriptions) around real courses, written the way the cart writes them. */
trait WellmsFixtures
{
    /** @param list<Course> $courses */
    protected function product(array $courses, array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);
        foreach ($courses as $course) {
            ProductProductable::query()->create(['product_id' => $product->getKey(), 'productable_type' => 'course', 'productable_id' => $course->getKey(), 'quantity' => 1]);
        }

        return $product;
    }

    protected function order(User $user, Product $product, int $status = OrderStatus::PAID): OrderItem
    {
        $order = Order::query()->create(['user_id' => $user->getKey(), 'status' => $status, 'total' => 0, 'subtotal' => 0, 'tax' => 0]);

        return OrderItem::query()->create(['order_id' => $order->getKey(), 'buyable_type' => 'product', 'buyable_id' => $product->getKey(), 'quantity' => 1]);
    }

    protected function ownsProduct(User $user, Product $product, $endDate = null): ProductUser
    {
        return ProductUser::query()->create(['user_id' => $user->getKey(), 'product_id' => $product->getKey(), 'quantity' => 1, 'end_date' => $endDate]);
    }

    /** What `Course::attachToUser` does for a purchase: a `course_user` row with the product's end date. */
    protected function cartAttach(User $user, Course $course, $endDate = null): void
    {
        DB::table('course_user')->updateOrInsert(
            ['user_id' => $user->getKey(), 'course_id' => $course->getKey()],
            ['end_date' => $endDate, 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
