<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Site\NewSite;
use Ulams\CourseBuilder\Transfer\SessionArchive;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Jobs\ProvisionAcademyJob;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\Settings\Support\ThemePresets;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Support\TenantContext;
use Ulams\Tenancy\Support\TenantNaming;

/**
 * An academy of its own for a department (ADR 0141, amendment): a separate tenant created from a template, with the
 * department's theme, where the department's reviewed course is moved as a builder session and applied again. The
 * database, the bucket and the object-store identity are those every tenant gets, one set per academy.
 *
 * It follows "new site" of the builder (ADR 0048): the platform creates the tenant (`ulams:tenant:create` under the
 * platform host, which records it as created for this variant, enforces the quota and skips finished steps when run
 * again), the session archive is imported there and the author is invited. Provisioning a database is an operator-sized
 * action, so it needs `TENANCY_NEW_SITES` and `platform_admin`, exactly like a new site. The academy does not take over
 * the policy: certificates, the dashboard and later changes of the policy stay with this tenant; people of the academy are
 * invited there (user import) and trained in its own copy of the course.
 */
final class DepartmentAcademy
{
    public const PURPOSE = 'policy_department';

    public function __construct(private readonly NewSite $newSite, private readonly SessionArchive $archive, private readonly PolicyAudit $audit)
    {
    }

    /** The process is a tenant, "new site" is switched on and the user is a platform administrator. */
    public function available(?Authenticatable $user): bool
    {
        return !TenantContext::isPlatform() && $this->newSite->available($user);
    }

    /** @return list<string> theme presets a department academy can use */
    public static function themes(): array
    {
        return ThemePresets::names();
    }

    /** @return array<string,mixed>|null */
    public function status(PolicyVariant $variant): ?array
    {
        return $variant->academy;
    }

    /**
     * Asks for the academy: records the request and queues the work.
     *
     * @param array{theme?: ?string, accent?: ?string, template?: ?string, name?: ?string} $input
     *
     * @throws PolicyException
     */
    public function start(Policy $policy, PolicyVariant $variant, Authenticatable $actor, array $input): void
    {
        if (!$this->available($actor)) {
            throw new PolicyException('An academy of its own is created by the platform: it needs a platform administrator and TENANCY_NEW_SITES.', 403);
        }
        $current = (string) ($variant->academy['status'] ?? '');
        if (in_array($current, ['queued', 'provisioning', 'transferring'], true)) {
            throw new PolicyException('The academy is already being created.', 409);
        }
        if ($current === 'done') {
            throw new PolicyException('This department already has an academy. Remove it first to create another.', 409);
        }
        $session = $variant->session_id !== null ? Session::query()->find($variant->session_id) : null;
        if ($session === null || $session->current_version_id === null) {
            throw new PolicyException('Generate the department course first: there is nothing to move to an academy yet.', 409);
        }

        $theme = isset($input['theme']) && $input['theme'] !== '' ? (string) $input['theme'] : null;
        if ($theme !== null && !in_array($theme, self::themes(), true)) {
            throw new PolicyException('The theme is one of: ' . implode(', ', self::themes()) . '.', 422);
        }
        $accent = isset($input['accent']) && $input['accent'] !== '' ? strtoupper((string) $input['accent']) : null;
        if ($accent !== null && !preg_match(ThemePresets::ACCENT_PATTERN, $accent)) {
            throw new PolicyException('The accent is a colour like #C2552D.', 422);
        }
        $template = isset($input['template']) && $input['template'] !== '' ? (string) $input['template'] : (config('ulams_policy_lifecycle.academy_template') ?: null);
        if ($template !== null && !preg_match('/^[a-z][a-z0-9-]{1,38}$/', $template)) {
            throw new PolicyException('The template is a slug of lowercase letters, digits and hyphens.', 422);
        }
        $slug = (string) ($variant->academy['slug'] ?? $this->slugFor($variant));
        try {
            TenantNaming::assertValidSlug($slug);
        } catch (InvalidArgumentException $e) {
            throw new PolicyException($e->getMessage(), 422);
        }
        $name = trim(preg_replace('/[<>]/', '', (string) ($input['name'] ?? '')) ?? '') ?: $policy->title . ' / ' . $variant->department_label;

        $this->save($variant, [
            'status' => 'queued', 'slug' => $slug, 'name' => mb_substr($name, 0, 100), 'theme' => $theme, 'accent' => $accent, 'template' => $template,
            'requestedBy' => (string) ($actor->email ?? $actor->getAuthIdentifier()), 'requestedAt' => now()->toIso8601String(), 'error' => null,
        ]);
        $this->audit->record('policy.academy.requested', $policy, null, ['department' => $variant->department_label, 'slug' => $slug, 'theme' => $theme, 'template' => $template], $variant->session_id);
        ProvisionAcademyJob::dispatchFor($variant->id);
    }

    /** Runs in the queue worker. Safe to run again after a failure: the tenant steps resume, an import already done is not repeated. */
    public function run(PolicyVariant $variant): void
    {
        $info = (array) $variant->academy;
        $policy = Policy::query()->findOrFail($variant->policy_id);
        $session = Session::query()->find($variant->session_id);
        $slug = (string) $info['slug'];
        $runner = app(TenantCommandRunnerContract::class);
        $platformHost = (string) config('ulams_tenancy.platform_command_host', 'api.localhost');
        $archive = null;
        try {
            if ($session === null) {
                throw new RuntimeException('The department course no longer exists.');
            }
            $this->save($variant, ['status' => 'provisioning']);
            $runner->run($platformHost, array_values(array_filter([
                'ulams:tenant:create', $slug, '--users=0',
                '--name=' . $info['name'],
                ($info['theme'] ?? null) ? '--theme=' . $info['theme'] : null,
                ($info['accent'] ?? null) ? '--accent=' . $info['accent'] : null,
                ($info['template'] ?? null) ? '--template=' . $info['template'] : null,
                '--derived-from=' . TenantContext::slug(), '--purpose=' . self::PURPOSE, '--reference=' . $variant->id,
                '--requested-by=' . ($info['requestedBy'] ?? ''),
            ], fn ($argument) => $argument !== null)));

            $attrs = TenantNaming::newTenantAttributes($slug);
            if (empty($info['sessionId'])) {
                $this->save($variant, ['status' => 'transferring']);
                // a name of its own per run: PHP keeps a read archive cached by path
                $archive = sys_get_temp_dir() . '/policy-academy-' . $variant->id . '-' . Str::lower(Str::random(8)) . '.tar';
                $this->archive->export($session, $archive);
                $author = $session->author;
                $output = $runner->run($attrs['api_host'], [
                    'course-builder:session:import', $archive,
                    '--author-email=' . $author->email,
                    '--author-name=' . trim(($author->first_name ?? '') . ' ' . ($author->last_name ?? '')),
                ]);
                $result = json_decode(trim((string) collect(explode("\n", trim($output)))->last()), true);
                if (!is_array($result) || empty($result['sessionId'])) {
                    throw new RuntimeException('The academy did not confirm the import.');
                }
                $info = ['sessionId' => $result['sessionId'], 'invited' => (bool) ($result['invited'] ?? false)];
            } else {
                $info = ['sessionId' => $info['sessionId'], 'invited' => (bool) ($info['invited'] ?? false)];
            }
            $scheme = (string) config('ulams_tenancy.scheme', 'http');
            $this->save($variant, $info + [
                'status' => 'done',
                'studioUrl' => "{$scheme}://{$attrs['front_host']}/studio/s/{$info['sessionId']}",
                'adminUrl' => "{$scheme}://{$attrs['admin_host']}",
                'frontUrl' => "{$scheme}://{$attrs['front_host']}",
            ]);
            $this->audit->record('policy.academy.provisioned', $policy, null, ['department' => $variant->department_label, 'slug' => $slug], $variant->session_id);
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 500);
            $this->save($variant, ['status' => 'failed', 'error' => $message]);
            $this->audit->record('policy.academy.failed', $policy, null, ['department' => $variant->department_label, 'slug' => $slug, 'error' => mb_substr($message, 0, 200)], $variant->session_id);
        } finally {
            if ($archive !== null) {
                @unlink($archive);
            }
        }
    }

    /**
     * Removes the academy: its tenant is deleted with everything in it (database, files, settings). The record of who
     * created it stays on the platform.
     *
     * @throws PolicyException
     */
    public function remove(Policy $policy, PolicyVariant $variant, Authenticatable $actor): void
    {
        if (!$this->available($actor)) {
            throw new PolicyException('Removing an academy is a platform action: it needs a platform administrator and TENANCY_NEW_SITES.', 403);
        }
        $info = (array) $variant->academy;
        if ($info === [] || empty($info['slug'])) {
            throw new PolicyException('This department has no academy.', 404);
        }
        if (in_array($info['status'] ?? '', ['queued', 'provisioning', 'transferring'], true)) {
            throw new PolicyException('The academy is being created: wait for it to finish.', 409);
        }
        try {
            app(TenantCommandRunnerContract::class)->run((string) config('ulams_tenancy.platform_command_host', 'api.localhost'), ['ulams:tenant:delete', (string) $info['slug'], '--force']);
        } catch (Throwable $e) {
            throw new PolicyException('The academy could not be removed: ' . mb_substr($e->getMessage(), 0, 300), 502);
        }
        $variant->forceFill(['academy' => null])->save();
        $this->audit->record('policy.academy.removed', $policy, null, ['department' => $variant->department_label, 'slug' => $info['slug']], $variant->session_id);
    }

    /** The parent's slug, the department's name and four characters of its id: readable, within 30 characters, unique per variant. */
    private function slugFor(PolicyVariant $variant): string
    {
        $parent = (string) TenantContext::slug();
        $name = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($variant->department_label))) ?: 'dept';

        return substr($parent, 0, 14) . substr($name, 0, 8) . substr(md5($variant->id), 0, 4);
    }

    /** @param array<string,mixed> $changes */
    private function save(PolicyVariant $variant, array $changes): void
    {
        $variant->refresh();
        $variant->forceFill(['academy' => array_merge((array) $variant->academy, $changes)])->save();
    }
}
