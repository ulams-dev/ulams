<?php

namespace Ulams\CourseBuilder\Apply;

use Illuminate\Contracts\Auth\Authenticatable;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Settings\Services\Contracts\SettingsServiceContract;

/**
 * The theme step of an apply: writes `theme.theme` and `theme.accent` through the settings service,
 * but only when the author may change the site (`settings_manage`) or the site is new. Otherwise the
 * step is skipped with a note in the apply summary; the course itself is never blocked by it.
 */
final class SiteTheme
{
    public const PERMISSION = 'settings_manage';

    public function __construct(private readonly SettingsServiceContract $settings)
    {
    }

    public function authorMayChange(?Authenticatable $author, array $brief): bool
    {
        if (($brief['site']['mode'] ?? 'current') === 'new') {
            return true;
        }

        return $author !== null && method_exists($author, 'can') && $author->can(self::PERMISSION);
    }

    /** The theme preset the site uses now (the interview's default), or null when none is set. */
    public function current(): ?string
    {
        try {
            $value = (string) $this->settings->find('theme', 'theme')->value;
        } catch (\Throwable) {
            return null;
        }

        return in_array($value, ['coffee', 'oncall', 'nightsky', 'gravity', 'poland', 'ulam'], true) ? $value : null;
    }

    /**
     * @return array{applied:bool,note:?string}
     */
    public function apply(Session $session, Authenticatable $author): array
    {
        $theme = $session->brief['theme'] ?? null;
        if (!is_array($theme) || empty($theme['preset'])) {
            return ['applied' => false, 'note' => null];
        }
        if (!$this->authorMayChange($author, (array) $session->brief)) {
            return ['applied' => false, 'note' => 'The theme was not changed: only site admins can change the site theme. Ask an admin to pick it in Settings.'];
        }
        $this->settings->put('theme', 'theme', $theme['preset']);
        if (!empty($theme['accent'])) {
            $this->settings->put('theme', 'accent', $theme['accent']);
        }

        return ['applied' => true, 'note' => null];
    }
}
