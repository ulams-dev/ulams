<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use Illuminate\Database\Eloquent\Builder;

// TODO: Abstract this as Productable People Bought so that any productable can be checked, and not only Courses
class PeopleBought extends AbstractCourseStat
{
    public function calculate(): int
    {
        return $this->course->users()->count();
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(Product::class) && parent::requiredPackagesInstalled();
    }

    // this will not include people that got Course assigned manually using Course Access Admin API endpoints
    public function calculateReallyBought(): int
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
                    : $query->whereHas(
                        'productables',
                        fn (Builder $subquery) => $subquery->where('productable_type', $this->course->getMorphClass())->where('productable_id', $this->course->getKey())
                    )
            )->count();
    }
}
