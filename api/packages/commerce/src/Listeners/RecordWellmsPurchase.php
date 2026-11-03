<?php

namespace Ulams\Commerce\Listeners;

use Illuminate\Support\Facades\Log;
use Ulams\Cart\Events\ProductAttached;
use Ulams\Cart\Events\ProductBought;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductProductable;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;

/**
 * Shadow mode (ADR 0130, section 6.3): the Wellms cart keeps granting access exactly as before (it attaches the
 * course to the user itself), and these listeners record the same purchase as a `wellms_order` entitlement so the
 * entitlement table stays complete while the cart and Sylius run side by side.
 *
 * - `ProductBought` (before the cart attaches): one entitlement per course in the product, keyed
 *   `wellms:order_item:<line>:course:<course>`, open-ended, recorded without touching `course_user` (the cart does);
 * - `ProductAttached` (after): the cart has written `course_user.end_date` (the end of a subscription), so the
 *   purchase's entitlements are aligned to it. In shadow mode the cart leads and the entitlements follow.
 *
 * A failure here is logged and never blocks the purchase; the nightly parity check reports what was missed.
 */
class RecordWellmsPurchase
{
    public function __construct(private readonly EntitlementService $entitlements)
    {
    }

    public function handleBought(ProductBought $event): void
    {
        if (!config('commerce.shadow.wellms_entitlements')) {
            return;
        }
        $product = $event->getProduct();
        $order = $event->getOrder();
        $line = $order->items->first(fn ($item) => (int) $item->buyable_id === (int) $product->getKey() && $item->getRawOriginal('buyable_type') === $product->getMorphClass());
        if ($line === null) {
            return;
        }
        foreach ($this->courseIds($product) as $courseId) {
            try {
                $this->entitlements->grantCourse(
                    (int) $event->getUser()->getKey(),
                    $courseId,
                    Entitlement::SOURCE_WELLMS_ORDER,
                    "wellms:order_item:{$line->getKey()}:course:{$courseId}",
                    ['provider' => 'wellms', 'order_id' => $order->getKey(), 'order_item_id' => $line->getKey(), 'product_id' => $product->getKey()],
                    project: false, // the cart attaches the course itself, with its own events
                );
            } catch (\Throwable $e) {
                Log::error('Shadow entitlement for a Wellms purchase failed', ['order' => $order->getKey(), 'course' => $courseId, 'error' => $e->getMessage()]);
                report($e);
            }
        }
    }

    public function handleAttached(ProductAttached $event): void
    {
        if (!config('commerce.shadow.wellms_entitlements')) {
            return;
        }
        $userId = (int) $event->getUser()->getKey();
        foreach ($this->courseIds($event->getProduct()) as $courseId) {
            try {
                $end = CourseUserPivot::query()->where('user_id', $userId)->where('course_id', $courseId)->first()?->end_date;
                Entitlement::query()
                    ->where('user_id', $userId)->where('subject_type', Entitlement::SUBJECT_COURSE)->where('subject_id', $courseId)
                    ->where('source_type', Entitlement::SOURCE_WELLMS_ORDER)->where('status', Entitlement::STATUS_ACTIVE)
                    ->where('source_key', 'like', 'wellms:order_item:%')
                    ->get()
                    ->each(function (Entitlement $e) use ($end): void {
                        if ($e->valid_until?->getTimestamp() !== $end?->getTimestamp()) {
                            $e->forceFill(['valid_until' => $end])->save();
                        }
                    });
            } catch (\Throwable $e) {
                Log::error('Aligning the shadow entitlement of a Wellms purchase failed', ['product' => $event->getProduct()->getKey(), 'course' => $courseId, 'error' => $e->getMessage()]);
                report($e);
            }
        }
    }

    /** @return list<int> the courses inside a product */
    private function courseIds(Product $product): array
    {
        $type = (new Course())->getMorphClass();

        return ProductProductable::query()->where('product_id', $product->getKey())->where('productable_type', $type)
            ->pluck('productable_id')->map(fn ($id) => (int) $id)->all();
    }
}
