<?php

namespace Ulams\Identity\Services\Scim;

/**
 * Parser for the SCIM filter grammar of RFC 7644 3.4.2.2: comparison operators `eq ne co sw ew gt ge lt le`, `pr`,
 * `and`, `or`, `not ( ... )`, parentheses and value paths (`emails[type eq "work" and value co "@x.com"]`).
 * `not` binds tighter than `and`, which binds tighter than `or`.
 *
 * It only builds a tree; nothing is evaluated or put in a query here. Every failure is a `ScimError` of type
 * `invalidFilter`, whatever the input (the fuzz test feeds it random bytes), and length and nesting are capped.
 *
 * Nodes:
 *   ['t' => 'cmp', 'attr' => string, 'op' => string, 'value' => string|int|float|bool|null]
 *   ['t' => 'pr', 'attr' => string]
 *   ['t' => 'and'|'or', 'l' => node, 'r' => node]
 *   ['t' => 'not', 'n' => node]
 *   ['t' => 'path', 'attr' => string, 'filter' => node]
 */
final class FilterParser
{
    public const MAX_LENGTH = 1000;
    public const MAX_DEPTH = 16;
    public const OPERATORS = ['eq', 'ne', 'co', 'sw', 'ew', 'gt', 'ge', 'lt', 'le'];

    /** @var list<array{0:string,1:mixed}> */
    private array $tokens = [];
    private int $pos = 0;

    /** @return array<string,mixed> */
    public function parse(string $filter): array
    {
        if (trim($filter) === '') {
            throw ScimError::invalidFilter('The filter is empty.');
        }
        if (strlen($filter) > self::MAX_LENGTH) {
            throw ScimError::invalidFilter('The filter is longer than ' . self::MAX_LENGTH . ' characters.');
        }
        if (!mb_check_encoding($filter, 'UTF-8')) {
            throw ScimError::invalidFilter('The filter is not valid UTF-8.');
        }
        $this->tokens = $this->tokenize($filter);
        $this->pos = 0;
        $node = $this->parseOr(0);
        if ($this->pos < count($this->tokens)) {
            throw ScimError::invalidFilter('Unexpected "' . $this->describe($this->tokens[$this->pos]) . '" in the filter.');
        }

        return $node;
    }

    /** @return list<array{0:string,1:mixed}> */
    private function tokenize(string $s): array
    {
        $tokens = [];
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            $c = $s[$i];
            if ($c === ' ' || $c === "\t" || $c === "\r" || $c === "\n") {
                $i++;
            } elseif ($c === '(' || $c === ')' || $c === '[' || $c === ']') {
                $tokens[] = [$c, $c];
                $i++;
            } elseif ($c === '"') {
                $start = $i;
                $i++;
                $closed = false;
                while ($i < $len) {
                    if ($s[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($s[$i] === '"') {
                        $closed = true;
                        break;
                    }
                    $i++;
                }
                if (!$closed) {
                    throw ScimError::invalidFilter('A string in the filter is not closed.');
                }
                $decoded = json_decode(substr($s, $start, $i - $start + 1), false);
                if (!is_string($decoded)) {
                    throw ScimError::invalidFilter('A string in the filter is not valid.');
                }
                $tokens[] = ['str', $decoded];
                $i++;
            } elseif (ctype_digit($c) || ($c === '-' && $i + 1 < $len && ctype_digit($s[$i + 1]))) {
                if (!preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', $s, $m, 0, $i)) {
                    throw ScimError::invalidFilter('A number in the filter is not valid.');
                }
                $tokens[] = ['num', str_contains($m[0], '.') || stripos($m[0], 'e') !== false ? (float) $m[0] : (int) $m[0]];
                $i += strlen($m[0]);
            } elseif (preg_match('/\G[A-Za-z$][A-Za-z0-9$_.:\-]*/', $s, $m, 0, $i)) {
                $word = $m[0];
                $lower = strtolower($word);
                $tokens[] = in_array($lower, ['and', 'or', 'not', 'pr', 'true', 'false', 'null', ...self::OPERATORS], true) ? ['kw', $lower] : ['attr', $word];
                $i += strlen($word);
            } else {
                throw ScimError::invalidFilter('Unexpected character in the filter.');
            }
            if (count($tokens) > 200) {
                throw ScimError::invalidFilter('The filter is too complex.');
            }
        }

        return $tokens;
    }

    /** @return array<string,mixed> */
    private function parseOr(int $depth): array
    {
        $node = $this->parseAnd($depth);
        while ($this->isKeyword('or')) {
            $this->pos++;
            $node = ['t' => 'or', 'l' => $node, 'r' => $this->parseAnd($depth)];
        }

        return $node;
    }

    /** @return array<string,mixed> */
    private function parseAnd(int $depth): array
    {
        $node = $this->parseUnary($depth);
        while ($this->isKeyword('and')) {
            $this->pos++;
            $node = ['t' => 'and', 'l' => $node, 'r' => $this->parseUnary($depth)];
        }

        return $node;
    }

    /** @return array<string,mixed> */
    private function parseUnary(int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw ScimError::invalidFilter('The filter is nested too deeply.');
        }
        if ($this->isKeyword('not')) {
            $this->pos++;
            $this->expect('(');
            $inner = $this->parseOr($depth + 1);
            $this->expect(')');

            return ['t' => 'not', 'n' => $inner];
        }
        if ($this->peek() === '(') {
            $this->pos++;
            $inner = $this->parseOr($depth + 1);
            $this->expect(')');

            return $inner;
        }

        return $this->parseTerm($depth);
    }

    /** @return array<string,mixed> */
    private function parseTerm(int $depth): array
    {
        $token = $this->tokens[$this->pos] ?? null;
        if ($token === null || $token[0] !== 'attr') {
            throw ScimError::invalidFilter($token === null ? 'The filter ends where an attribute was expected.' : 'Expected an attribute name, found "' . $this->describe($token) . '".');
        }
        $attr = (string) $token[1];
        $this->pos++;
        if ($this->peek() === '[') {
            $this->pos++;
            $inner = $this->parseOr($depth + 1);
            $this->expect(']');

            return ['t' => 'path', 'attr' => $attr, 'filter' => $inner];
        }
        if ($this->isKeyword('pr')) {
            $this->pos++;

            return ['t' => 'pr', 'attr' => $attr];
        }
        $op = $this->tokens[$this->pos] ?? null;
        if ($op === null || $op[0] !== 'kw' || !in_array($op[1], self::OPERATORS, true)) {
            throw ScimError::invalidFilter('Expected a comparison operator after the attribute.');
        }
        $this->pos++;
        $value = $this->tokens[$this->pos] ?? null;
        if ($value === null) {
            throw ScimError::invalidFilter('The filter ends where a value was expected.');
        }
        $this->pos++;
        $v = match (true) {
            $value[0] === 'str', $value[0] === 'num' => $value[1],
            $value[0] === 'kw' && $value[1] === 'true' => true,
            $value[0] === 'kw' && $value[1] === 'false' => false,
            $value[0] === 'kw' && $value[1] === 'null' => null,
            default => throw ScimError::invalidFilter('Expected a value after the operator, found "' . $this->describe($value) . '".'),
        };

        return ['t' => 'cmp', 'attr' => $attr, 'op' => $op[1], 'value' => $v];
    }

    private function peek(): ?string
    {
        $t = $this->tokens[$this->pos] ?? null;

        return $t !== null && in_array($t[0], ['(', ')', '[', ']'], true) ? $t[0] : null;
    }

    private function isKeyword(string $word): bool
    {
        $t = $this->tokens[$this->pos] ?? null;

        return $t !== null && $t[0] === 'kw' && $t[1] === $word;
    }

    private function expect(string $symbol): void
    {
        if ($this->peek() !== $symbol) {
            throw ScimError::invalidFilter("Expected \"{$symbol}\" in the filter.");
        }
        $this->pos++;
    }

    /** @param array{0:string,1:mixed} $t */
    private function describe(array $t): string
    {
        $text = is_scalar($t[1]) ? (string) $t[1] : $t[0];

        return mb_substr($text, 0, 40);
    }
}
