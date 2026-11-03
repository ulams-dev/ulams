<?php

namespace Ulams\Identity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ulams\Identity\Events\SamlMetadataNeedsAttention;
use Ulams\Identity\Exceptions\SamlException;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\Saml\IdpMetadata;
use Ulams\Identity\Services\Saml\SpKeyGenerator;

/**
 * Identity providers of the tenant: create, change, delete, each with an audit row. The client secret and the SAML
 * service provider keys are encrypted by the model casts; the audit rows only say that they changed.
 *
 * SAML metadata (plan 4.3, question 5): an administrator's import or edit is trusted; the daily refresh adds new IdP
 * certificates by itself, removes only certificates the new metadata no longer lists while at least one trusted
 * certificate stays, and never changes the entity id or replaces the whole trusted set: those need an administrator.
 */
class ProviderService
{
    public function __construct(
        private readonly IdentityAuditLog $audit,
        private readonly IdpMetadata $metadata,
        private readonly SpKeyGenerator $keys
    ) {
    }

    /** @param array<string,mixed> $data validated input */
    public function create(array $data): IdentityProvider
    {
        if (IdentityProvider::query()->count() >= (int) config('ulams_identity.max_providers')) {
            throw ValidationException::withMessages(['slug' => 'This academy has reached the maximum number of identity providers.']);
        }

        $protocol = ($data['protocol'] ?? IdentityProvider::OIDC) === IdentityProvider::SAML ? IdentityProvider::SAML : IdentityProvider::OIDC;
        $provider = new IdentityProvider();
        $provider->protocol = $protocol;
        $imported = null;
        if ($protocol === IdentityProvider::SAML) {
            // network and key generation before the transaction
            $imported = $this->importSaml($provider, (array) ($data['saml'] ?? []));
            $pair = $this->keys->generate($data['slug'] . ' (ulams service provider)');
            $provider->saml_sp_key = $pair['key'];
            $provider->saml_sp_cert = $pair['cert'];
        }

        return DB::transaction(function () use ($data, $provider, $protocol, $imported) {
            $this->fill($provider, $data, $imported['prepared'] ?? null);
            $provider->save();
            $this->audit->record('provider.created', ['provider_id' => $provider->getKey(), 'data' => ['slug' => $provider->slug, 'protocol' => $provider->protocol, 'enabled' => $provider->enabled]]);
            if ($protocol === IdentityProvider::SAML) {
                $this->auditSamlChanges($provider, $imported, [], 'admin');
                if ((bool) (((array) $provider->saml)['allow_sha1'] ?? false)) {
                    $this->audit->record('saml.sha1_changed', ['provider_id' => $provider->getKey(), 'data' => ['allowed' => true, 'warning' => 'SHA-1 signatures are accepted for this provider']]);
                }
            }

            return $provider;
        });
    }

    /** @param array<string,mixed> $data validated input; absent keys stay as they are */
    public function update(IdentityProvider $provider, array $data): IdentityProvider
    {
        $imported = null;
        $sha1Before = (bool) (((array) $provider->saml)['allow_sha1'] ?? false);
        if ($provider->protocol === IdentityProvider::SAML && array_key_exists('saml', $data)) {
            $imported = $this->importSaml($provider, (array) $data['saml']);
        }

        return DB::transaction(function () use ($provider, $data, $imported, $sha1Before) {
            $before = $provider->saml_idp_certs;
            $this->fill($provider, $data, $imported['prepared'] ?? null);
            $changed = array_keys($provider->getDirty());
            $secretChanged = in_array('oidc_client_secret', $changed, true);
            $provider->save();
            Cache::forget('identity:oidc:discovery:' . $provider->getKey() . ':' . sha1((string) ($provider->oidc['issuer'] ?? '')));
            $this->audit->record('provider.updated', [
                'provider_id' => $provider->getKey(),
                'data' => ['fields' => array_values(array_diff($changed, ['oidc_client_secret', 'oidc_secret_hint', 'saml_idp_certs', 'updated_at'])), 'secret_changed' => $secretChanged],
            ]);
            if ($provider->protocol === IdentityProvider::SAML) {
                $this->auditSamlChanges($provider, $imported, (array) $before, 'admin');
                $sha1After = (bool) (((array) $provider->saml)['allow_sha1'] ?? false);
                if ($sha1After !== $sha1Before) {
                    $this->audit->record('saml.sha1_changed', ['provider_id' => $provider->getKey(), 'data' => $sha1After
                        ? ['allowed' => true, 'warning' => 'SHA-1 signatures are accepted for this provider']
                        : ['allowed' => false]]);
                }
            }

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

    /**
     * Fetches the IdP metadata again and applies what the policy allows (see the class comment).
     *
     * @param bool $acceptChanges an administrator confirms: a new entity id and a new certificate set are applied
     * @return array{status:string,added:list<string>,removed:list<string>,reason:?string}
     *         status: unchanged | updated | attention (needs an administrator) | failed
     */
    public function refreshMetadata(IdentityProvider $provider, bool $acceptChanges = false, string $actor = 'system'): array
    {
        $saml = (array) $provider->saml;
        $url = (string) ($saml['metadata_url'] ?? '');
        if ($provider->protocol !== IdentityProvider::SAML || $url === '') {
            return $this->refreshFailed($provider, SamlException::MISCONFIGURED);
        }

        try {
            $xml = $this->metadata->fetch($url);
            try {
                $parsed = $this->metadata->parse($xml, (string) ($saml['entity_id'] ?? ''));
            } catch (SamlException $e) {
                // the stored entity may be gone from the document: tell "another entity" from "not metadata"
                $parsed = $this->metadata->parse($xml);
            }
        } catch (SamlException $e) {
            return $this->refreshFailed($provider, $e->reason);
        }

        $stored = $this->certsByFingerprint((array) $provider->saml_idp_certs);
        $incoming = $this->certsByFingerprint($parsed['certs'], 'metadata');
        $actorLabel = $actor === 'system' ? 'daily_refresh' : 'admin';

        $entityChanged = $parsed['entity_id'] !== (string) ($saml['entity_id'] ?? '');
        if ($entityChanged && !$acceptChanges) {
            return $this->needsAttention($provider, 'entity_id_changed');
        }

        $added = array_values(array_diff(array_keys($incoming), array_keys($stored)));
        $kept = array_intersect(array_keys($stored), array_keys($incoming));
        $missing = array_values(array_diff(array_keys($stored), array_keys($incoming)));
        $noOverlap = !$acceptChanges && $stored !== [] && $kept === [];

        if ($acceptChanges) {
            $next = $incoming;
            $removed = $missing;
        } elseif ($noOverlap) {
            // nothing in common with what is trusted: add, never drop, and ask an administrator to look
            $next = $stored + $incoming;
            $removed = [];
        } else {
            $next = array_diff_key($stored, array_flip($missing)) + $incoming;
            $removed = $missing;
        }
        // the one rule that never bends
        if ($next === []) {
            return $this->needsAttention($provider, 'would_remove_last_certificate');
        }

        $nextSaml = $saml;
        $endpointsChanged = false;
        foreach (['sso_url' => 'sso_url', 'slo_url' => 'slo_url', 'slo_response_url' => 'slo_response_url'] as $key => $source) {
            if ($this->urlAllowed($parsed[$source]) && ($nextSaml[$key] ?? null) !== $parsed[$source]) {
                $nextSaml[$key] = $parsed[$source];
                $endpointsChanged = true;
            }
        }
        if ($entityChanged) {
            $nextSaml['entity_id'] = $parsed['entity_id'];
        }

        $nothing = $added === [] && $removed === [] && !$endpointsChanged && !$entityChanged;
        DB::transaction(function () use ($provider, $nextSaml, $next, $added, $removed, $endpointsChanged, $entityChanged, $noOverlap, $actor, $actorLabel, $nothing) {
            $provider->forceFill([
                'saml' => $nextSaml,
                'saml_idp_certs' => array_values($next),
                'metadata_refreshed_at' => now(),
                'last_error' => $noOverlap && !$nothing ? 'metadata_certificates_unrelated' : ($provider->last_error !== null && str_starts_with((string) $provider->last_error, 'metadata_') ? null : $provider->last_error),
            ])->save();
            if (!$nothing) {
                $this->audit->record('saml.metadata_refreshed', [
                    'actor' => $actor === 'system' ? 'system' : 'user', 'provider_id' => $provider->getKey(),
                    'data' => ['via' => $actorLabel, 'added' => $added, 'removed' => $removed, 'endpoints_changed' => $endpointsChanged, 'entity_changed' => $entityChanged, 'no_overlap' => $noOverlap],
                ]);
            }
        });
        if ($noOverlap && !$nothing) {
            event(new SamlMetadataNeedsAttention($provider->getKey(), $provider->slug, 'metadata_certificates_unrelated'));
        }

        return ['status' => $nothing ? 'unchanged' : ($noOverlap ? 'attention' : 'updated'), 'added' => $added, 'removed' => $removed, 'reason' => $noOverlap ? 'metadata_certificates_unrelated' : null];
    }

    /**
     * Prepares or activates the next service provider key pair. `prepare` generates it and publishes it in the SP
     * metadata next to the current one (the IdP imports both); `promote` makes it the current pair.
     *
     * @return array{fingerprint:string,step:string}
     */
    public function rotateSpKeys(IdentityProvider $provider, string $step): array
    {
        if ($provider->protocol !== IdentityProvider::SAML) {
            throw ValidationException::withMessages(['step' => 'Only SAML providers have service provider keys.']);
        }
        if ($step === 'promote') {
            if ((string) $provider->saml_sp_next_key === '' || (string) $provider->saml_sp_next_cert === '') {
                throw ValidationException::withMessages(['step' => 'Prepare a next key pair first and give its certificate to the identity provider.']);
            }
            $pair = ['key' => (string) $provider->saml_sp_next_key, 'cert' => (string) $provider->saml_sp_next_cert];
            $fields = ['saml_sp_key' => $pair['key'], 'saml_sp_cert' => $pair['cert'], 'saml_sp_next_key' => null, 'saml_sp_next_cert' => null];
        } else {
            $pair = $this->keys->generate($provider->slug . ' (ulams service provider)');
            $fields = ['saml_sp_next_key' => $pair['key'], 'saml_sp_next_cert' => $pair['cert']];
        }
        $fingerprint = Saml\SamlXml::fingerprint($pair['cert']);
        DB::transaction(function () use ($provider, $fields, $step, $fingerprint) {
            $provider->forceFill($fields)->save();
            $this->audit->record('saml.sp_keys_rotated', ['provider_id' => $provider->getKey(), 'data' => ['step' => $step, 'fingerprint' => $fingerprint]]);
        });

        return ['fingerprint' => $fingerprint, 'step' => $step];
    }

    /**
     * @param array<string,mixed> $data
     * @param array{settings:array<string,mixed>,certs:list<array<string,mixed>>,refreshed:bool}|null $prepared the metadata import of this request
     */
    private function fill(IdentityProvider $provider, array $data, ?array $prepared = null): void
    {
        foreach (['slug', 'name', 'enabled', 'trust_email', 'jit_enabled', 'default_role'] as $field) {
            if (array_key_exists($field, $data)) {
                $provider->{$field} = $data[$field];
            }
        }
        if (array_key_exists('email_domains', $data)) {
            $provider->email_domains = array_values(array_unique(array_map('strtolower', (array) $data['email_domains'])));
        }
        if ($provider->protocol === IdentityProvider::SAML) {
            $this->fillSaml($provider, $data, $prepared);

            return;
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

    /**
     * Applies the SAML part of the input to the model: what the metadata import prepared, then the explicit fields.
     *
     * @param array<string,mixed> $data
     * @param array{settings:array<string,mixed>,certs:list<array<string,mixed>>,refreshed:bool}|null $prepared
     */
    private function fillSaml(IdentityProvider $provider, array $data, ?array $prepared): void
    {
        $saml = (array) $provider->saml;
        if (array_key_exists('saml', $data)) {
            $input = (array) $data['saml'];
            if ($prepared !== null) {
                $saml = array_replace($saml, $prepared['settings']);
                $provider->saml_idp_certs = $prepared['certs'];
                $provider->metadata_refreshed_at = $prepared['refreshed'] ? now() : $provider->metadata_refreshed_at;
            }
            foreach (['entity_id', 'sso_url', 'slo_url', 'slo_response_url', 'metadata_url', 'name_id_format'] as $field) {
                if (array_key_exists($field, $input)) {
                    $saml[$field] = $input[$field] === '' ? null : $input[$field];
                }
            }
            foreach (['want_encrypted_assertions', 'allow_sha1'] as $flag) {
                if (array_key_exists($flag, $input)) {
                    $saml[$flag] = (bool) $input[$flag];
                }
            }
            if (array_key_exists('attribute_map', $input)) {
                $saml['attribute_map'] = array_filter(array_intersect_key((array) $input['attribute_map'], array_flip(['email', 'first_name', 'last_name', 'name', 'subject'])), fn ($v) => is_string($v) && $v !== '');
            }
        }
        $provider->saml = $saml + ['want_encrypted_assertions' => false, 'allow_sha1' => false];

        if ((bool) ($provider->enabled ?? false)) {
            $certs = (array) $provider->saml_idp_certs;
            if ((string) ($saml['entity_id'] ?? '') === '' || (string) ($saml['sso_url'] ?? '') === '' || $certs === []) {
                throw ValidationException::withMessages(['enabled' => 'Import the identity provider metadata (or set the entity id, sign-on URL and a certificate) before enabling the provider.']);
            }
        }
    }

    /**
     * The metadata part of the input: fetched or parsed now (an administrator's action, so a new entity id and a new
     * certificate set are accepted) and handed to {@see fillSaml()}.
     *
     * @param array<string,mixed> $input
     * @return array{added:list<string>,removed:list<string>,prepared:?array{settings:array<string,mixed>,certs:list<array<string,mixed>>,refreshed:bool}}
     */
    private function importSaml(IdentityProvider $provider, array $input): array
    {
        $settings = [];
        $certs = null;
        $refreshed = false;
        $xml = null;
        if (!empty($input['metadata_xml'])) {
            $xml = (string) $input['metadata_xml'];
        } elseif (!empty($input['metadata_url']) && $input['metadata_url'] !== (((array) $provider->saml)['metadata_url'] ?? null)) {
            try {
                $xml = $this->metadata->fetch((string) $input['metadata_url']);
            } catch (SamlException $e) {
                throw ValidationException::withMessages(['saml.metadata_url' => $this->metadataMessage($e)]);
            }
            $settings['metadata_url'] = (string) $input['metadata_url'];
        }

        if ($xml !== null) {
            try {
                $parsed = $this->metadata->parse($xml, isset($input['entity_id']) && $input['entity_id'] !== '' ? (string) $input['entity_id'] : null);
            } catch (SamlException $e) {
                throw ValidationException::withMessages([empty($input['metadata_xml']) ? 'saml.metadata_url' : 'saml.metadata_xml' => $this->metadataMessage($e)]);
            }
            $settings += [
                'entity_id' => $parsed['entity_id'], 'sso_url' => $parsed['sso_url'], 'slo_url' => $parsed['slo_url'], 'slo_response_url' => $parsed['slo_response_url'],
            ];
            if ($parsed['name_id_format'] !== null && !array_key_exists('name_id_format', $input) && empty(((array) $provider->saml)['name_id_format'])) {
                $settings['name_id_format'] = $parsed['name_id_format'];
            }
            $certs = $this->certsByFingerprint($parsed['certs'], 'metadata');
            $refreshed = true;
        }

        if (!empty($input['idp_certificates'])) {
            try {
                $certs = [];
                foreach ((array) $input['idp_certificates'] as $pem) {
                    $cert = IdpMetadata::certificate((string) $pem);
                    $certs[$cert['fingerprint']] = $cert + ['added_at' => now()->toIso8601String(), 'source' => 'admin'];
                }
            } catch (SamlException $e) {
                throw ValidationException::withMessages(['saml.idp_certificates' => $this->metadataMessage($e)]);
            }
        }

        $before = $this->certsByFingerprint((array) $provider->saml_idp_certs);
        if ($certs === null) {
            return ['added' => [], 'removed' => [], 'prepared' => $settings === [] ? null : ['settings' => $settings, 'certs' => array_values($before), 'refreshed' => false]];
        }
        // keep the original added_at of certificates that stay
        foreach ($certs as $fp => $cert) {
            if (isset($before[$fp])) {
                $certs[$fp] = $before[$fp];
            }
        }

        return [
            'added' => array_values(array_diff(array_keys($certs), array_keys($before))),
            'removed' => array_values(array_diff(array_keys($before), array_keys($certs))),
            'prepared' => ['settings' => $settings, 'certs' => array_values($certs), 'refreshed' => $refreshed],
        ];
    }

    /**
     * @param array{added:list<string>,removed:list<string>,prepared:mixed}|null $imported
     * @param array<int,mixed> $before
     */
    private function auditSamlChanges(IdentityProvider $provider, ?array $imported, array $before, string $via): void
    {
        if ($imported === null || ($imported['added'] === [] && $imported['removed'] === [])) {
            return;
        }
        $this->audit->record('saml.certs_changed', ['provider_id' => $provider->getKey(), 'data' => ['via' => $via, 'added' => $imported['added'], 'removed' => $imported['removed']]]);
    }

    /**
     * @param list<array<string,mixed>> $certs
     * @return array<string,array<string,mixed>> by fingerprint
     */
    private function certsByFingerprint(array $certs, ?string $source = null): array
    {
        $out = [];
        foreach ($certs as $cert) {
            $out[(string) $cert['fingerprint']] = $source === null ? $cert : ($cert + ['added_at' => now()->toIso8601String(), 'source' => $source]);
        }

        return $out;
    }

    private function urlAllowed(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return filter_var($url, FILTER_VALIDATE_URL) !== false && ($scheme === 'https' || ($scheme === 'http' && config('ulams_identity.allow_insecure_urls')));
    }

    private function metadataMessage(SamlException $e): string
    {
        return match ($e->reason) {
            SamlException::METADATA_UNREACHABLE => 'The metadata could not be fetched. Use a public https URL.',
            SamlException::CERTIFICATE_WEAK => 'A certificate uses a key that is too weak (RSA below 2048 bits).',
            SamlException::CERTIFICATE_INVALID => 'A certificate is not a valid X.509 certificate.',
            default => 'This is not valid SAML identity provider metadata (an IDPSSODescriptor with a sign-on URL and a signing certificate is required).',
        };
    }

    /** @return array{status:string,added:list<string>,removed:list<string>,reason:?string} */
    private function refreshFailed(IdentityProvider $provider, string $reason): array
    {
        $provider->forceFill(['last_error' => Str::limit('metadata_' . $reason, 250, '')])->save();
        $this->audit->record('saml.metadata_failed', ['actor' => 'system', 'provider_id' => $provider->getKey(), 'data' => ['reason' => $reason]]);

        return ['status' => 'failed', 'added' => [], 'removed' => [], 'reason' => $reason];
    }

    /** @return array{status:string,added:list<string>,removed:list<string>,reason:?string} */
    private function needsAttention(IdentityProvider $provider, string $reason): array
    {
        $provider->forceFill(['last_error' => 'metadata_' . $reason])->save();
        $this->audit->record('saml.metadata_attention', ['actor' => 'system', 'provider_id' => $provider->getKey(), 'data' => ['reason' => $reason]]);
        event(new SamlMetadataNeedsAttention($provider->getKey(), $provider->slug, $reason));

        return ['status' => 'attention', 'added' => [], 'removed' => [], 'reason' => $reason];
    }
}
