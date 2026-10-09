<?php

namespace Ulams\Lrs\Xapi;

/**
 * Structural validation of xAPI 1.0.x statements: required properties, identifiers,
 * IRIs, UUIDs, timestamps and value ranges. It does not check extensions, language maps
 * in depth or attachments (which the store does not accept).
 */
final class StatementValidator
{
    private const STATEMENT_KEYS = ['id', 'actor', 'verb', 'object', 'result', 'context', 'timestamp', 'stored', 'authority', 'version', 'attachments'];
    private const SUB_STATEMENT_KEYS = ['objectType', 'actor', 'verb', 'object', 'result', 'context', 'timestamp', 'attachments'];
    private const AGENT_KEYS = ['objectType', 'name', 'mbox', 'mbox_sha1sum', 'openid', 'account', 'member'];
    private const RESULT_KEYS = ['score', 'success', 'completion', 'response', 'duration', 'extensions'];
    private const CONTEXT_KEYS = ['registration', 'instructor', 'team', 'contextActivities', 'revision', 'platform', 'language', 'statement', 'extensions'];
    private const CONTEXT_ACTIVITY_KEYS = ['parent', 'grouping', 'category', 'other'];

    public const VOIDED_VERB = 'http://adlnet.gov/expapi/verbs/voided';

    /**
     * @param mixed $statement decoded JSON
     * @return string[] error messages, empty when valid
     */
    public static function errors(mixed $statement): array
    {
        if (!self::isObject($statement)) {
            return ['A statement must be a JSON object.'];
        }

        $errors = self::unknownKeys($statement, self::STATEMENT_KEYS, 'statement');

        if (array_key_exists('id', $statement) && !self::isUuid($statement['id'])) {
            $errors[] = 'statement.id must be a UUID.';
        }
        if (array_key_exists('version', $statement) && !(is_string($statement['version']) && preg_match('/^1\.0(\.\d+)?$/', $statement['version']))) {
            $errors[] = 'statement.version must be 1.0.x.';
        }
        foreach (['timestamp', 'stored'] as $key) {
            if (array_key_exists($key, $statement) && !self::isTimestamp($statement[$key])) {
                $errors[] = "statement.$key must be an ISO 8601 timestamp.";
            }
        }
        if (array_key_exists('authority', $statement)) {
            $errors = [...$errors, ...self::agentErrors($statement['authority'], 'statement.authority')];
        }
        if (array_key_exists('attachments', $statement)) {
            $errors[] = 'Statement attachments are not supported by this store.';
        }

        return [...$errors, ...self::coreErrors($statement, 'statement', false)];
    }

    public static function isVoiding(array $statement): bool
    {
        return ($statement['verb']['id'] ?? null) === self::VOIDED_VERB;
    }

    /**
     * Actor, verb, object, result and context: shared by statements and sub-statements.
     *
     * @return string[]
     */
    private static function coreErrors(array $statement, string $path, bool $isSubStatement): array
    {
        $errors = [];

        if (!array_key_exists('actor', $statement)) {
            $errors[] = "$path.actor is required.";
        } else {
            $errors = [...$errors, ...self::agentErrors($statement['actor'], "$path.actor")];
        }

        if (!array_key_exists('verb', $statement)) {
            $errors[] = "$path.verb is required.";
        } elseif (!self::isObject($statement['verb']) || !self::isIri($statement['verb']['id'] ?? null)) {
            $errors[] = "$path.verb.id must be an IRI.";
        } elseif (array_key_exists('display', $statement['verb']) && !self::isObject($statement['verb']['display'])) {
            $errors[] = "$path.verb.display must be a language map.";
        }

        if (!array_key_exists('object', $statement)) {
            $errors[] = "$path.object is required.";
        } else {
            $errors = [...$errors, ...self::objectErrors($statement['object'], "$path.object", $isSubStatement)];
        }

        if (array_key_exists('result', $statement)) {
            $errors = [...$errors, ...self::resultErrors($statement['result'], "$path.result")];
        }

        if (array_key_exists('context', $statement)) {
            $errors = [...$errors, ...self::contextErrors($statement['context'], "$path.context", $statement['object'] ?? null)];
        }

        if (array_key_exists('timestamp', $statement) && $isSubStatement && !self::isTimestamp($statement['timestamp'])) {
            $errors[] = "$path.timestamp must be an ISO 8601 timestamp.";
        }

        if (!$isSubStatement && self::isVoiding($statement) && (($statement['object']['objectType'] ?? null) !== 'StatementRef')) {
            $errors[] = "$path.object of a voiding statement must be a StatementRef.";
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    public static function agentErrors(mixed $agent, string $path, bool $allowAnonymousGroup = true): array
    {
        if (!self::isObject($agent)) {
            return ["$path must be an object."];
        }

        $errors = self::unknownKeys($agent, self::AGENT_KEYS, $path);
        $type = $agent['objectType'] ?? 'Agent';

        if (!in_array($type, ['Agent', 'Group'], true)) {
            return [...$errors, "$path.objectType must be Agent or Group."];
        }

        $ifis = array_values(array_intersect(Agent::IFI_KEYS, array_keys($agent)));

        if (count($ifis) > 1) {
            $errors[] = "$path must have only one identifier (mbox, mbox_sha1sum, openid or account).";
        }

        if ($type === 'Agent' && count($ifis) === 0) {
            $errors[] = "$path must have an identifier (mbox, mbox_sha1sum, openid or account).";
        }

        if ($type === 'Group') {
            if (count($ifis) === 0 && (!$allowAnonymousGroup || !isset($agent['member']) || !is_array($agent['member']))) {
                $errors[] = "$path is an anonymous group and needs a member list.";
            }
            foreach ((array) ($agent['member'] ?? []) as $i => $member) {
                if (self::isObject($member) && ($member['objectType'] ?? 'Agent') !== 'Agent') {
                    $errors[] = "$path.member[$i] must be an Agent.";
                    continue;
                }
                $errors = [...$errors, ...self::agentErrors($member, "$path.member[$i]")];
            }
        } elseif (array_key_exists('member', $agent)) {
            $errors[] = "$path.member is only allowed on groups.";
        }

        if (isset($agent['mbox']) && !(is_string($agent['mbox']) && preg_match('/^mailto:[^@\s]+@[^@\s]+$/i', $agent['mbox']))) {
            $errors[] = "$path.mbox must be a mailto: IRI.";
        }
        if (isset($agent['mbox_sha1sum']) && !(is_string($agent['mbox_sha1sum']) && preg_match('/^[0-9a-f]{40}$/i', $agent['mbox_sha1sum']))) {
            $errors[] = "$path.mbox_sha1sum must be a SHA1 hex string.";
        }
        if (isset($agent['openid']) && !self::isIri($agent['openid'])) {
            $errors[] = "$path.openid must be an IRI.";
        }
        if (array_key_exists('account', $agent)) {
            $account = $agent['account'];
            if (!self::isObject($account)
                || !self::isIri($account['homePage'] ?? null)
                || !is_string($account['name'] ?? null)
                || self::unknownKeys($account, ['homePage', 'name'], '')) {
                $errors[] = "$path.account must have a homePage IRI and a name.";
            }
        }
        if (array_key_exists('name', $agent) && !is_string($agent['name'])) {
            $errors[] = "$path.name must be a string.";
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function objectErrors(mixed $object, string $path, bool $isSubStatement): array
    {
        if (!self::isObject($object)) {
            return ["$path must be an object."];
        }

        $type = $object['objectType'] ?? 'Activity';

        switch ($type) {
            case 'Activity':
                $errors = self::unknownKeys($object, ['objectType', 'id', 'definition'], $path);
                if (!self::isIri($object['id'] ?? null)) {
                    $errors[] = "$path.id must be an IRI.";
                }
                if (array_key_exists('definition', $object) && !self::isObject($object['definition'])) {
                    $errors[] = "$path.definition must be an object.";
                }

                return $errors;
            case 'Agent':
            case 'Group':
                return self::agentErrors($object, $path);
            case 'StatementRef':
                $errors = self::unknownKeys($object, ['objectType', 'id'], $path);
                if (!self::isUuid($object['id'] ?? null)) {
                    $errors[] = "$path.id must be a UUID.";
                }

                return $errors;
            case 'SubStatement':
                if ($isSubStatement) {
                    return ["$path: a SubStatement can not contain a SubStatement."];
                }

                return [
                    ...self::unknownKeys($object, self::SUB_STATEMENT_KEYS, $path),
                    ...(array_key_exists('attachments', $object) ? ['Statement attachments are not supported by this store.'] : []),
                    ...self::coreErrors($object, $path, true),
                ];
            default:
                return ["$path.objectType is not a valid object type."];
        }
    }

    /**
     * @return string[]
     */
    private static function resultErrors(mixed $result, string $path): array
    {
        if (!self::isObject($result)) {
            return ["$path must be an object."];
        }

        $errors = self::unknownKeys($result, self::RESULT_KEYS, $path);

        if (array_key_exists('score', $result)) {
            $score = $result['score'];
            if (!self::isObject($score)) {
                $errors[] = "$path.score must be an object.";
            } else {
                $errors = [...$errors, ...self::unknownKeys($score, ['scaled', 'raw', 'min', 'max'], "$path.score")];
                foreach (['scaled', 'raw', 'min', 'max'] as $key) {
                    if (array_key_exists($key, $score) && !is_int($score[$key]) && !is_float($score[$key])) {
                        $errors[] = "$path.score.$key must be a number.";
                    }
                }
                if (is_numeric($score['scaled'] ?? null) && ($score['scaled'] < -1 || $score['scaled'] > 1)) {
                    $errors[] = "$path.score.scaled must be between -1 and 1.";
                }
                if (is_numeric($score['min'] ?? null) && is_numeric($score['max'] ?? null) && $score['min'] > $score['max']) {
                    $errors[] = "$path.score.min must not be greater than max.";
                }
                if (is_numeric($score['raw'] ?? null)
                    && ((is_numeric($score['min'] ?? null) && $score['raw'] < $score['min'])
                        || (is_numeric($score['max'] ?? null) && $score['raw'] > $score['max']))) {
                    $errors[] = "$path.score.raw must be between min and max.";
                }
            }
        }
        foreach (['success', 'completion'] as $key) {
            if (array_key_exists($key, $result) && !is_bool($result[$key])) {
                $errors[] = "$path.$key must be a boolean.";
            }
        }
        if (array_key_exists('response', $result) && !is_string($result['response'])) {
            $errors[] = "$path.response must be a string.";
        }
        if (array_key_exists('duration', $result) && !(is_string($result['duration']) && preg_match('/^P(?=\d|T\d)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+(\.\d+)?S)?)?$/', $result['duration']))) {
            $errors[] = "$path.duration must be an ISO 8601 duration.";
        }
        if (array_key_exists('extensions', $result) && !self::isObject($result['extensions'])) {
            $errors[] = "$path.extensions must be an object.";
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function contextErrors(mixed $context, string $path, mixed $object): array
    {
        if (!self::isObject($context)) {
            return ["$path must be an object."];
        }

        $errors = self::unknownKeys($context, self::CONTEXT_KEYS, $path);

        if (array_key_exists('registration', $context) && !self::isUuid($context['registration'])) {
            $errors[] = "$path.registration must be a UUID.";
        }
        if (array_key_exists('instructor', $context)) {
            $errors = [...$errors, ...self::agentErrors($context['instructor'], "$path.instructor")];
        }
        if (array_key_exists('team', $context)) {
            if (!self::isObject($context['team']) || ($context['team']['objectType'] ?? null) !== 'Group') {
                $errors[] = "$path.team must be a Group.";
            } else {
                $errors = [...$errors, ...self::agentErrors($context['team'], "$path.team")];
            }
        }
        if (array_key_exists('contextActivities', $context)) {
            $activities = $context['contextActivities'];
            if (!self::isObject($activities)) {
                $errors[] = "$path.contextActivities must be an object.";
            } else {
                $errors = [...$errors, ...self::unknownKeys($activities, self::CONTEXT_ACTIVITY_KEYS, "$path.contextActivities")];
                foreach ($activities as $key => $value) {
                    $list = self::isObject($value) ? [$value] : (is_array($value) ? $value : null);
                    if ($list === null) {
                        $errors[] = "$path.contextActivities.$key must be an activity or a list of activities.";
                        continue;
                    }
                    foreach ($list as $activity) {
                        $errors = [...$errors, ...self::objectErrors(
                            self::isObject($activity) ? ['objectType' => 'Activity'] + $activity : $activity,
                            "$path.contextActivities.$key",
                            true
                        )];
                    }
                }
            }
        }
        $isActivity = self::isObject($object) && ($object['objectType'] ?? 'Activity') === 'Activity';
        foreach (['revision', 'platform'] as $key) {
            if (array_key_exists($key, $context)) {
                if (!is_string($context[$key])) {
                    $errors[] = "$path.$key must be a string.";
                } elseif (!$isActivity) {
                    $errors[] = "$path.$key is only allowed when the object is an activity.";
                }
            }
        }
        if (array_key_exists('language', $context) && !is_string($context['language'])) {
            $errors[] = "$path.language must be a string.";
        }
        if (array_key_exists('statement', $context)
            && (!self::isObject($context['statement'])
                || ($context['statement']['objectType'] ?? null) !== 'StatementRef'
                || !self::isUuid($context['statement']['id'] ?? null))) {
            $errors[] = "$path.statement must be a StatementRef.";
        }
        if (array_key_exists('extensions', $context) && !self::isObject($context['extensions'])) {
            $errors[] = "$path.extensions must be an object.";
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function unknownKeys(array $value, array $allowed, string $path): array
    {
        $unknown = array_diff(array_keys($value), $allowed);

        return $unknown ? [sprintf('%s has unknown properties: %s.', $path ?: 'value', implode(', ', $unknown))] : [];
    }

    public static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    public static function isIri(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/^[a-z][a-z0-9+.\-]*:\S+$/i', $value);
    }

    public static function isUuid(mixed $value): bool
    {
        return is_string($value)
            && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    public static function isTimestamp(mixed $value): bool
    {
        return is_string($value)
            && (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+\-]\d{2}(:?\d{2})?)?$/', $value)
            && strtotime($value) !== false;
    }
}
