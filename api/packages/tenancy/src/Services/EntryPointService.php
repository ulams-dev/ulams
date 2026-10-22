<?php

namespace Ulams\Tenancy\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ulams\Tenancy\Events\EntryPointsChanged;
use Ulams\Tenancy\Exceptions\EntryPointException;
use Ulams\Tenancy\Models\EntryPoint;
use Ulams\Tenancy\Models\EntryPointAudit;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\EntryHostPublisherContract;
use Ulams\Tenancy\Services\Contracts\EntryPointGatewayContract;
use Ulams\Tenancy\Support\EntryHost;
use Ulams\Tenancy\Support\EntryPointRules;
use Ulams\Tenancy\Support\TenantNaming;

/**
 * The platform side of entry points (ADR 0195): the only code that writes `tenant_entry_points`. It runs in
 * a platform process, on behalf of one tenant named by the caller, and refuses anything that tenant does not
 * own. Every change is audited.
 */
class EntryPointService implements EntryPointGatewayContract
{
    public function __construct(private readonly EntryHostPublisherContract $publisher)
    {
    }

    public function list(string $slug): array
    {
        $tenant = $this->tenant($slug);

        return EntryPoint::query()->where('tenant_id', $tenant->id)->orderBy('label')->orderBy('key')->get()
            ->map(fn (EntryPoint $entry) => $this->present($entry, $tenant))->all();
    }

    public function show(string $slug, string $id): array
    {
        $tenant = $this->tenant($slug);

        return $this->present($this->find($tenant, $id), $tenant);
    }

    public function create(string $slug, array $data, ?array $actor): array
    {
        $tenant = $this->tenant($slug);
        $data = $this->validated(EntryPointRules::create(), $data);

        $errors = [];
        $host = EntryHost::normalize((string) $data['host']);
        $errors += $this->hostErrors($host, $tenant);
        $kind = (string) $data['kind'];
        $errors += EntryPointRules::targetErrors($kind, $data['target'] ?? null);
        $errors += $this->seoErrors($kind, (array) ($data['seo'] ?? []));
        if (EntryPoint::query()->where('tenant_id', $tenant->id)->where('key', $data['key'])->exists()) {
            $errors['key'] = ['This tenant already has an entry point with that key.'];
        }
        if (EntryPoint::query()->where('tenant_id', $tenant->id)->count() >= (int) config('ulams_tenancy.max_entry_points', 20)) {
            $errors['host'] = array_merge($errors['host'] ?? [], ['This academy has reached its limit of ' . (int) config('ulams_tenancy.max_entry_points', 20) . ' entry points.']);
        }
        $this->failWith($errors);

        $verified = EntryHost::platformSuffix($host) !== null || EntryHost::isTrustedSuffix($host);
        $entry = DB::transaction(function () use ($tenant, $data, $host, $kind, $verified, $actor) {
            $entry = EntryPoint::query()->create([
                'id' => strtolower((string) Str::ulid()),
                'tenant_id' => $tenant->id,
                'host' => $host,
                'key' => $data['key'],
                'kind' => $kind,
                'target' => $this->normalizeTarget($kind, $data['target'] ?? null),
                'label' => $data['label'],
                'theme' => $this->cleanTheme($data['theme'] ?? null),
                'seo' => array_merge(EntryPointRules::seoDefaults($kind), $this->cleanSeo($data['seo'] ?? null)),
                'locale' => $data['locale'] ?? null,
                'status' => $verified ? EntryPoint::ACTIVE : EntryPoint::PENDING_DNS,
                'verification_token' => $verified ? null : bin2hex(random_bytes(16)),
                'verified_at' => $verified ? now() : null,
            ]);
            $this->record($tenant, $entry, 'created', $actor, ['host' => $host, 'kind' => $kind, 'status' => $entry->status]);

            return $entry;
        });
        event(new EntryPointsChanged($tenant));
        $this->publish($tenant);

        return $this->present($entry, $tenant);
    }

    public function update(string $slug, string $id, array $data, ?array $actor): array
    {
        $tenant = $this->tenant($slug);
        $entry = $this->find($tenant, $id);
        $data = $this->validated(EntryPointRules::update(), $data);

        $errors = [];
        if (array_key_exists('target', $data)) {
            $errors += EntryPointRules::targetErrors($entry->kind, $data['target']);
        }
        if (array_key_exists('seo', $data)) {
            $errors += $this->seoErrors($entry->kind, (array) $data['seo']);
        }
        if (($data['status'] ?? null) === EntryPoint::ACTIVE && $entry->verified_at === null) {
            $errors['status'] = ['The host is not verified yet: publish its DNS record first.'];
        }
        $this->failWith($errors);

        $changes = [];
        $before = $entry->only(['label', 'target', 'theme', 'seo', 'locale', 'status']);
        if (array_key_exists('label', $data)) {
            $entry->label = $data['label'];
        }
        if (array_key_exists('target', $data)) {
            $entry->target = $this->normalizeTarget($entry->kind, $data['target']);
        }
        if (array_key_exists('theme', $data)) {
            $entry->theme = $this->cleanTheme($data['theme']);
        }
        if (array_key_exists('seo', $data)) {
            $entry->seo = array_merge(EntryPointRules::seoDefaults($entry->kind), $this->cleanSeo($data['seo']));
        }
        if (array_key_exists('locale', $data)) {
            $entry->locale = $data['locale'];
        }
        if (array_key_exists('status', $data)) {
            $entry->status = $data['status'];
        }
        foreach ($before as $field => $old) {
            if ($entry->{$field} !== $old) {
                $changes[$field] = ['from' => $old, 'to' => $entry->{$field}];
            }
        }

        if ($changes !== []) {
            DB::transaction(function () use ($tenant, $entry, $actor, $changes, $before) {
                $entry->save();
                $action = 'updated';
                if (isset($changes['status'])) {
                    $action = $entry->status === EntryPoint::DISABLED ? 'disabled' : 'enabled';
                }
                $this->record($tenant, $entry, $action, $actor, $changes);
            });
            event(new EntryPointsChanged($tenant));
        }
        // also when nothing changed: a retry after a failed publication brings the academy's copy up to date
        $this->publish($tenant);

        return $this->present($entry->refresh(), $tenant);
    }

    public function delete(string $slug, string $id, ?array $actor): void
    {
        $tenant = $this->tenant($slug);
        $entry = $this->find($tenant, $id);

        DB::transaction(function () use ($tenant, $entry, $actor) {
            $this->record($tenant, $entry, 'deleted', $actor, ['host' => $entry->host, 'kind' => $entry->kind]);
            $entry->delete();
        });
        event(new EntryPointsChanged($tenant));
        $this->publish($tenant);
    }

    public function audit(string $slug, int $limit = 100): array
    {
        $tenant = $this->tenant($slug);

        return EntryPointAudit::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(max(1, min($limit, 500)))->get()
            ->map(fn (EntryPointAudit $row) => [
                'id' => $row->id,
                'entry_point_id' => $row->entry_point_id,
                'action' => $row->action,
                'host' => $row->host,
                'actor_id' => $row->actor_id,
                'actor_email' => $row->actor_email,
                'changes' => $row->changes ?? [],
                'created_at' => $row->created_at?->toIso8601String(),
            ])->all();
    }

    /** @return array<string, mixed> */
    public function present(EntryPoint $entry, Tenant $tenant): array
    {
        $pending = $entry->status === EntryPoint::PENDING_DNS && $entry->verification_token !== null;

        return [
            'id' => $entry->id,
            'host' => $entry->host,
            'key' => $entry->key,
            'kind' => $entry->kind,
            'target' => $entry->target ?? [],
            'label' => $entry->label,
            'theme' => $entry->theme ?? [],
            'seo' => $entry->seo ?? EntryPointRules::seoDefaults($entry->kind),
            'locale' => $entry->locale,
            'status' => $entry->status,
            'url' => TenantNaming::origin($entry->host),
            // the same context without DNS (ADR 0195, option 3): the main host, /go/<key>
            'go_url' => rtrim($tenant->frontUrl(), '/') . '/go/' . $entry->key,
            'dns' => $pending ? [
                'txt_name' => '_ulams-verify.' . $entry->host,
                'txt_value' => 'ulams-verify=' . $entry->verification_token,
                'cname_target' => config('ulams_tenancy.entry_cname_target'),
            ] : null,
            'verified_at' => $entry->verified_at?->toIso8601String(),
            'created_at' => $entry->created_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Brings the academy's own copy of its active entry hosts up to date, so the origin check, the return URLs and the
     * hand-off follow the change at once. The change is already saved; when the academy cannot be reached the admin is
     * told, a retry publishes again, and the reconcile job finds the academy anyway.
     */
    private function publish(Tenant $tenant): void
    {
        try {
            $this->publisher->publish($tenant);
        } catch (\Throwable $e) {
            report($e);

            throw new EntryPointException('Saved, but the academy could not be updated yet: its trusted address list is refreshed again within minutes. Repeat the request to refresh it now.', 503);
        }
    }

    private function tenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            throw new EntryPointException('Unknown academy.', 404);
        }

        return $tenant;
    }

    /** Looks the entry point up inside the tenant: another tenant's id is an unknown id. */
    private function find(Tenant $tenant, string $id): EntryPoint
    {
        $entry = preg_match('/^[0-9a-z]{26}$/', $id) ? EntryPoint::query()->where('tenant_id', $tenant->id)->whereKey($id)->first() : null;
        if ($entry === null) {
            throw new EntryPointException('Entry point not found.', 404);
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $rules, array $data): array
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new EntryPointException('The entry point is not valid.', 422, $validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /** @return array<string, list<string>> */
    private function hostErrors(string $host, Tenant $tenant): array
    {
        $reason = EntryHost::syntaxError($host);
        if ($reason === null) {
            $suffix = EntryHost::platformSuffix($host);
            $reason = ($suffix !== null && !EntryHost::isTrustedSuffix($host) ? EntryHost::platformFlatnessError($host, $suffix, $tenant->slug) : null)
                ?? EntryHost::conflict($host);
        }

        return $reason === null ? [] : ['host' => [$reason]];
    }

    /**
     * @param array<string, mixed> $seo
     * @return array<string, list<string>>
     */
    private function seoErrors(string $kind, array $seo): array
    {
        // a copy of the tenant's pages under a second address is only worth indexing for a course sales page
        if (($seo['canonical'] ?? 'tenant') === 'self' && $kind !== EntryPoint::COURSE) {
            return ['seo.canonical' => ['Only a course entry point can be its own canonical address.']];
        }

        return [];
    }

    /** @param array<string, list<string>> $errors */
    private function failWith(array $errors): void
    {
        if ($errors !== []) {
            throw new EntryPointException('The entry point is not valid.', 422, $errors);
        }
    }

    /**
     * @param array<string, mixed>|null $target
     * @return array<string, int|string>|null
     */
    private function normalizeTarget(string $kind, ?array $target): ?array
    {
        return match ($kind) {
            EntryPoint::DEPARTMENT => ['group_id' => (int) $target['group_id']],
            EntryPoint::COURSE => ['course_id' => (int) $target['course_id']],
            EntryPoint::ROLE => ['role' => (string) $target['role']],
            default => null,
        };
    }

    /**
     * @param array<string, mixed>|null $theme
     * @return array<string, string>|null
     */
    private function cleanTheme(?array $theme): ?array
    {
        $clean = array_filter([
            'preset' => $theme['preset'] ?? null,
            'accent' => isset($theme['accent']) ? strtolower((string) $theme['accent']) : null,
            'logo_alt' => $theme['logo_alt'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return $clean === [] ? null : $clean;
    }

    /**
     * @param array<string, mixed>|null $seo
     * @return array<string, mixed>
     */
    private function cleanSeo(?array $seo): array
    {
        return array_filter([
            'canonical' => $seo['canonical'] ?? null,
            'index' => $seo['index'] ?? null,
            'title' => $seo['title'] ?? null,
            'description' => $seo['description'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param array{id: int|null, email: string|null}|null $actor
     * @param array<string, mixed> $changes
     */
    private function record(Tenant $tenant, EntryPoint $entry, string $action, ?array $actor, array $changes): void
    {
        EntryPointAudit::query()->create([
            'tenant_id' => $tenant->id,
            'entry_point_id' => $entry->id,
            'action' => $action,
            'host' => $entry->host,
            'actor_id' => $actor['id'] ?? null,
            'actor_email' => $actor['email'] ?? null,
            'changes' => $changes,
        ]);
    }
}
