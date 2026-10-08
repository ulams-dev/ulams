<?php

namespace EscolaLms\Reports\Metrics;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Models\Cart;
use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Models\ProductProductable;
use EscolaLms\Courses\Models\Course;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoursesTopSellingMetric extends AbstractCoursesMetric
{
    public function calculate(?int $limit = null): Collection
    {
        $courseTable = (new Course())->getTable();
        $orderItemTable = (new OrderItem())->getTable();
        $orderTable = (new Order())->getTable();
        $productTable = (new Product())->getTable();
        $productProductableTable = (new ProductProductable())->getTable();

        return Course::dontCache()->selectRaw($courseTable . '.id, ' . $courseTable . ".title as label, SUM(" . $orderItemTable . '.quantity) as value')
            ->leftJoin($productProductableTable, fn (JoinClause $join) => $join->where($productProductableTable . '.productable_id', '=', DB::raw($courseTable . '.id'))->where($productProductableTable . '.productable_type', '=', (new Course)->getMorphClass()))
            ->leftJoin($productTable, fn (JoinClause $join) => $join->where($productTable . '.id', '=', DB::raw($productProductableTable . '.product_id')))
            ->leftJoin($orderItemTable, fn (JoinClause $join) => $join->where($orderItemTable . '.buyable_id', '=', DB::raw($productTable . '.id'))->where($orderItemTable . '.buyable_type', '=', (new Product())->getMorphClass()))
            ->rightJoin($orderTable, fn (JoinClause $join) => $join->where($orderTable . '.id', '=', DB::raw($orderItemTable . '.order_id'))->where($orderTable . '.status', '=', OrderStatus::PAID))
            ->groupBy($courseTable . '.id', $courseTable . '.title')
            ->whereNotNull($courseTable . '.id')
            ->orderBy('value', 'DESC')
            ->take($this->getLimit($limit))
            ->get(['id', 'label', 'value']);
    }

    public function requiredPackage(): string
    {
        return 'escolalms/courses & escolalms/cart';
    }

    public static function requiredPackageInstalled(): bool
    {
        return class_exists(Course::class) && class_exists(Cart::class);
    }
}
