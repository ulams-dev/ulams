<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Passport 12 → 13 (Laravel 13 upgrade, docs/plans/phase-0.md B.14).
 *
 * `oauth_clients`: `user_id` → `owner_type`/`owner_id`, `redirect` → `redirect_uris` (JSON array),
 * `personal_access_client`/`password_client` → `grant_types` (JSON array); client secrets are
 * hashed. `oauth_personal_access_clients` is dropped (Passport 13 picks the newest client with the
 * `personal_access` grant).
 *
 * Grant types are derived exactly as Passport 13 derives them for clients still on the legacy
 * columns (`Laravel\Passport\Client::grantTypes()`), so every existing client keeps the grants it
 * had and issued tokens stay valid (token rows and JWT keys are untouched; client ids keep their
 * UUIDs). Runs on the platform database and, through `ulams:tenant:sync-env --migrate`, on every
 * tenant database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('oauth_clients', 'grant_types')) {
            return;
        }

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->nullableMorphs('owner');
            $table->text('redirect_uris')->nullable();
            $table->text('grant_types')->nullable();
            $table->string('secret')->nullable()->change();
        });

        foreach (DB::table('oauth_clients')->orderBy('created_at')->get() as $client) {
            DB::table('oauth_clients')->where('id', $client->id)->update([
                'owner_type' => $client->user_id ? $this->ownerMorphClass($client->provider) : null,
                'owner_id' => $client->user_id ?: null,
                'redirect_uris' => json_encode($this->redirectUris($client->redirect)),
                'grant_types' => json_encode($this->grantTypes($client)),
                'secret' => $this->hashedSecret($client->secret),
            ]);
        }

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn(['user_id', 'redirect', 'personal_access_client', 'password_client']);
        });

        // Not nullable, as in Passport 13's own migration.
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('redirect_uris')->nullable(false)->change();
            $table->text('grant_types')->nullable(false)->change();
        });

        Schema::dropIfExists('oauth_personal_access_clients');
    }

    /**
     * Restores the Passport 12 columns. Secrets stay hashed (they cannot be recovered), so a client
     * that authenticates with its secret (password or client-credentials grant) needs a new one
     * after a rollback; personal access tokens do not use the secret.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('oauth_clients', 'grant_types')) {
            return;
        }

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->text('redirect')->nullable();
            $table->boolean('personal_access_client')->default(false);
            $table->boolean('password_client')->default(false);
        });

        Schema::create('oauth_personal_access_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('client_id');
            $table->timestamps();
        });

        foreach (DB::table('oauth_clients')->orderBy('created_at')->get() as $client) {
            $grantTypes = json_decode((string) $client->grant_types, true) ?: [];
            $personal = in_array('personal_access', $grantTypes, true);

            DB::table('oauth_clients')->where('id', $client->id)->update([
                'user_id' => $client->owner_id,
                'redirect' => implode(',', json_decode((string) $client->redirect_uris, true) ?: []),
                'personal_access_client' => $personal,
                'password_client' => in_array('password', $grantTypes, true),
            ]);

            if ($personal) {
                DB::table('oauth_personal_access_clients')->insert([
                    'client_id' => $client->id,
                    'created_at' => $client->created_at,
                    'updated_at' => $client->updated_at,
                ]);
            }
        }

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('redirect')->nullable(false)->change();
            $table->dropMorphs('owner');
            $table->dropColumn(['redirect_uris', 'grant_types']);
            $table->string('secret', 100)->nullable()->change();
        });
    }

    /**
     * @return list<string>
     */
    private function redirectUris(?string $redirect): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $redirect))));
    }

    /**
     * Same rules as `Laravel\Passport\Client::grantTypes()` for legacy clients.
     *
     * @return list<string>
     */
    private function grantTypes(object $client): array
    {
        $confidential = !empty($client->secret);
        $firstParty = empty($client->user_id);

        return array_keys(array_filter([
            'authorization_code' => $this->redirectUris($client->redirect) !== [],
            'client_credentials' => $confidential && $firstParty,
            'implicit' => $this->redirectUris($client->redirect) !== [],
            'password' => (bool) $client->password_client,
            'personal_access' => (bool) $client->personal_access_client && $confidential,
            'refresh_token' => true,
            'urn:ietf:params:oauth:grant-type:device_code' => true,
        ]));
    }

    private function hashedSecret(?string $secret): ?string
    {
        if ($secret === null || $secret === '') {
            return $secret;
        }

        return Hash::isHashed($secret) ? $secret : Hash::make($secret);
    }

    private function ownerMorphClass(?string $provider): string
    {
        $model = config('auth.providers.' . ($provider ?: 'users') . '.model')
            ?? config('auth.providers.users.model');

        return (new $model())->getMorphClass();
    }
};
