<?php

namespace Ulams\Lrs\Xapi;

/**
 * xAPI agent identification: the inverse functional identifier (IFI) of an agent or
 * identified group, and the "virtual id" string documents are keyed by.
 */
final class Agent
{
    public const IFI_KEYS = ['mbox', 'mbox_sha1sum', 'openid', 'account'];

    /**
     * Decode and validate the `agent` request parameter.
     *
     * @return array<string, mixed>
     */
    public static function fromParameter(mixed $value, string $name = 'agent'): array
    {
        if (!is_string($value) || $value === '') {
            throw XapiException::badRequest("The [$name] parameter is required.");
        }

        $agent = json_decode($value, true);

        if (!is_array($agent) || array_is_list($agent)) {
            throw XapiException::badRequest("The [$name] parameter must be a JSON object.");
        }

        $errors = StatementValidator::agentErrors($agent, $name, false);

        if ($errors) {
            throw XapiException::badRequest(implode(' ', $errors));
        }

        return $agent;
    }

    /**
     * The IFI part of an agent, e.g. `['mbox' => 'mailto:a@b.c']`.
     *
     * @param array<string, mixed> $agent
     * @return array<string, mixed>
     */
    public static function ifi(array $agent): array
    {
        foreach (self::IFI_KEYS as $key) {
            if (isset($agent[$key])) {
                return $key === 'account'
                    ? ['account' => ['homePage' => $agent['account']['homePage'] ?? null, 'name' => $agent['account']['name'] ?? null]]
                    : [$key => $agent[$key]];
            }
        }

        return [];
    }

    /**
     * A string id for an agent, e.g. `mbox::mailto:a@b.c` or `account::name@homePage`.
     *
     * @param array<string, mixed> $agent
     */
    public static function virtualId(array $agent): string
    {
        $ifi = self::ifi($agent);

        if (isset($ifi['account'])) {
            return 'account::' . $ifi['account']['name'] . '@' . $ifi['account']['homePage'];
        }

        $key = array_key_first($ifi);

        if ($key === null) {
            throw XapiException::badRequest('The agent has no identifier (mbox, mbox_sha1sum, openid or account).');
        }

        return $key . '::' . $ifi[$key];
    }
}
