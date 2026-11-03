<?php

namespace Ulams\Tenancy\Services;

use InvalidArgumentException;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantDerivation;

/**
 * Tenants created by another tenant for a purpose, with the limits and the record (ADR 0141, amendment): one tenant per
 * (parent, purpose, reference), at most `ulams_tenancy.derived.max_per_parent` active ones per parent, and no chains
 * (a derived tenant cannot itself be a parent). The ledger row is written before provisioning starts, so a crash or a retry
 * resumes the same tenant instead of making another.
 */
class TenantDerivations
{
    public const PURPOSE_PATTERN = '/^[a-z][a-z0-9_]{1,31}$/';

    /**
     * Registers (or finds) the derivation of `$slug`. Returns the existing row for the same reference.
     *
     * @throws InvalidArgumentException when the parent does not exist or is itself derived, the reference is used by another
     *                                  tenant, the slug belongs to another derivation, or the parent is over its quota
     */
    public function register(string $slug, string $parentSlug, string $purpose, string $reference, ?string $template, ?string $requestedBy): TenantDerivation
    {
        if (!preg_match(self::PURPOSE_PATTERN, $purpose)) {
            throw new InvalidArgumentException('The purpose is 2 to 32 lowercase letters, digits and underscores, starting with a letter.');
        }
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 128) {
            throw new InvalidArgumentException('The reference is 1 to 128 characters.');
        }
        if (!Tenant::query()->where('slug', $parentSlug)->exists()) {
            throw new InvalidArgumentException("The parent tenant '{$parentSlug}' does not exist.");
        }
        if (TenantDerivation::query()->where('tenant_slug', $parentSlug)->where('status', TenantDerivation::ACTIVE)->exists()) {
            throw new InvalidArgumentException("'{$parentSlug}' was itself created for another tenant and cannot create tenants.");
        }

        $existing = TenantDerivation::query()->where('parent_slug', $parentSlug)->where('purpose', $purpose)->where('reference', $reference)->first();
        if ($existing !== null && $existing->status === TenantDerivation::ACTIVE) {
            if ($existing->tenant_slug !== $slug) {
                throw new InvalidArgumentException("This reference already has the tenant '{$existing->tenant_slug}'.");
            }

            return $existing;
        }
        if (TenantDerivation::query()->where('tenant_slug', $slug)->where('status', TenantDerivation::ACTIVE)->exists()) {
            throw new InvalidArgumentException("The tenant '{$slug}' was created for another purpose.");
        }
        $max = (int) config('ulams_tenancy.derived.max_per_parent', 5);
        $active = TenantDerivation::query()->where('parent_slug', $parentSlug)->where('status', TenantDerivation::ACTIVE)->count();
        if ($active >= $max) {
            throw new InvalidArgumentException("'{$parentSlug}' already has {$active} tenants created from it; the limit is {$max} (TENANCY_MAX_DERIVED_PER_PARENT).");
        }
        // an earlier, deleted derivation of the same reference or slug is replaced: the new row is the live record
        TenantDerivation::query()->where('status', TenantDerivation::DELETED)->where(fn ($q) => $q->where('tenant_slug', $slug)->orWhere(fn ($q2) => $q2->where('parent_slug', $parentSlug)->where('purpose', $purpose)->where('reference', $reference)))->delete();

        return TenantDerivation::query()->create([
            'tenant_slug' => $slug, 'parent_slug' => $parentSlug, 'template_slug' => $template, 'purpose' => $purpose,
            'reference' => $reference, 'requested_by' => $requestedBy !== null ? mb_substr($requestedBy, 0, 190) : null, 'status' => TenantDerivation::ACTIVE,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int,TenantDerivation> the active tenants created by `$parentSlug` */
    public function activeOf(string $parentSlug)
    {
        return TenantDerivation::query()->where('parent_slug', $parentSlug)->where('status', TenantDerivation::ACTIVE)->orderBy('id')->get();
    }

    /** The tenant is gone: the ledger keeps the row as the record. */
    public function markDeleted(string $slug): void
    {
        TenantDerivation::query()->where('tenant_slug', $slug)->where('status', TenantDerivation::ACTIVE)->update(['status' => TenantDerivation::DELETED, 'deleted_at' => now()]);
    }
}
