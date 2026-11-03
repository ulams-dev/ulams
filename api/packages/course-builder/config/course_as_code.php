<?php

return [
    // May a `ci` token approve an imported version? Off: CI pushes a proposed version and a person approves it
    // in the studio or with `ulams`. On: the merged, reviewed pull request counts as the approval (ADR 0160).
    // `agent` tokens and MCP clients can never approve.
    'ci_may_approve' => filter_var(env('COURSE_AS_CODE_CI_MAY_APPROVE', false), FILTER_VALIDATE_BOOLEAN),

    // Largest pushed blueprint document, in bytes of JSON.
    'max_document_bytes' => (int) env('COURSE_AS_CODE_MAX_DOCUMENT_MB', 8) * 1024 * 1024,
];
