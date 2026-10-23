<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Services\ChangeClassifier;
use Ulams\PolicyLifecycle\Tests\TestCase;

class ChangeClassifierTest extends TestCase
{
    public function testNoClassIsTheSuggestionAndRaisingIsFree(): void
    {
        $c = new ChangeClassifier();

        $this->assertSame(['class' => 'major', 'reason' => null], $c->resolve('major', null, null));
        $this->assertSame(['class' => 'major', 'reason' => null], $c->resolve('minor', 'major', null));
        $this->assertSame(['class' => 'minor', 'reason' => null], $c->resolve('minor', 'minor', ''));
    }

    public function testLoweringNeedsARecordedReason(): void
    {
        $c = new ChangeClassifier();

        $this->assertSame(['class' => 'minor', 'reason' => 'Typos only'], $c->resolve('major', 'minor', ' Typos only '));
        foreach ([null, '', '   '] as $reason) {
            try {
                $c->resolve('major', 'minor', $reason);
                $this->fail('A lower class without a reason must be refused.');
            } catch (PolicyException $e) {
                $this->assertSame(422, $e->status);
            }
        }
        $this->expectException(PolicyException::class);
        $c->resolve('minor', 'huge', 'x');
    }
}
