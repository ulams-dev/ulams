<?php

namespace Ulams\CourseBuilder\Site;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Ulams\CourseBuilder\Jobs\MoveToNewSiteJob;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Transfer\SessionArchive;
use Ulams\Tenancy\Enums\TenancyPermissionsEnum;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Support\TenantNaming;

/**
 * "New site" for a course (ADR 0048): provisions a tenant on the platform, moves the builder session
 * to it and invites the author there, so the author continues (apply, theme, publish) in the new site.
 * Switched on with TENANCY_NEW_SITES and open to people holding `tenancy_manage`; the progress is kept
 * in the session state (`newSite`) for the studio.
 */
final class NewSite
{
    public function __construct(private readonly SessionArchive $archive)
    {
    }

    public function available(?Authenticatable $user): bool
    {
        return class_exists(Tenant::class)
            && (bool) config('ulams_tenancy.new_sites', false)
            && $user !== null && method_exists($user, 'can') && $user->can(TenancyPermissionsEnum::TENANCY_MANAGE);
    }

    /** @return array<string,mixed> */
    public function status(Session $session): array
    {
        return (array) $session->stateValue('newSite', []);
    }

    /** @throws InvalidArgumentException when the slug or the session cannot be used */
    public function start(Session $session, string $slug, ?string $name): void
    {
        TenantNaming::assertValidSlug($slug);
        $current = (string) ($this->status($session)['status'] ?? '');
        if (in_array($current, ['queued', 'provisioning', 'transferring'], true)) {
            throw new InvalidArgumentException('The new site is already being created.');
        }
        if ($session->current_version_id === null) {
            throw new InvalidArgumentException('There is nothing to move yet: finish the outline first.');
        }
        $this->save($session, ['slug' => $slug, 'name' => $name, 'status' => 'queued', 'error' => null]);
        MoveToNewSiteJob::dispatchFor($session->id);
    }

    /** Runs in the queue worker. */
    public function run(Session $session): void
    {
        $info = $this->status($session);
        $slug = (string) $info['slug'];
        $runner = app(TenantCommandRunnerContract::class);
        $attrs = TenantNaming::newTenantAttributes($slug);
        $archive = null;
        try {
            $this->save($session, ['status' => 'provisioning']);
            $runner->run((string) config('ulams_tenancy.platform_command_host', 'api.localhost'), array_values(array_filter([
                'ulams:tenant:create', $slug, '--users=0',
                ($info['name'] ?? null) ? '--name=' . $info['name'] : null,
                ($session->brief['theme']['preset'] ?? null) ? '--theme=' . $session->brief['theme']['preset'] : null,
                ($session->brief['theme']['accent'] ?? null) ? '--accent=' . $session->brief['theme']['accent'] : null,
            ])));

            $this->save($session, ['status' => 'transferring']);
            $archive = sys_get_temp_dir() . '/cb-move-' . $session->id . '.tar';
            $this->archive->export($session, $archive);
            $author = $session->author;
            $output = $runner->run($attrs['api_host'], [
                'course-builder:session:import', $archive,
                '--author-email=' . $author->email,
                '--author-name=' . trim(($author->first_name ?? '') . ' ' . ($author->last_name ?? '')),
            ]);
            $result = json_decode(trim((string) collect(explode("\n", trim($output)))->last()), true);
            if (!is_array($result) || empty($result['sessionId'])) {
                throw new RuntimeException('The new site did not confirm the import.');
            }
            $scheme = (string) config('ulams_tenancy.scheme', 'http');
            $this->save($session, [
                'status' => 'done',
                'sessionId' => $result['sessionId'],
                'invited' => (bool) ($result['invited'] ?? false),
                'studioUrl' => "{$scheme}://{$attrs['front_host']}/studio/s/{$result['sessionId']}",
                'adminUrl' => "{$scheme}://{$attrs['admin_host']}",
            ]);
        } catch (Throwable $e) {
            $this->save($session, ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        } finally {
            if ($archive !== null) {
                @unlink($archive);
            }
        }
    }

    /** @param array<string,mixed> $changes */
    private function save(Session $session, array $changes): void
    {
        $session->refresh();
        $session->putState('newSite', array_merge($this->status($session), $changes));
        $session->save();
    }
}
