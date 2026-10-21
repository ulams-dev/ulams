<?php

namespace Ulams\Auth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ulams\Auth\Models\SocialAccount;
use Ulams\Auth\Models\User;

/**
 * The social login before ADR 0120 created accounts with `true` in the password slot of the DTO,
 * which was stored as the password "1". Anybody who knew the address of such an account could sign
 * in to it. This replaces that hash with a random one nobody knows (the owner signs in with the
 * provider, or sets a password with "forgot password") and revokes the tokens of the account.
 * It only touches accounts that have a linked social account, and it is safe to run again.
 */
class DisarmLegacySocialPasswordsCommand extends Command
{
    /** The password the old code stored. */
    private const LEGACY_PASSWORD = '1';

    protected $signature = 'ulams:auth:disarm-legacy-social-passwords {--dry-run : Count the accounts, change nothing}';

    protected $description = 'Replace the password "1" of accounts created by the old social login with an unusable one and revoke their tokens';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $fixed = 0;

        User::query()
            ->whereIn((new User())->getKeyName(), SocialAccount::query()->select('user_id'))
            ->whereNotNull('password')
            ->chunkById(100, function ($users) use ($dryRun, &$checked, &$fixed): void {
                foreach ($users as $user) {
                    $checked++;
                    if (!$this->verifiesAgainstLegacyPassword((string) $user->password)) {
                        continue;
                    }
                    $fixed++;
                    if ($dryRun) {
                        continue;
                    }
                    $user->forceFill(['password' => Hash::make(Str::random(64))])->save();
                    $user->tokens()->update(['revoked' => true]);
                }
            });

        // counts only: no address, name or id
        $message = ($dryRun ? 'Would disarm ' : 'Disarmed ') . "{$fixed} of {$checked} social login accounts with the legacy password.";
        $this->info($message);
        if (!$dryRun) {
            Log::info('auth.legacy_social_passwords', ['checked' => $checked, 'disarmed' => $fixed]);
        }

        return self::SUCCESS;
    }

    private function verifiesAgainstLegacyPassword(string $hash): bool
    {
        try {
            return Hash::check(self::LEGACY_PASSWORD, $hash);
        } catch (\RuntimeException) {
            // a hash in a format the hasher refuses (a legacy algorithm) cannot be the bcrypt of "1"
            return false;
        }
    }
}
