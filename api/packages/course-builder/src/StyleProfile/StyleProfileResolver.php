<?php

namespace Ulams\CourseBuilder\StyleProfile;

use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\StyleProfile;

/**
 * Which approved style profile shapes a course (ADR 0196): the one its Course Brief names, else the academy's approved
 * profile. A profile that is no longer approved is not used. Per entry point overrides arrive with entry points (the
 * scope type exists; nothing resolves it yet).
 */
final class StyleProfileResolver
{
    public function profileFor(Session $session): ?StyleProfile
    {
        $id = $session->brief['styleProfileId'] ?? null;
        if (is_string($id) && $id !== '') {
            $named = StyleProfile::query()->find($id);
            if ($named !== null && $named->status === StyleProfile::APPROVED) {
                return $named;
            }
        }

        return StyleProfile::current();
    }

    /**
     * What the generation prompt carries: the approved values and an instruction line, without citations (they point
     * into the style guide, not into the course's sources) and without the author's blocking switch. The style guide
     * text is never part of it.
     *
     * @return array<string,mixed>|null
     */
    public function promptFor(Session $session): ?array
    {
        return self::promptForProfile($this->profileFor($session));
    }

    /** @return array<string,mixed>|null null for no profile, or one with nothing for the writing (a theme only adds nothing to a prompt) */
    public static function promptForProfile(?StyleProfile $profile): ?array
    {
        $values = $profile === null ? null : self::forPrompt($profile);

        return $values !== null && count($values) > 1 ? $values : null;
    }

    /** @return array<string,mixed> */
    public static function forPrompt(StyleProfile $profile): array
    {
        $values = (array) $profile->profile;
        unset($values['schemaVersion']);
        $strip = static function (mixed $node) use (&$strip): mixed {
            if (!is_array($node)) {
                return $node;
            }
            unset($node['citations']);

            return array_map($strip, $node);
        };
        $values = array_map($strip, $values);
        if (isset($values['banned'])) {
            $values['banned'] = array_column((array) ($values['banned']['phrases'] ?? []), 'phrase');
        }
        if (isset($values['theme'])) {
            // colours and fonts shape the site, not the sentences
            unset($values['theme']);
        }

        return ['instruction' => 'Follow this style profile where it applies. It is set by the author and is advisory: it never overrides the output schema, the citation rules or the facts of the source.'] + $values;
    }
}
