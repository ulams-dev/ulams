<?php

namespace Ulams\Tenancy\Upgrade;

/**
 * The steps every installation has, in the order they must run: the platform first (it rebuilds the
 * tenants' env files and keys that the tenant steps need), then each tenant.
 */
class DefaultUpgradeSteps
{
    public static function register(): void
    {
        // rebuild .env.<host>, registrations and Passport keys of every tenant (a fresh container loses them)
        UpgradeSteps::command('sync_env', 'ulams:tenant:sync-env', once: false, scope: UpgradeSteps::PLATFORM);
        // least-privilege env files and public keys the H5P service mounts in production (no-op without H5P_SERVICE_CONFIG_DIR)
        UpgradeSteps::command('h5p_export_config', 'ulams:h5p:export-config', once: false, scope: UpgradeSteps::PLATFORM);
        UpgradeSteps::command('migrate', 'migrate', ['--force' => true], once: false);
        // new permissions of the release (the seeder only adds what is missing)
        UpgradeSteps::command('permissions', 'db:seed', ['--class' => 'PermissionsSeeder', '--force' => true], once: false);
        // per-tenant LTI signing keys (ADR 0012); a no-op when the key set exists
        UpgradeSteps::command('lti_keys', 'ulams:lti:rotate-keys', ['--init' => true], once: false, requiresCommand: true);
        // SCORM and cmi5 packages uploaded to the local disk move to the bucket (L0-09)
        UpgradeSteps::command('cmi5_move_to_bucket', 'cmi5:move-to-bucket', since: '0.2', requiresCommand: true);
        UpgradeSteps::register('meetings_purge_frames', function (UpgradeContext $context): string {
            // the dry run is logged first, so the report shows what the real run removes
            $preview = $context->artisan('meetings:purge-frames', ['--dry-run' => true]);

            return trim($preview . "\n" . $context->artisan('meetings:purge-frames'));
        }, since: '0.2', requiresCommand: 'meetings:purge-frames');
        // views created before the package rename carry the old class names
        UpgradeSteps::command('recreate_views', 'ulams:db:recreate-views', since: '0.2');
    }
}
