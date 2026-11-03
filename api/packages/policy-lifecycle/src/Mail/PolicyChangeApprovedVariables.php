<?php

namespace Ulams\PolicyLifecycle\Mail;

use Ulams\Templates\Events\EventWrapper;

class PolicyChangeApprovedVariables extends PolicyVariables
{
    const VAR_CHANGE_CLASS = '@VarChangeClass';
    const VAR_COUNTS = '@VarCounts';

    /** The owner's notice is about a policy, not a course. */
    public static function requiredVariables(): array
    {
        return [self::VAR_POLICY_TITLE];
    }

    protected static function mockedExtra(): array
    {
        return [self::VAR_CHANGE_CLASS => 'minor', self::VAR_COUNTS => 'Engineering: 12 revoked, 12 reassigned; Sales: 3 revoked, 3 reassigned'];
    }

    protected static function extra(EventWrapper $event): array
    {
        $counts = array_map(fn (array $d) => sprintf('%s: %d revoked, %d reassigned', $d['department'], $d['revoked'], $d['reassigned']), $event->getDepartments());

        return self::policyVariables($event) + [
            self::VAR_WHAT_CHANGED => (string) $event->getVersion()->summary,
            self::VAR_CHANGE_CLASS => $event->getVersion()->change_class,
            self::VAR_COUNTS => implode('; ', $counts),
        ];
    }

    public static function defaultSectionsContent(): array
    {
        $values = [
            'policy' => self::VAR_POLICY_TITLE,
            'version' => self::VAR_POLICY_VERSION,
            'class' => self::VAR_CHANGE_CLASS,
            'user_name' => self::VAR_USER_NAME,
            'what_changed' => self::VAR_WHAT_CHANGED,
            'counts' => self::VAR_COUNTS,
        ];

        return [
            'title' => __('Policy change approved: :policy', $values),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Version :version of ":policy" is approved as a :class change and rolled out. :what_changed</p><p>Superseded certificates and reassigned training: :counts.</p>', $values)),
        ];
    }
}
