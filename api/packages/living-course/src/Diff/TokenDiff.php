<?php

namespace Ulams\LivingCourse\Diff;

/**
 * Token-level diff of two texts (ADR 0031): words, numbers, punctuation, inline code spans and
 * fenced code lines are tokens. Gives a compact word diff for the UI and the signals that decide
 * the magnitude of a change: a number, code, an identifier or a negation/modality word changed, or
 * more than a set share of the old tokens changed.
 */
final class TokenDiff
{
    private const TOKEN = '/`[^`\n]*`|--?[A-Za-z][\w-]*|[\p{L}\p{N}_]+(?:(?:::|[.:\/\-])[\p{L}\p{N}_]+)*(?:\(\))?|[^\s\p{L}\p{N}_]/u';

    /** Above this many token pairs the LCS is skipped and the middle part is reported as replaced. */
    private const MAX_CELLS = 2500000;

    /**
     * @return array{ops:array<int,array{0:string,1:string}>,removed:int,added:int,oldCount:int,signals:string[]}
     */
    public static function diff(string $old, string $new): array
    {
        $a = self::tokens($old);
        $b = self::tokens($new);
        $pairs = self::align(array_column($a, 't'), array_column($b, 't'));

        $ops = [];
        $push = function (string $type, string $text) use (&$ops) {
            if ($text === '') {
                return;
            }
            if ($ops !== [] && $ops[count($ops) - 1][0] === $type) {
                $ops[count($ops) - 1][1] .= $text;
            } else {
                $ops[] = [$type, $text];
            }
        };
        $removed = [];
        $added = [];
        $i = 0;
        $j = 0;
        foreach ([...$pairs, [count($a), count($b)]] as [$ai, $bj]) {
            // tokens between the previous match and this one are removed (old side) and added (new side)
            $minus = '';
            for (; $i < $ai; $i++) {
                $minus .= $a[$i]['pre'] . $a[$i]['t'];
                $removed[] = $a[$i];
            }
            $plus = '';
            for (; $j < $bj; $j++) {
                $plus .= $b[$j]['pre'] . $b[$j]['t'];
                $added[] = $b[$j];
            }
            $push('-', $minus);
            $push('+', $plus);
            if ($ai < count($a)) {
                $push('=', $b[$bj]['pre'] . $b[$bj]['t']);
                $i++;
                $j++;
            }
        }

        return [
            'ops' => $ops,
            'removed' => count($removed),
            'added' => count($added),
            'oldCount' => count($a),
            'signals' => self::signals([...$removed, ...$added], count($a), max(count($removed), count($added))),
        ];
    }

    /**
     * @return array<int,array{t:string,pre:string,code:bool}>
     */
    public static function tokens(string $text): array
    {
        $out = [];
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split('/(^[ \t]{0,3}(?:```|~~~).*?^[ \t]{0,3}(?:```|~~~)[ \t]*$)/ms', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $pre = '';
        foreach ($parts as $n => $part) {
            if ($n % 2 === 1) {
                // a fenced block: every line is one token
                foreach (explode("\n", $part) as $line) {
                    if (trim($line) === '') {
                        continue;
                    }
                    $out[] = ['t' => rtrim($line), 'pre' => $pre === '' ? "\n" : $pre . "\n", 'code' => true];
                    $pre = '';
                }
                continue;
            }
            $offset = 0;
            if (preg_match_all(self::TOKEN, $part, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [$token, $at]) {
                    $out[] = ['t' => $token, 'pre' => $pre . substr($part, $offset, $at - $offset), 'code' => $token[0] === '`' && strlen($token) > 1];
                    $pre = '';
                    $offset = $at + strlen($token);
                }
            }
            $pre = substr($part, $offset);
        }
        if ($out !== []) {
            $out[0]['pre'] = ltrim($out[0]['pre']);
        }

        return $out;
    }

    /**
     * Longest common subsequence of two token lists, as matched index pairs.
     *
     * @param string[] $a
     * @param string[] $b
     * @return array<int,array{0:int,1:int}>
     */
    private static function align(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $start = 0;
        while ($start < $n && $start < $m && $a[$start] === $b[$start]) {
            $start++;
        }
        $endA = $n;
        $endB = $m;
        while ($endA > $start && $endB > $start && $a[$endA - 1] === $b[$endB - 1]) {
            $endA--;
            $endB--;
        }
        $pairs = [];
        for ($k = 0; $k < $start; $k++) {
            $pairs[] = [$k, $k];
        }
        $rows = $endA - $start;
        $cols = $endB - $start;
        if ($rows > 0 && $cols > 0 && $rows * $cols <= self::MAX_CELLS) {
            $table = array_fill(0, $rows + 1, array_fill(0, $cols + 1, 0));
            for ($x = $rows - 1; $x >= 0; $x--) {
                for ($y = $cols - 1; $y >= 0; $y--) {
                    $table[$x][$y] = $a[$start + $x] === $b[$start + $y]
                        ? $table[$x + 1][$y + 1] + 1
                        : max($table[$x + 1][$y], $table[$x][$y + 1]);
                }
            }
            $x = 0;
            $y = 0;
            while ($x < $rows && $y < $cols) {
                if ($a[$start + $x] === $b[$start + $y]) {
                    $pairs[] = [$start + $x, $start + $y];
                    $x++;
                    $y++;
                } elseif ($table[$x + 1][$y] >= $table[$x][$y + 1]) {
                    $x++;
                } else {
                    $y++;
                }
            }
        }
        for ($k = 0; $k < $n - $endA; $k++) {
            $pairs[] = [$endA + $k, $endB + $k];
        }

        return $pairs;
    }

    /**
     * @param array<int,array{t:string,pre:string,code:bool}> $changed
     * @return string[]
     */
    private static function signals(array $changed, int $oldCount, int $changedCount): array
    {
        $modality = [];
        foreach ((array) config('living_course.diff.modality_words', []) as $words) {
            foreach ($words as $w) {
                $modality[mb_strtolower($w)] = true;
            }
        }
        $signals = [];
        foreach ($changed as $token) {
            $t = $token['t'];
            if ($token['code']) {
                $signals['code'] = true;
            }
            if (preg_match('/\d/', $t)) {
                $signals['number'] = true;
            } elseif (
                preg_match('/^[A-Za-z]+(?:_[A-Za-z0-9]+)+$/', $t) || preg_match('/^[a-z]+[A-Z][A-Za-z0-9]*$/', $t)
                || str_contains($t, '::') || str_ends_with($t, '()') || (str_contains($t, '.') && preg_match('/^[\p{L}_]+\.[\p{L}_.]+$/u', $t))
                || (str_starts_with($t, '-') && strlen($t) > 1 && !$token['code'])
            ) {
                $signals['identifier'] = true;
            }
            if (isset($modality[mb_strtolower($t)])) {
                $signals['modality'] = true;
            }
        }
        $ratio = (float) config('living_course.diff.substantive_ratio', 0.15);
        if ($oldCount > 0 && $changedCount / $oldCount > $ratio) {
            $signals['large'] = true;
        }

        return array_keys($signals);
    }

    /** Caps the compact diff: long unchanged runs shrink to their ends, then the tail is dropped. */
    public static function cap(array $ops, int $maxBytes): array
    {
        $size = fn (array $o) => strlen((string) json_encode($o, JSON_UNESCAPED_UNICODE));
        if ($size($ops) <= $maxBytes) {
            return $ops;
        }
        foreach ($ops as &$op) {
            if ($op[0] === '=' && mb_strlen($op[1]) > 160) {
                $op[1] = mb_substr($op[1], 0, 70) . ' … ' . mb_substr($op[1], -70);
            }
        }
        unset($op);
        $out = [];
        $bytes = 2;
        foreach ($ops as $op) {
            $len = $size($op) + 1;
            if ($bytes + $len > $maxBytes - 24) {
                $out[] = ['…', ''];
                break;
            }
            $out[] = $op;
            $bytes += $len;
        }

        return $out;
    }
}
