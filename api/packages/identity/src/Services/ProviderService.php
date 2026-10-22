<?php

namespace Ulams\Identity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Ulams\Identity\Models\IdentityProvider;

/**
 * Identity providers of the tenant: create, change, delete, each with an audit row. The client secret is
 * encrypted by the model cast; the audit rows only say that it changed.
 */
class ProviderService
{
    public function __construct(private readonly IdentityAuditLog $audit)
    {
    }

    /** @param array<string,mixed> $data validated input */
    public function create(array $data): IdentityProvider
    {
        if (IdentityProvider::query()->count() >= (int) config('ulams_identity.max_providers')) {
            throw ValidationException::withMessages(['slug' => 'This academy has reached the maximum number of identity providers.']);
        }

        return DB::transaction(function () use ($data) {
            $provider = new IdentityProvider();
            $this->fill($provider, $data);
            $provider->protocol = IdentityProvider::OIDC;
            $provider->save();
            $this->audit->record('provider.created', ['provider_id' => $provider->getKey(), 'data' => ['slug' => $provider->slug, 'protocol' => $provider->protocol, 'enabled' => $provider->enabled]]);

            return $provider;
        });
    }

    /** @param array<string,mixed> $data validated input; absent keys stay as they are */
    public function update(IdentityProvider $provider, array $data): IdentityProvider
    {
        return DB::transaction(function () use ($provider, $data) {
            $this->fill($provider, $data);
            $changed = array_keys($provider->getDirty());
            $secretChanged = in_array('oidc_client_secret', $changed, true);
            $provider->save();
            Cache::forget('identity:oidc:discovery:' . $provider->getKey() . ':' . sha1((string) ($provider->oidc['issuer'] ?? '')));
            $this->audit->record('provider.updated', [
                'provider_id' => $provider->getKey(),
                'data' => ['fields' => array_values(array_diff($changed, ['oidc_client_secret', 'oidc_secret_hint', 'updated_at'])), 'secret_changed' => $secretChanged],
            ]);

            return $provider;
        });
    }

    public function delete(IdentityProvider $provider): void
    {
        DB::transaction(function () use ($provider) {
            $id = $provider->getKey();
            $slug = $provider->slug;
            $links = $provider->links()->count();
            $provider->delete();
            $this->audit->record('provider.deleted', ['provider_id' => $id, 'data' => ['slug' => $slug, 'links_removed' => $links]]);
        });
    }

    /** @param array<string,mixed> $data */
    private function fill(IdentityProvider $provider, array $data): void
    {
        foreach (['slug', 'name', 'enabled', 'trust_email', 'jit_enabled', 'default_role'] as $field) {
            if (array_key_exists($field, $data)) {
                $provider->{$field} = $data[$field];
            }
        }
        if (array_key_exists('email_domains', $data)) {
            $provider->email_domains = array_values(array_unique(array_map('strtolower', (array) $data['email_domains'])));
        }
        if (array_key_exists('oidc', $data)) {
            $oidc = (array) $data['oidc'];
            $current = (array) $provider->oidc;
            $provider->oidc = [
                'issuer' => $oidc['issuer'] ?? $current['issuer'] ?? null,
                'client_id' => $oidc['client_id'] ?? $current['client_id'] ?? null,
                'scopes' => array_values(array_unique(array_map('strval', $oidc['scopes'] ?? $current['scopes'] ?? ['email', 'profile']))),
                'token_auth' => $oidc['token_auth'] ?? $current['token_auth'] ?? 'client_secret_basic',
                'userinfo' => (bool) ($oidc['userinfo'] ?? $current['userinfo'] ?? false),
            ];
        }
        if (array_key_exists('client_secret', $data) && is_string($data['client_secret']) && $data['client_secret'] !== '') {
            $provider->oidc_client_secret = $data['client_secret'];
            $provider->oidc_secret_hint = strlen($data['client_secret']) >= 16 ? substr($data['client_secret'], -4) : null;
        }
        // enabling needs everything a login needs
        if (($provider->enabled ?? false) && ((string) ($provider->oidc['issuer'] ?? '') === '' || (string) ($provider->oidc['client_id'] ?? '') === '' || (string) $provider->oidc_client_secret === '')) {
            throw ValidationException::withMessages(['enabled' => 'Set the issuer, client id and client secret before enabling the provider.']);
        }
    }
}
