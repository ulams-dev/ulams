<?php

return [
    /*
     * Demo mode: password-less login as the demo student, tutor or admin and an hourly reset of the
     * whole tenant database. Set DEMO_MODE=true only in the `.env.<host>` of a demo tenant
     * (`ulams:tenant:create <slug> --demo=on`). When it is off, no route, command schedule or
     * public config key of this package is registered.
     */
    'enabled' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),

    /*
     * Accounts used for the automatic login. Empty means: the admin is INITIAL_USER_EMAIL
     * (created by PermissionsSeeder), the student is `student1@` at the admin's e-mail domain
     * (created by `ulams:tenant:seed-demo`). If either is missing, the first user with that
     * role is used.
     */
    'admin_email' => env('DEMO_ADMIN_EMAIL') ?: env('INITIAL_USER_EMAIL'),
    'student_email' => env('DEMO_STUDENT_EMAIL'),
    // The tutor (the studio author role) is `tutor@` at the admin's e-mail domain, created by
    // `ulams:tenant:seed-demo`.
    'tutor_email' => env('DEMO_TUTOR_EMAIL'),

    /*
     * Shown on the demo badge of the front ("open the admin panel") and of the admin
     * ("open the learner site"). The front and the admin derive them from their own host
     * when these are empty.
     */
    'front_url' => env('FRONTEND_URL'),
    'admin_url' => env('DEMO_ADMIN_URL') ?: env('ADMIN_URL'),

    'reset' => [
        // Registered in the scheduler of every domain that has DEMO_MODE=true.
        'schedule' => filter_var(env('DEMO_RESET_SCHEDULE', true), FILTER_VALIDATE_BOOLEAN),
        'cron' => env('DEMO_RESET_CRON', '0 * * * *'),

        // Also delete every file of the tenant's default disk (bucket) before reseeding.
        'wipe_files' => filter_var(env('DEMO_RESET_WIPE_FILES', false), FILTER_VALIDATE_BOOLEAN),

        // Number of demo students when no baseline was captured yet.
        'students' => (int) env('DEMO_RESET_STUDENTS', 5),
    ],

    /*
     * Demo content seeded by `ulams:demo:seed` (and therefore by every reset). The class is
     * skipped when it does not exist. The experience defaults to the tenant slug.
     */
    'content_seeder' => env('DEMO_CONTENT_SEEDER', 'Database\\Seeders\\DemoCoursesSeeder'),
    'experience' => env('ULAMS_DEMO_EXPERIENCE') ?: env('TENANT_SLUG'),
];
