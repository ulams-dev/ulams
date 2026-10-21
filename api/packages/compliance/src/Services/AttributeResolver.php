<?php

namespace Ulams\Compliance\Services;

use Ulams\Core\Models\User;

/**
 * The values of a user attribute a rule can test: the virtual attributes `group` (names of the user's
 * groups) and `competency` (names of the confirmed competencies the user has earned), a user column, or a custom user field from model-fields (department, role, location, ...).
 * Hidden attributes (passwords, tokens) are never readable.
 */
class AttributeResolver
{
    public const GROUP = 'group';
    public const COMPETENCY = 'competency';

    public function __construct(private SkillProfileService $skills)
    {
    }

    /** @return list<string> lower-cased string values; empty when the user has none */
    public function values(User $user, string $attribute): array
    {
        if ($attribute === self::GROUP) {
            return method_exists($user, 'groups')
                ? $user->groups()->pluck('name')->map(fn ($name) => mb_strtolower(trim((string) $name)))->all()
                : [];
        }
        if ($attribute === self::COMPETENCY) {
            return $this->skills->names($user);
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
