<?php

namespace Ulams\Tenancy\Tests\Unit;

use Ulams\Tenancy\Tests\TestCase;

/**
 * Root processes in the api image (workers, `docker compose exec api php artisan`) must not leave files that
 * php-fpm (www-data) cannot write (issue #501): umask 002 for supervisord and artisan, and setgid directories
 * (group www-data) that the start scripts set after their final chown.
 */
class RootFilePermissionsTest extends TestCase
{
    private function api(string $file): string
    {
        return (string) file_get_contents(realpath(__DIR__ . '/../../../../' . $file));
    }

    public function testSupervisordAndArtisanRunWithGroupWritableUmask(): void
    {
        $this->assertMatchesRegularExpression('/^umask=002\b/m', $this->api('docker/conf/supervisor/supervisord.conf'));
        $this->assertStringContainsString('umask(0002);', $this->api('artisan'));
    }

    public function testStartScriptsMakeStorageDirectoriesSetgidAfterTheFinalChown(): void
    {
        foreach (['init.sh', 'init_multidomains.sh'] as $script) {
            $source = $this->api($script);
            $chown = strpos($source, 'chown -R www-data:www-data /var/www/html/storage');
            $setgid = strpos($source, '-type d -exec chmod g+s');
            $this->assertNotFalse($chown, $script);
            $this->assertNotFalse($setgid, $script);
            $this->assertGreaterThan($chown, $setgid, $script . ': setgid must come after the chown');
            $this->assertLessThan(strpos($source, '/usr/bin/supervisord'), $setgid, $script);
        }
    }
}
