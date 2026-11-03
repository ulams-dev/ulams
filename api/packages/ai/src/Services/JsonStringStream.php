<?php

namespace Ulams\Ai\Services;

/**
 * Pulls one top-level string field out of a JSON object that arrives in pieces, so the text of an answer
 * can be shown while the model is still writing the rest of the structured output. No dependency: it only
 * finds `"field": "` and decodes the JSON string that follows, escapes and surrogate pairs included, waiting
 * for more input when an escape sequence is cut in half. The result is a draft; the complete output is
 * always validated by the client.
 */
final class JsonStringStream
{
    private string $buffer = '';

    private int $offset = 0;

    private bool $started = false;

    private bool $done = false;

    public function __construct(private readonly string $field)
    {
    }

    public function done(): bool
    {
        return $this->done;
    }

    /** Feeds the next piece of raw output and returns the newly decoded text of the field ('' when none). */
    public function feed(string $chunk): string
    {
        if ($this->done) {
            return '';
        }
        $this->buffer .= $chunk;
        if (!$this->started) {
            if (!preg_match('/"' . preg_quote($this->field, '/') . '"\s*:\s*"/', $this->buffer, $m, PREG_OFFSET_CAPTURE)) {
                return '';
            }
            $this->started = true;
            $this->offset = $m[0][1] + strlen($m[0][0]);
        }

        $out = '';
        $length = strlen($this->buffer);
        while ($this->offset < $length) {
            $char = $this->buffer[$this->offset];
            if ($char === '"') {
                $this->done = true;
                $this->offset++;
                break;
            }
            if ($char !== '\\') {
                // copy the plain run up to the next quote or backslash in one step
                $run = strcspn($this->buffer, '"\\', $this->offset);
                $out .= substr($this->buffer, $this->offset, $run);
                $this->offset += $run;
                continue;
            }
            if ($this->offset + 1 >= $length) {
                break;
            }
            $next = $this->buffer[$this->offset + 1];
            if ($next !== 'u') {
                $out .= match ($next) {
                    'n' => "\n", 't' => "\t", 'r' => "\r", 'b' => "\x08", 'f' => "\x0c", default => $next,
                };
                $this->offset += 2;
                continue;
            }
            if ($this->offset + 6 > $length) {
                break;
            }
            $code = hexdec(substr($this->buffer, $this->offset + 2, 4));
            $size = 6;
            if ($code >= 0xD800 && $code <= 0xDBFF) {
                // a high surrogate needs its pair
                if ($this->offset + 12 > $length) {
                    break;
                }
                $low = hexdec(substr($this->buffer, $this->offset + 8, 4));
                $code = 0x10000 + (($code - 0xD800) << 10) + ($low - 0xDC00);
                $size = 12;
            }
            $out .= mb_chr((int) $code, 'UTF-8');
            $this->offset += $size;
        }

        // an incomplete multi-byte character at the end of a plain run waits for its remaining bytes
        $valid = $out;
        $tail = '';
        if ($out !== '' && !mb_check_encoding($out, 'UTF-8')) {
            for ($cut = 1; $cut <= 3 && $cut <= strlen($out); $cut++) {
                if (mb_check_encoding(substr($out, 0, -$cut), 'UTF-8')) {
                    $valid = substr($out, 0, -$cut);
                    $tail = substr($out, -$cut);
                    break;
                }
            }
            if ($tail !== '') {
                $this->offset -= strlen($tail);
            }
        }

        return $valid;
    }
}
