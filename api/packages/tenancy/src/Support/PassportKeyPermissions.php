<?php

namespace Ulams\Tenancy\Support;

/**
 * Passport 13 (league/oauth2-server 9) refuses key files whose mode is not 400, 440, 600, 640 or
 * 660: every request that loads the key fails with "Key file ... permissions are not correct".
 *
 * The private key is readable by its owner only (the php-fpm user, see StorageOwnership). The
 * public key is also group-readable, because the H5P service (api/h5p) verifies tokens with it
 * through a read-only mount; that container gets the storage group (`group_add` in
 * docker-compose.yml).
 */
class PassportKeyPermissions
{
    public const PRIVATE_KEY = 'oauth-private.key';
    public const PUBLIC_KEY = 'oauth-public.key';
    public const PRIVATE_MODE = 0600;
    public const PUBLIC_MODE = 0640;

    /**
     * Applies the modes to the key files in a storage directory (missing files are skipped).
     */
    public static function apply(string $directory): void
    {
        foreach ([self::PRIVATE_KEY => self::PRIVATE_MODE, self::PUBLIC_KEY => self::PUBLIC_MODE] as $file => $mode) {
            $path = rtrim($directory, '/') . '/' . $file;
            if (is_file($path)) {
                @chmod($path, $mode);
            }
        }
    }
}
