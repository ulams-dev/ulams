<?php

namespace Ulams\Tenancy\Services;

use InvalidArgumentException;
use Ulams\Tenancy\Models\TenantTemplate;
use Ulams\Tenancy\Support\TenantNaming;

/** Creates, changes and removes tenant templates (platform administrators only; see the platform API and the commands). */
class TenantTemplates
{
    public const SLUG_PATTERN = '/^[a-z][a-z0-9-]{1,38}$/';

    public function find(string $slug): ?TenantTemplate
    {
        return TenantTemplate::query()->firstWhere('slug', $slug);
    }

    /**
     * Creates the template or changes the given fields of it.
     *
     * @param array{name?: ?string, theme?: ?string, accent?: ?string, demo?: ?bool, users?: ?int, env?: ?array<string,string>} $attributes
     *
     * @throws InvalidArgumentException on an invalid slug or value
     */
    public function save(string $slug, array $attributes): TenantTemplate
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            throw new InvalidArgumentException("Invalid template slug '{$slug}': use 2-39 lowercase letters, digits and hyphens, starting with a letter.");
        }
        $template = $this->find($slug) ?? new TenantTemplate(['slug' => $slug, 'name' => ucfirst(str_replace('-', ' ', $slug))]);

        $values = [];
        if (($attributes['name'] ?? null) !== null) {
            $name = trim((string) $attributes['name']);
            if ($name === '' || mb_strlen($name) > 100 || preg_match('/[<>]/', $name)) {
                throw new InvalidArgumentException('The template name is 1 to 100 characters without < or >.');
            }
            $values['name'] = $name;
        }
        if (array_key_exists('theme', $attributes) && $attributes['theme'] !== null && $attributes['theme'] !== '') {
            if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) $attributes['theme'])) {
                throw new InvalidArgumentException("Invalid theme '{$attributes['theme']}'.");
            }
            $values['theme'] = (string) $attributes['theme'];
        }
        if (array_key_exists('accent', $attributes) && $attributes['accent'] !== null && $attributes['accent'] !== '') {
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $attributes['accent'])) {
                throw new InvalidArgumentException("Invalid accent '{$attributes['accent']}': use a colour like #C2552D.");
            }
            $values['accent'] = strtoupper((string) $attributes['accent']);
        }
        if (($attributes['demo'] ?? null) !== null) {
            $values['demo'] = (bool) $attributes['demo'];
        }
        if (($attributes['users'] ?? null) !== null) {
            $users = (int) $attributes['users'];
            if ($users < 0 || $users > 50) {
                throw new InvalidArgumentException('Demo users are 0 to 50.');
            }
            $values['users'] = $users;
        }
        if (($attributes['env'] ?? null) !== null) {
            $values['env_overrides'] = $this->env((array) $attributes['env']) ?: null;
        }
        $template->fill($values)->save();

        return $template;
    }

    /** @return bool whether a template was removed; the tenants made from it are untouched */
    public function delete(string $slug): bool
    {
        return (bool) TenantTemplate::query()->where('slug', $slug)->delete();
    }

    /**
     * @param array<string,mixed> $env
     *
     * @return array<string,string>
     */
    private function env(array $env): array
    {
        $allowed = TenantNaming::inheritableKeys();
        $out = [];
        foreach ($env as $key => $value) {
            $key = strtoupper(trim((string) $key));
            if (!in_array($key, $allowed, true) || !is_string($value) || $value === '') {
                throw new InvalidArgumentException('Template settings are KEY=value with one of: ' . implode(', ', $allowed));
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
