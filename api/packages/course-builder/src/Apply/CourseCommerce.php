<?php

namespace Ulams\CourseBuilder\Apply;

use InvalidArgumentException;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;

/**
 * The commerce step of an apply and of a publish (ADR 0049). After the course exists, a paid course
 * with a confirmed amount gets an inactive product through the configured `CommerceProvider`;
 * publishing the course activates it. A free course never gets a product, and switching a course
 * back to free deactivates the one it had. A currency the provider cannot sell is skipped with a
 * note: the course is never blocked by pricing.
 */
final class CourseCommerce
{
    public function __construct(private readonly CommerceManager $commerce)
    {
    }

    /** @return array{synced:bool,note:?string} */
    public function apply(Session $session, int $courseId): array
    {
        $pricing = (array) ($session->brief['pricing'] ?? ['mode' => 'free']);
        $ref = SellableRef::course($courseId);
        $provider = $this->commerce->provider();
        $link = $this->link($courseId, $provider->key());

        if (($pricing['mode'] ?? 'free') !== 'paid') {
            if ($link !== null && $link->active) {
                $provider->syncProduct($ref, new Price($link->amount_minor, $link->currency), false);
            }

            return ['synced' => false, 'note' => null];
        }
        if (empty($pricing['amountMinor'])) {
            return ['synced' => false, 'note' => 'The course is marked as paid but has no price yet. Confirm a price before publishing.'];
        }
        try {
            $provider->syncProduct($ref, new Price((int) $pricing['amountMinor'], (string) ($pricing['currency'] ?? config('ulams_payments.default_currency', 'USD'))), $link?->active ?? false);
        } catch (InvalidArgumentException $e) {
            return ['synced' => false, 'note' => 'The product was not created: ' . $e->getMessage()];
        }

        return ['synced' => true, 'note' => null];
    }

    /** Called when the course is published: makes its product purchasable. */
    public function activate(Session $session): void
    {
        $pricing = (array) ($session->brief['pricing'] ?? []);
        if (($pricing['mode'] ?? 'free') !== 'paid' || empty($pricing['amountMinor']) || $session->course_id === null) {
            return;
        }
        $this->commerce->provider()->syncProduct(
            SellableRef::course((int) $session->course_id),
            new Price((int) $pricing['amountMinor'], (string) ($pricing['currency'] ?? config('ulams_payments.default_currency', 'USD'))),
            true,
        );
    }

    private function link(int $courseId, string $provider): ?ProductLink
    {
        return ProductLink::query()->where(['sellable_type' => SellableRef::COURSE, 'sellable_id' => $courseId, 'provider' => $provider])->first();
    }
}
