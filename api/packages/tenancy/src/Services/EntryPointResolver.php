<?php

namespace Ulams\Tenancy\Services;

use Ulams\Tenancy\Models\EntryPoint;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Support\EntryHost;
use Ulams\Tenancy\Support\EntryPointRules;

/**
 * Public lookup of an active entry point (ADR 0195): by the host a browser asked the front for, or, for the
 * `/go/<key>` fallback on an academy's main host, by academy and key. The answer is what the front needs to
 * serve the host (academy, kind, key, label, target, theme, SEO, language) and nothing else: no ids of people,
 * no status, no DNS token. An entry point that is pending or disabled, or whose academy is not active, does not
 * exist for this lookup.
 */
class EntryPointResolver
{
    /** @return array<string, mixed>|null */
    public function byHost(string $host): ?array
    {
        $host = EntryHost::normalize($host);
        if (EntryHost::syntaxError($host) !== null) {
            return null;
        }

        return $this->present(EntryPoint::query()->with('tenant')->where('host', $host)->first());
    }

    /** @return array<string, mixed>|null */
    public function byKey(string $slug, string $key): ?array
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            return null;
        }

        return $this->present(EntryPoint::query()->with('tenant')->where('tenant_id', $tenant->id)->where('key', $key)->first());
    }

    /** @return array<string, mixed>|null */
    private function present(?EntryPoint $entry): ?array
    {
        if ($entry === null || !$entry->isActive() || $entry->tenant?->status !== Tenant::STATUS_ACTIVE) {
            return null;
        }

        return [
            'tenant' => $entry->tenant->slug,
            'host' => $entry->host,
            'kind' => $entry->kind,
            'key' => $entry->key,
            'label' => $entry->label,
            'target' => $entry->target ?? [],
            'theme' => $entry->theme ?? [],
            'seo' => $entry->seo ?? EntryPointRules::seoDefaults($entry->kind),
            'locale' => $entry->locale,
        ];
    }
}
