<?php

namespace Ulams\CourseBuilder\CourseAsCode;

/**
 * Reads back the GIFT that `GiftRenderer` writes (`stem {=right#why ~wrong}`, weighted multiple choice,
 * `{T}`/`{F}`, short answers), so an admin's edit of a question text can flow into the blueprint. It reads
 * only what the renderer writes; anything else returns null and the caller keeps the blueprint value.
 */
final class GiftReader
{
    /** @return array{stem:string,answers:array<int,array{text:string,correct:bool,feedback:?string}>,truth:?bool}|null */
    public static function read(string $gift): ?array
    {
        $gift = trim($gift);
        if (!str_ends_with($gift, '}') || self::escapedAt($gift, strlen($gift) - 1)) {
            return null;
        }
        $open = null;
        $len = strlen($gift);
        for ($i = 0; $i < $len; $i++) {
            if ($gift[$i] === '\\') {
                $i++;
            } elseif ($gift[$i] === '{') {
                $open = $i;
            }
        }
        if ($open === null) {
            return null;
        }
        $stem = self::unescape(trim(substr($gift, 0, $open)));
        $body = trim(substr($gift, $open + 1, $len - $open - 2));
        if ($body === 'T' || $body === 'F') {
            return ['stem' => $stem, 'answers' => [], 'truth' => $body === 'T'];
        }

        $answers = [];
        $current = null;
        $text = '';
        $flush = function () use (&$answers, &$current, &$text): void {
            if ($current === null) {
                return;
            }
            $feedback = null;
            $weight = null;
            $raw = $text;
            if ($current === '~' && preg_match('/^%(-?[0-9.]+)%(.*)$/s', $raw, $m)) {
                $weight = (float) $m[1];
                $raw = $m[2];
            }
            $hash = self::unescapedPos($raw, '#');
            if ($hash !== null) {
                $feedback = trim(self::unescape(substr($raw, $hash + 1)));
                $raw = substr($raw, 0, $hash);
            }
            $answers[] = ['text' => trim(self::unescape($raw)), 'correct' => $current === '=' || ($weight !== null && $weight > 0), 'feedback' => $feedback];
        };
        for ($i = 0, $n = strlen($body); $i < $n; $i++) {
            $c = $body[$i];
            if ($c === '\\' && $i + 1 < $n) {
                $text .= $c . $body[++$i];
            } elseif ($c === '=' || $c === '~') {
                $flush();
                $current = $c;
                $text = '';
            } else {
                $text .= $c;
            }
        }
        $flush();

        return $answers === [] ? null : ['stem' => $stem, 'answers' => $answers, 'truth' => null];
    }

    private static function escapedAt(string $text, int $index): bool
    {
        $slashes = 0;
        for ($i = $index - 1; $i >= 0 && $text[$i] === '\\'; $i--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }

    private static function unescapedPos(string $text, string $char): ?int
    {
        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            if ($text[$i] === '\\') {
                $i++;
            } elseif ($text[$i] === $char) {
                return $i;
            }
        }

        return null;
    }

    private static function unescape(string $text): string
    {
        return (string) preg_replace('/\\\\([\\\\~=#{}:])/', '$1', $text);
    }
}
