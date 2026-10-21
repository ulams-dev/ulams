<?php

namespace Ulams\Tutor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Tutor\Streaming\DeltaBuffer;

class DeltaBufferTest extends TestCase
{
    public function testItEmitsAtTheEndOfASentence(): void
    {
        $out = [];
        $buffer = new DeltaBuffer(function ($t) use (&$out) {
            $out[] = $t;
        }, 100, fn () => 0.0);

        foreach (['Water is ', 'wet. ', 'Ice is', ' cold.', ' More'] as $piece) {
            $buffer->push($piece);
        }
        $buffer->flush();

        $this->assertSame(['Water is wet. ', 'Ice is cold. More'], $out);
    }

    public function testItEmitsAfterTheIntervalAndFlushOfAnEmptyBufferEmitsNothing(): void
    {
        $now = 0.0;
        $out = [];
        $buffer = new DeltaBuffer(function ($t) use (&$out) {
            $out[] = $t;
        }, 100, function () use (&$now) {
            return $now;
        });

        $buffer->push('one ');
        $buffer->push('two ');
        $this->assertSame([], $out);
        $now = 150.0;
        $buffer->push('three');
        $this->assertSame(['one two three'], $out);
        $buffer->flush();
        $this->assertSame(['one two three'], $out);
    }
}
