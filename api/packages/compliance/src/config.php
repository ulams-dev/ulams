<?php

return [
    /*
     * Defaults for courses without their own compliance settings. Days are counted before the
     * certificate expiry or the due date (reminders) and after the due date (escalation).
     */
    'reminder_days' => [30, 7, 1],
    'escalation_days' => [1, 7],
    'recertify_before_days' => 30,
];
