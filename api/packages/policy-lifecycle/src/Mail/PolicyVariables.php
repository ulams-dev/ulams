<?php

namespace Ulams\PolicyLifecycle\Mail;

use Ulams\Compliance\Mail\ComplianceVariables;
use Ulams\Templates\Events\EventWrapper;

/** Variables the policy e-mails share on top of the compliance ones (user, course, due date, path). */
abstract class PolicyVariables extends ComplianceVariables
{
    const VAR_POLICY_TITLE = '@VarPolicyTitle';
    const VAR_POLICY_VERSION = '@VarPolicyVersion';
    const VAR_WHAT_CHANGED = '@VarWhatChanged';
    const VAR_TRAINING_URL = '@VarTrainingUrl';

    protected static function trainingUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/account/training';
    }

    public static function mockedVariables(?\Ulams\Core\Models\User $user = null): array
    {
        return array_merge(parent::mockedVariables($user), [
            self::VAR_POLICY_TITLE => 'Remote work and data handling',
            self::VAR_POLICY_VERSION => '2',
            self::VAR_WHAT_CHANGED => 'Sections 2 changed: §1.1 Approval and working hours; §2.1 Classifying data.',
            self::VAR_TRAINING_URL => self::trainingUrl(),
        ]);
    }

    /** The policy variables of an event that carries `policy` and `version`. */
    protected static function policyVariables(EventWrapper $event): array
    {
        return [
            self::VAR_POLICY_TITLE => $event->getPolicy()->title,
            self::VAR_POLICY_VERSION => (string) $event->getVersion()->number,
            self::VAR_TRAINING_URL => self::trainingUrl(),
        ];
    }
}
