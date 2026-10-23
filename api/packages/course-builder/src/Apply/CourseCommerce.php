<?php

namespace Ulams\CourseBuilder\Apply;

use InvalidArgumentException;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Commerce\Models\SellablePrice;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;

/**
 * The commerce step of an apply and of a publish (ADR 0049, 0130). After the course exists, a paid course with a
 * confirmed amount gets a product through the configured `CommerceProvider`; the catalogue sync keeps it disabled
 * while the course is a draft and enables it when the course is published (and disables it again when the course is
 * unpublished). A free course never gets a product, and switching a course back to free takes its product off sale.
 * A currency the provider cannot sell is skipped with a note: the course is never blocked by pricing.
 */
final class CourseCommerce
{
    public function __construct(private readonly CatalogueSync $sync)
    {
    }

    /** @return array{synced:bool,note:?string} */
    public function apply(Session $session, int $courseId): array
    {
        $pricing = (array) ($session->brief['pricing'] ?? ['mode' => 'free']);
        $ref = SellableRef::course($courseId);
        $existing = SellablePrice::query()->where(['sellable_type' => $ref->type, 'sellable_id' => $ref->id])->first();

        if (($pricing['mode'] ?? 'free') !== 'paid') {
            if ($existing !== null && $existing->for_sale) {
                $this->sync->setPrice($ref, $existing->price(), false);
                $this->sync->push($ref);
            }

            return ['synced' => false, 'note' => null];
        }
        if (empty($pricing['amountMinor'])) {
            return ['synced' => false, 'note' => 'The course is marked as paid but has no price yet. Confirm a price before publishing.'];
        }
        $price = new Price((int) $pricing['amountMinor'], (string) ($pricing['currency'] ?? config('ulams_payments.default_currency', 'USD')));
        $this->sync->setPrice($ref, $price, true);
        try {
            $this->sync->push($ref);
        } catch (InvalidArgumentException $e) {
            // the provider cannot sell this price: forget the intent so a later publish does not try again and fail
            if ($existing === null) {
                SellablePrice::query()->where(['sellable_type' => $ref->type, 'sellable_id' => $ref->id])->delete();
            } else {
                $this->sync->setPrice($ref, $existing->price(), $existing->for_sale);
            }

            return ['synced' => false, 'note' => 'The product was not created: ' . $e->getMessage()];
        }

        return ['synced' => true, 'note' => null];
    }

    /** Called when the course is published: its product becomes purchasable (the status change has already queued the same sync). */
    public function activate(Session $session): void
    {
        if ($session->course_id === null) {
            return;
        }
        $this->sync->push(SellableRef::course((int) $session->course_id));
    }
}
