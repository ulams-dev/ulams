<?php

namespace Tests\Integrations;

use Database\Seeders\Demo\Support\ModuleFile;
use Database\Seeders\Demo\Support\Sources;
use Tests\TestCase;

/**
 * The format of a module file of an interactive demo course and the citations of its text (ADR 0094).
 */
class DemoModuleFileTest extends TestCase
{
    public function testModuleFileSplitsMetaAndBlocks(): void
    {
        $text = "---\ntitle: One\nduration: 5 min\n---\n\n::: interactive title=\"Two words\" start=a end=b preview=1\nBody {{src:X}}\n:::\n\n::: quiz title=Q pass=70\n::A:: Q? {T}\n\n// a comment\n::B:: Q? {F}\n:::\n";
        $parsed = ModuleFile::parse($text);

        $this->assertSame(['title' => 'One', 'duration' => '5 min'], $parsed['meta']);
        $this->assertSame('interactive', $parsed['blocks'][0]['kind']);
        $this->assertSame(['title' => 'Two words', 'start' => 'a', 'end' => 'b', 'preview' => '1'], $parsed['blocks'][0]['attrs']);
        $this->assertSame('Body {{src:X}}', $parsed['blocks'][0]['body']);
        $this->assertCount(2, ModuleFile::giftQuestions($parsed['blocks'][1]['body']));
    }

    public function testModuleFileRefusesLooseTextAndUnknownBlocks(): void
    {
        $this->expectException(\RuntimeException::class);
        ModuleFile::parse("---\ntitle: T\n---\nloose text\n");
    }

    public function testSourcesNumberCitationsAndShareNumbersForTheSamePage(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sources');
        file_put_contents($file, json_encode([
            'A' => ['title' => 'Fact sheet', 'publisher' => 'NASA', 'url' => 'https://nasa.example/a'],
            'B' => ['s' => [['Annual report', 'https://stat.example/b']], 'p' => '2025'],
            'C' => ['s' => [['Annual report', 'https://stat.example/b']], 'p' => '2025'],
        ]));
        $sources = new Sources($file);

        $text = $sources->cite('One {{src:B}} two {{src:A,C}}.');

        $this->assertStringContainsString('One [1] two [1, 2].', $text, 'B and C name the same page and share a number');
        $this->assertStringContainsString("## Sources\n\n1. Annual report, <https://stat.example/b> (2025)\n2. Fact sheet (NASA), <https://nasa.example/a>", $text);
        $this->assertStringContainsString('*Sources: [1] Annual report; [2] Fact sheet (NASA)*', $sources->cite('{{src:B,A}}', true));
        $this->assertSame('No citations.', $sources->cite('No citations.'));
        unlink($file);

        $this->expectException(\RuntimeException::class);
        $sources->cited('{{src:Z}}');
    }
}
