<?php

/*
 * Conformance fixture for the Keycloak OpenID Connect sign-in (identity-keycloak.yml), run from api/:
 *
 *   php ../.github/conformance/keycloak/ulams-setup.php admin
 *       -> {"token": "..."}   an administrator and a Passport token for the admin API
 *
 * The provider itself is created through the admin API by run-oidc.cjs, so that API is part of the test.
 * Test fixtures only: the conformance database is thrown away after the run.
 */

use Illuminate\Contracts\Console\Kernel;
use Ulams\Core\Enums\UserRole;

require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

switch ($argv[1] ?? '') {
    case 'admin':
        $userModel = config('auth.providers.users.model');
        $admin = $userModel::query()->where('email', 'admin@conformance.example')->first()
            ?? $userModel::query()->create([
                'first_name' => 'Admin',
                'last_name' => 'Conformance',
                'email' => 'admin@conformance.example',
                'password' => bcrypt(bin2hex(random_bytes(16))),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        $admin->assignRole(UserRole::ADMIN);
        fwrite(STDOUT, json_encode(['token' => $admin->createToken('conformance')->accessToken]) . "\n");
        break;

    default:
        fwrite(STDERR, "usage: ulams-setup.php admin\n");
        exit(2);
}
