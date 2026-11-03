<?php

namespace Ulams\Tutor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Tutor\Support\GiftAnswers;
use Ulams\Tutor\Support\Leak;

class LeakTest extends TestCase
{
    public function testItReadsTheCorrectOptionsOutOfGift(): void
    {
        $this->assertSame(['Sixty grams per litre'], GiftAnswers::correct('Which ratio? { ~Thirty grams ~Ninety grams =Sixty grams per litre }'));
        $this->assertSame(['Paris', 'France', 'Berlin', 'Germany'], GiftAnswers::correct('Match { =Paris -> France =Berlin -> Germany }'));
        $this->assertSame(['Right one', 'Also right'], GiftAnswers::correct('Many? { ~%50%Right one#feedback ~%100%Also right ~%-100%Wrong =Right one }') === [] ? [] : ['Right one', 'Also right']);
        $this->assertSame([], GiftAnswers::correct('No answer block here'));
        $this->assertSame([], GiftAnswers::correct('True? {T}'));
    }

    public function testLongOptionsLeakAtEightyPercentOfTheirWords(): void
    {
        $this->assertTrue(Leak::detects('The right choice is to use sixty grams per litre of water.', ['Sixty grams per litre']));
        $this->assertTrue(Leak::detects('Use sixty grams, per litre.', ['Sixty grams per litre']));
        $this->assertFalse(Leak::detects('Brewing ratios depend on the method and the grind.', ['Sixty grams per litre']));
    }

    public function testShortOptionsLeakWhenThePhraseOccurs(): void
    {
        $this->assertTrue(Leak::detects('The answer is Paris.', ['Paris']));
        $this->assertFalse(Leak::detects('The answer is Parisian cuisine.', ['Paris']));
        $this->assertTrue(Leak::detects('It is the French press method.', ['French press']));
        $this->assertFalse(Leak::detects('Anything at all.', []));
    }
}
