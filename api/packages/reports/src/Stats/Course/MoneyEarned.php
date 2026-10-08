<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Enums\ProductType;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

// TODO: Abstract this as Productable Money Earned so that any productable can be checked, and not only Courses
class MoneyEarned extends AbstractCourseStat
{
    public function calculate(): int
    {
        return $this->calculateSingleProductValue() + $this->calculateBundleProductValue();
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(Product::class) && parent::requiredPackagesInstalled();
    }

    private function calculateSingleProductValue(): int
    {
        return OrderItem::whereHas(
            'order',
            fn (Builder $query) => $query->where('status', OrderStatus::PAID)
        )
            ->where('buyable_type', Product::class)
            ->whereHas(
                'buyable',
                fn (Builder $query) => $query->getModel()->getMorphClass() !== Product::class
                    ? $query
                    : $query->where('type', ProductType::SINGLE)
                    ->whereHas(
                        'productables',
                        fn (Builder $subquery) => $subquery->where('productable_type', $this->course->getMorphClass())->where('productable_id', $this->course->getKey())
                    )
            )->sum(DB::raw('price * quantity')) ?? 0;
    }

    private function calculateBundleProductValue(): int
    {
        /** @var Collection $orderItems */
        $orderItems = OrderItem::whereHas(
            'order',
            fn (Builder $query) => $query->where('status', OrderStatus::PAID)
        )
            ->where('buyable_type', Product::class)
            ->whereHas(
                'buyable',
                fn (Builder $query) => $query->getModel()->getMorphClass() !== Product::class
                    ? $query
                    : $query->where('type', ProductType::BUNDLE)
                    ->whereHas(
                        'productables',
                        fn (Builder $subquery) => $subquery->where('productable_type', $this->course->getMorphClass())->where('productable_id', $this->course->getKey())
                    )
                    ->withCount('productables')
            )->with('buyable', 'buyable.productables')->get();

        // This calculates value as if every part of a product bundle represented equal share of product total price
        // For example, if product contains 3 different courses and 2 hours of consultations (quantity = 2), single course is worth 1/5 of product total price
        // TODO: try to write this as (Raw) SQL Query
        return $orderItems->reduce(function (int $sum, OrderItem $orderItem) {
            $product = $orderItem->buyable;
            assert($product instanceof Product);
            $productablesCount = $product->productables->sum('quantity');
            $courseCount = optional($orderItem->buyable->productables->where('productable_type', $this->course->getMorphClass())->where('productable_id', $this->course->getKey())->first())->quantity;
            if ($courseCount > 0 && $productablesCount > 0) {
                $price = ($orderItem->subtotal * $courseCount / $productablesCount);
            } else {
                $price = 0;
            }
            return $sum + $price;
        }, 0);
    }
}
