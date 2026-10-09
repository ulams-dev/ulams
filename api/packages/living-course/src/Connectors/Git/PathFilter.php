<?php

namespace Ulams\LivingCourse\Connectors\Git;

/**
 * Which repository files are source text: an extension list and path globs (`docs/**` + `/*.md`).
 * `**` crosses directories, `*` and `?` do not cross a slash. Dot directories (`.github`,
 * `.git`) and `node_modules` are never source.
 */
final class PathFilter
{
    /** @var string[] */
    private array $regexes;

    /**
     * @param string[] $globs
     * @param string[] $extensions without the dot
     */
    public function __construct(array $globs, private readonly array $extensions)
    {
        $this->regexes = array_map(self::toRegex(...), $globs === [] ? ['**/*'] : $globs);
    }

    public function accepts(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, array_map('strtolower', $this->extensions), true)) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === 'node_modules' || ($segment !== '' && $segment[0] === '.')) {
                return false;
            }
        }
        foreach ($this->regexes as $regex) {
            if (preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }

    public static function toRegex(string $glob): string
    {
        $out = '';
        $length = strlen($glob);
        for ($i = 0; $i < $length; $i++) {
            $c = $glob[$i];
            if ($c === '*' && ($glob[$i + 1] ?? '') === '*') {
                $i++;
                if (($glob[$i + 1] ?? '') === '/') {
                    $i++;
                    $out .= '(?:.*/)?';
                } else {
                    $out .= '.*';
                }
            } elseif ($c === '*') {
                $out .= '[^/]*';
            } elseif ($c === '?') {
                $out .= '[^/]';
            } else {
                $out .= preg_quote($c, '~');
            }
        }

        return '~^' . $out . '$~';
    }

    /** MDX is Markdown with JSX: import/export lines and component tags (an upper-case first letter) are dropped. */
    public static function stripMdx(string $text): string
    {
        $text = (string) preg_replace('/^(?:import|export)\s.*$/m', '', $text);
        $text = (string) preg_replace('~<([A-Z][A-Za-z0-9.]*)\b[^>]*?/>~s', '', $text);
        $text = (string) preg_replace('~<([A-Z][A-Za-z0-9.]*)\b[^>]*>.*?</\1>~s', '', $text);

        return $text;
    }
}
