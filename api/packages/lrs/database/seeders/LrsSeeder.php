<?php

namespace Ulams\Lrs\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Models\BasicHttpCredentials;
use Ulams\Lrs\Models\Client;
use Ulams\Lrs\Models\Owner;

/**
 * Creates the store's owner, client and xAPI access (the endpoint cmi5 content reports to).
 */
class LrsSeeder extends Seeder
{
    public function run()
    {
        $name = 'Ulams';

        $owner = Owner::firstOrCreate(['name' => $name], ['meta' => []]);

        $client = Client::create([
            'name' => $name,
            'permissions' => ['xapi-scope.all'],
            'owner_id' => $owner->id,
        ]);

        // Learners authenticate with their Passport token; these Basic credentials are for
        // other xAPI clients and get a random password.
        $credentials = BasicHttpCredentials::create([
            'username' => $name,
            'password' => Hash::make(Str::random(40)),
        ]);

        Access::create([
            'name' => $client->name,
            'cors' => '*',
            'client_id' => $client->id,
            'credentials_id' => $credentials->id,
            'credentials_type' => Access::TYPE_BASIC_HTTP,
        ]);
    }
}
