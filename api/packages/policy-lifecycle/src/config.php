<?php

return [
    // The tenant template new department academies start from (slug of a template of the platform, see
    // `ulams:tenant:template`). Empty: no template, the theme and accent chosen for the department are all that is set.
    'academy_template' => env('POLICY_ACADEMY_TEMPLATE') ?: null,
];
