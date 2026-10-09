<?php

namespace Ulams\Demo\Support;

use Illuminate\Filesystem\Filesystem;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Ulams\Demo\UlamsDemoServiceProvider;
use Ulams\Settings\Models\Setting;

/**
 * What a reset rebuilds the tenant with: display name, theme, accent, front URL, number of
 * demo students and their e-mail domain. They live in the tenant database (settings set by
 * `ulams:tenant:seed-demo`), which the reset wipes, so they are captured once into the
 * tenant's storage directory and reused by every later reset. Changes an admin makes in the
 * demo therefore never survive a reset. `ulams:demo:reset --recapture` takes a new baseline.
 */
class DemoBaseline
{
    public const FILE = 'app/ulams-demo-baseline.json';

    private const SETTINGS = [
        'name' => ['global', 'companyName'],
        'front_url' => ['global', 'frontURL'],
        'theme' => ['theme', 'theme'],
        'accent' => ['theme', 'accent'],
    ];

    public function __construct(private Filesystem $files)
    {
    }

    public function path(): string
    {
        return storage_path(self::FILE);
    }

    /**
     * The stored baseline, or a new one captured from the current database (and stored).
     *
     * @return array{name: ?string, front_url: ?string, theme: ?string, accent: ?string, students: int, email_domain: string}
     */
    public function resolve(bool $recapture = false): array
    {
        if (!$recapture && $this->files->exists($this->path())) {
            $stored = json_decode((string) $this->files->get($this->path()), true);
            if (is_array($stored)) {
                return $this->normalise($stored);
            }
        }

        $baseline = $this->capture();
        $this->files->ensureDirectoryExists(dirname($this->path()));
        $this->files->put($this->path(), json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $baseline;
    }

    /**
     * Arguments of `ulams:tenant:seed-demo` that rebuild the baseline.
     *
     * @param array{name: ?string, front_url: ?string, theme: ?string, accent: ?string, students: int, email_domain: string} $baseline
     * @return list<string>
     */
    public static function seedDemoArguments(array $baseline): array
    {
        return array_values(array_filter([
            'ulams:tenant:seed-demo',
            '--users=' . $baseline['students'],
            $baseline['name'] ? '--name=' . $baseline['name'] : null,
            $baseline['theme'] ? '--theme=' . $baseline['theme'] : null,
            $baseline['accent'] ? '--accent=' . $baseline['accent'] : null,
            $baseline['front_url'] ? '--front-url=' . $baseline['front_url'] : null,
            '--email-domain=' . $baseline['email_domain'],
        ]));
    }

    /** @return array{name: ?string, front_url: ?string, theme: ?string, accent: ?string, students: int, email_domain: string} */
    private function capture(): array
    {
        $values = [];
        foreach (self::SETTINGS as $field => [$group, $key]) {
            $value = Setting::query()->where(['group' => $group, 'key' => $key])->value('value');
            $values[$field] = is_string($value) && $value !== '' ? $value : null;
        }

        $domain = $this->emailDomain();
        $model = config('auth.providers.users.model');
        try {
            $values['students'] = $model::query()
                ->role('student', 'api')
                ->where('email', 'like', 'student%@' . $domain)
                ->count();
        } catch (RoleDoesNotExist) {
            $values['students'] = 0;
        }
        $values['email_domain'] = $domain;

        return $this->normalise($values);
    }

    /** @return array{name: ?string, front_url: ?string, theme: ?string, accent: ?string, students: int, email_domain: string} */
    private function normalise(array $values): array
    {
        $config = UlamsDemoServiceProvider::CONFIG_KEY;
        $students = (int) ($values['students'] ?? 0);

        return [
            'name' => $values['name'] ?? (config('app.name') ?: null),
            'front_url' => $values['front_url'] ?? (config($config . '.front_url') ?: null),
            'theme' => $values['theme'] ?? null,
            'accent' => $values['accent'] ?? null,
            'students' => $students > 0 ? $students : max(1, (int) config($config . '.reset.students', 5)),
            'email_domain' => (string) (($values['email_domain'] ?? null) ?: $this->emailDomain()),
        ];
    }

    private function emailDomain(): string
    {
        $admin = (string) config(UlamsDemoServiceProvider::CONFIG_KEY . '.admin_email');
        if (str_contains($admin, '@')) {
            return substr($admin, strpos($admin, '@') + 1);
        }

        return (config('ulams_tenancy.tenant_slug') ?: 'demo') . '.ulams.app';
    }
}
