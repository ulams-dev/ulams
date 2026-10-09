<?php

namespace Ulams\Demo\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Ulams\H5P\Exceptions\H5PServiceException;
use Ulams\Demo\Services\Contracts\DemoServiceContract;
use Ulams\Demo\Services\H5PContentCleaner;
use Ulams\Demo\Support\DemoBaseline;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Support\RedisKeyPurger;
use Ulams\Tenancy\Support\TenantContext;

/**
 * Wipes a demo tenant and seeds it again, as if it had just been provisioned. Runs inside the
 * tenant (`--domain=<host>`); the scheduler of every domain with DEMO_MODE=true runs it hourly.
 *
 * Every step after the baseline runs as a child `php artisan … --domain=<host>`, like the
 * tenancy provisioning steps, so each one boots with a clean container after the wipe.
 */
class ResetDemoCommand extends Command
{
    protected $signature = 'ulams:demo:reset
        {--force : Do not ask for confirmation}
        {--wipe-files : Also delete every file on the tenant\'s default disk (bucket)}
        {--recapture : Take a new baseline (name, theme, accent, students) from the current database}';

    protected $description = 'Wipe and reseed this demo tenant (database, demo users, demo courses, cache, tokens). Requires DEMO_MODE=true.';

    public function handle(
        DemoServiceContract $demo,
        TenantCommandRunnerContract $runner,
        DemoBaseline $baseline,
        RedisKeyPurger $redis,
        H5PContentCleaner $h5p,
    ): int {
        if (!$demo->isEnabled()) {
            $this->error('Demo mode is off on this domain (DEMO_MODE=true is required): nothing was reset.');

            return self::FAILURE;
        }
        if (TenantContext::isPlatform()) {
            $this->error('The demo reset only runs inside a tenant: use --domain=<tenant host>. The platform database is never reset.');

            return self::FAILURE;
        }
        if (!$this->option('force') && !$this->confirm('This deletes every record of ' . $this->host() . '. Continue?', false)) {
            $this->line('Aborted.');

            return self::FAILURE;
        }

        $host = $this->host();
        $settings = $baseline->resolve((bool) $this->option('recapture'));

        $steps = [
            'migrate' => ['migrate:fresh', '--force'],
            'passport_client' => ['passport:client', '--personal', '--name=' . config('app.name') . ' Personal Access Client'],
            'permissions' => ['db:seed', '--class=PermissionsSeeder', '--force'],
            // LTI key set (packages/lti), kept in the tenant database like the Passport client
            'lti_keys' => $this->hasCommand('ulams:lti:rotate-keys') ? ['ulams:lti:rotate-keys', '--init'] : null,
            'demo_users' => DemoBaseline::seedDemoArguments($settings),
            'demo_content' => ['ulams:demo:seed'],
        ];
        $steps = array_filter($steps);

        $started = microtime(true);
        $this->info("Resetting demo tenant {$host}");
        try {
            // before the wipe: the H5P service keeps the tenant's content outside the tables
            // migrate:fresh drops (schema h5p, tenant bucket)
            $this->cleanH5P($h5p);
            if ($this->option('wipe-files') || config('ulams_demo.reset.wipe_files')) {
                $this->line('  <info>run</info>   files');
                $this->wipeFiles();
            }
            foreach ($steps as $step => $arguments) {
                $this->line("  <info>run</info>   {$step}");
                $runner->run($host, $arguments);
                if ($step === 'migrate') {
                    // Every access token is gone with the oauth tables; drop cached state too.
                    $this->purgeCache($redis);
                }
            }
            $this->purgeCache($redis);
        } catch (Throwable $exception) {
            $this->error('Demo reset failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Demo tenant %s reset in %.0f s.', $host, microtime(true) - $started));

        return self::SUCCESS;
    }

    /**
     * A failure does not stop the reset: the rows stay in `h5p.contents`, which survives
     * `migrate:fresh`, so the next reset deletes them.
     */
    private function cleanH5P(H5PContentCleaner $h5p): void
    {
        if (!$h5p->available()) {
            $this->line('  <comment>skip</comment>  h5p (no H5P service client)');

            return;
        }

        $this->line('  <info>run</info>   h5p');
        try {
            $result = $h5p->clean();
        } catch (H5PServiceException $exception) {
            $this->warn('  H5P content not deleted, the next reset retries: ' . $exception->getMessage());

            return;
        }

        $this->line(sprintf(
            '        %d content(s) deleted, %d already gone, %d orphaned content folder(s) (%d files) removed',
            $result['deleted'],
            $result['missing'],
            $result['orphan_contents'],
            $result['orphan_files'],
        ));
        foreach ($result['failed'] as $id => $message) {
            $this->warn("  H5P content {$id} not deleted, the next reset retries: {$message}");
        }
    }

    private function hasCommand(string $name): bool
    {
        return $this->getApplication()?->has($name) ?? false;
    }

    private function host(): string
    {
        $app = $this->laravel;
        if (method_exists($app, 'domain') && is_string($app->domain()) && $app->domain() !== '') {
            return $app->domain();
        }

        return (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    /**
     * Only this tenant's cache keys: `cache:clear` on the shared Redis store would flush the
     * cache of every tenant.
     */
    private function purgeCache(RedisKeyPurger $redis): void
    {
        $store = (string) config('cache.default');
        if (config("cache.stores.{$store}.driver") !== 'redis') {
            Cache::store()->flush();

            return;
        }

        $prefix = (string) config('database.redis.options.prefix', '')
            . (string) config("cache.stores.{$store}.prefix", config('cache.prefix', ''));
        if ($prefix === '') {
            $this->warn('  cache not cleared: the Redis cache has no tenant prefix');

            return;
        }
        $redis->purge($prefix);
    }

    private function wipeFiles(): void
    {
        $disk = Storage::disk();
        foreach (array_chunk($disk->allFiles(), 500) as $files) {
            $disk->delete($files);
        }
    }
}
