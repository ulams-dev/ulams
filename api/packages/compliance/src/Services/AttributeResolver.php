<?php

namespace Ulams\Compliance\Services;

use Ulams\Core\Models\User;

/**
 * The values of a user attribute a rule can test: the virtual attribute `group` (names of the user's
 * groups), a user column, or a custom user field from model-fields (department, role, location, ...).
 * Hidden attributes (passwords, tokens) are never readable.
 */
class AttributeResolver
{
    public const GROUP = 'group';

    /** @return list<string> lower-cased string values; empty when the user has none */
    public function values(User $user, string $attribute): array
    {
        if ($attribute === self::GROUP) {
            return method_exists($user, 'groups')
                ? $user->groups()->pluck('name')->map(fn ($name) => mb_strtolower(trim((string) $name)))->all()
                : [];
        }
        if (in_array($attribute, $user->getHidden(), true)) {
            return [];
        }

        $value = $user->attributesToArray()[$attribute] ?? null;
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map(
            fn ($item) => is_scalar($item) ? mb_strtolower(trim((string) $item)) : null,
            $values,
        ), fn ($item) => $item !== null && $item !== ''));
    }
}
