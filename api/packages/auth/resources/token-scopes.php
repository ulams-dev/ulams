<?php

/*
 * Route -> scope area map for scoped personal access tokens (ADR 0074, docs/plans/cli.md 6.2).
 *
 * Entries are `[pattern, area, options?]`, matched against the route URI (`api/admin/courses/{course}`)
 * with `Str::is` (`*` matches anything, `/` included); the FIRST match wins, so keep specific
 * patterns above general ones.
 *
 * Areas: courses, users, enrolments, settings, events, certificates, commerce, reports, lti,
 * builder, living-course, learner, tokens, platform, plus two pseudo areas:
 *   public  any valid scoped token may call it (identity, health, anonymous endpoints);
 *   none    never callable with a scoped token (it would mint unscoped credentials, change the
 *           account's credentials or let a scoped token widen its own rights).
 *
 * Options: `methods` (match only these HTTP methods) and `level` (`read` or `write`) for the few
 * endpoints whose HTTP method does not tell whether they change data.
 *
 * Anything not listed is DENIED for a scoped token (fail closed). `TokenScopeMapCoverageTest`
 * fails when a registered route matches no entry, so a new route must be added here.
 * Regenerate the CLI's copy with `php artisan ulams:tokens:export-scopes`.
 */

return [
    // ---- never with a scoped token -------------------------------------------------------------
    ['api/auth/refresh', 'none'],
    ['api/refresh-token', 'none'],
    ['api/admin/auth/*', 'none'],
    ['api/admin/g-token/*', 'none'],
    ['api/profile/me-auth', 'none'],
    ['api/profile/password', 'none'],
    ['api/profile/delete*', 'none'],
    ['api/profile', 'none'],
    ['api/auth/device/requests*', 'none'],
    ['oauth/*', 'none'],

    // ---- public / identity ---------------------------------------------------------------------
    ['api/meta', 'public'],
    ['api/auth/tokens/current', 'public'],
    ['api/auth/logout', 'public'],
    ['api/profile/me', 'public', ['methods' => ['GET', 'HEAD']]],
    ['api/auth/device*', 'public'],
    ['api/auth/login', 'public'],
    ['api/auth/register*', 'public'],
    ['api/auth/password/*', 'public'],
    ['api/auth/social/*', 'public'],
    ['api/auth/email/*', 'public'],
    ['api/health*', 'public'],
    ['api/core/health-check', 'public'],
    ['api/name', 'public'],
    ['api/documentation', 'public'],
    ['api/oauth2-callback', 'public'],
    ['api/lti/jwks', 'public'],
    ['api/jitsi/*', 'public'],
    ['api/payments-gateways/*', 'public'],
    ['api/demo/*', 'public'],
    ['.well-known/*', 'public'],
    ['docs*', 'public'],

    // ---- tokens --------------------------------------------------------------------------------
    ['api/auth/tokens*', 'tokens'],
    ['api/admin/tokens*', 'tokens'],
    ['api/admin/agent-audit*', 'tokens'],

    // ---- builder and living course -------------------------------------------------------------
    ['api/admin/course-builder*', 'builder'],
    ['api/admin/living-course*', 'living-course'],

    // ---- enrolments (before the broader courses and users prefixes) ----------------------------
    ['api/admin/courses/*/access*', 'enrolments'],
    ['api/admin/courses/users/assignable', 'enrolments'],
    ['api/admin/dictionaries/*/access', 'enrolments'],
    ['api/admin/course-access-enquiries*', 'enrolments'],
    ['api/admin/consultation-access-enquiries*', 'enrolments'],
    ['api/admin/tutors/*', 'enrolments'],

    // ---- courses -------------------------------------------------------------------------------
    ['api/admin/courses/*/clone', 'courses', ['level' => 'write']],
    ['api/admin/courses*', 'courses'],
    ['api/admin/lessons*', 'courses'],
    ['api/admin/topics*', 'courses'],
    ['api/admin/categories*', 'courses'],
    ['api/admin/tags*', 'courses'],
    ['api/admin/file/*', 'courses'],
    ['api/admin/scorm*', 'courses'],
    ['api/admin/cmi5*', 'courses'],
    ['api/admin/liascript*', 'courses'],
    ['api/admin/h5p*', 'courses'],
    ['api/admin/adapt*', 'courses'],
    ['api/admin/gift-*', 'courses'],
    ['api/admin/question', 'courses'],
    ['api/admin/question/*', 'courses'],
    ['api/admin/question-answers*', 'courses'],
    ['api/admin/quiz-*', 'courses'],
    ['api/admin/topic-project-solutions*', 'courses'],
    ['api/admin/user-submissions*', 'courses'],
    ['api/admin/video*', 'courses'],
    ['api/admin/youtube*', 'courses'],
    ['api/admin/dictionaries*', 'courses'],
    ['api/admin/dictionary-words*', 'courses'],
    ['api/admin/bookmarks*', 'courses'],
    ['api/images/*', 'courses'],

    // ---- users ---------------------------------------------------------------------------------
    ['api/admin/users*', 'users'],
    ['api/admin/user-groups*', 'users'],
    ['api/admin/roles*', 'users'],
    ['api/admin/permissions*', 'users'],
    ['api/admin/csv/*', 'users'],
    ['api/admin/assign-without-account*', 'users'],

    // ---- settings ------------------------------------------------------------------------------
    ['api/admin/settings*', 'settings'],
    ['api/admin/config*', 'settings'],
    ['api/admin/pages*', 'settings'],
    ['api/admin/templates*', 'settings'],
    ['api/admin/translations*', 'settings'],
    ['api/admin/notifications*', 'settings'],
    ['api/admin/bulk-notifications*', 'settings'],
    ['api/admin/model-fields*', 'settings'],
    ['api/admin/mattermost*', 'settings'],
    ['api/admin/mailerlite*', 'settings'],

    // ---- events --------------------------------------------------------------------------------
    ['api/admin/webinars*', 'events'],
    ['api/admin/stationary-events*', 'events'],
    ['api/admin/consultations*', 'events'],
    ['api/admin/events*', 'events'],
    ['api/admin/jitsi*', 'events'],
    ['api/admin/pencil-spaces*', 'events'],

    // ---- certificates --------------------------------------------------------------------------
    ['api/admin/pdfs*', 'certificates'],

    // ---- commerce ------------------------------------------------------------------------------
    ['api/admin/orders*', 'commerce'],
    ['api/admin/products*', 'commerce'],
    ['api/admin/productables*', 'commerce'],
    ['api/admin/vouchers*', 'commerce'],
    ['api/admin/payments*', 'commerce'],
    ['api/admin/invoices*', 'commerce'],

    // ---- reports -------------------------------------------------------------------------------
    ['api/admin/reports*', 'reports'],
    ['api/admin/stats*', 'reports'],
    ['api/admin/questionnaire*', 'reports'],
    ['api/admin/tasks*', 'reports'],

    // ---- lti -----------------------------------------------------------------------------------
    ['api/admin/lti*', 'lti'],

    // ---- platform (tenant API, platform hosts only) --------------------------------------------
    ['api/platform*', 'platform'],

    // ---- learner (everything a signed-in learner does on the public API) -----------------------
    ['api/consultations/generate-jitsi/*', 'learner', ['level' => 'write']],
    ['api/consultations/approve-term/*', 'learner', ['level' => 'write']],
    ['api/webinars/generate-jitsi/*', 'learner', ['level' => 'write']],
    ['api/webinars/start-live-stream/*', 'learner', ['level' => 'write']],
    ['api/webinars/stop-live-stream/*', 'learner', ['level' => 'write']],
    ['api/mattermost/*', 'learner', ['level' => 'write']],
    ['api/profile*', 'learner'],
    ['api/courses*', 'learner'],
    ['api/cart*', 'learner'],
    ['api/bookmarks*', 'learner'],
    ['api/notifications*', 'learner'],
    ['api/orders*', 'learner'],
    ['api/order-invoices/*', 'learner'],
    ['api/payments*', 'learner'],
    ['api/product*', 'learner'],
    ['api/quiz-*', 'learner'],
    ['api/tasks*', 'learner'],
    ['api/topic-project-solutions*', 'learner'],
    ['api/webinars*', 'learner'],
    ['api/stationary-events*', 'learner'],
    ['api/consultations*', 'learner'],
    ['api/consultation-access-enquiries*', 'learner'],
    ['api/course-access-enquiries*', 'learner'],
    ['api/dictionaries*', 'learner'],
    ['api/categories*', 'learner'],
    ['api/tags*', 'learner'],
    ['api/pages*', 'learner'],
    ['api/settings*', 'learner'],
    ['api/translations*', 'learner'],
    ['api/config', 'learner'],
    ['api/model-fields', 'learner'],
    ['api/events', 'learner'],
    ['api/tutors*', 'learner'],
    ['api/content/*', 'learner'],
    ['api/pdfs*', 'learner'],
    ['api/questionnaire/*', 'learner'],
    ['api/scorm/*', 'learner'],
    ['api/cmi5/*', 'learner'],
    ['api/liascript/*', 'learner'],
    ['api/lti/*', 'learner'],
    ['api/pencil-spaces/*', 'learner'],
    ['api/core/packages', 'learner'],
    ['broadcasting/auth', 'learner'],
];
