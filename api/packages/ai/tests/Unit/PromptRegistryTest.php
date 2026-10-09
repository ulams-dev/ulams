<?php

namespace Ulams\Ai\Tests\Unit;

use Illuminate\Support\Str;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\Ai\Tests\TestCase;

class PromptRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/ulams-prompts-' . Str::random(8);
        mkdir($this->dir . '/outline', 0775, true);
        file_put_contents($this->dir . '/outline/v1.md', "---\nid: test/outline\nversion: 1\ntask: outline\nchangelog: first\n---\nOld text.\n");
        file_put_contents($this->dir . '/outline/v2.md', "---\nid: test/outline\nversion: 2\ntask: outline\nschema: outline/v1.json\n---\nNew text.\n");
        file_put_contents($this->dir . '/outline/v3.txt', 'ignored');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function testLatestVersionByDefaultAndPinnedVersionOnRequest(): void
    {
        $registry = new PromptRegistry();
        $registry->addPath('test', $this->dir);

        $latest = $registry->get('test', 'outline');
        $this->assertSame(2, $latest->version);
        $this->assertSame('New text.', $latest->text);
        $this->assertSame('outline/v1.json', $latest->meta['schema']);

        $this->assertSame('Old text.', $registry->get('test', 'outline', 1)->text);
        config(['ai.prompt_pins' => ['test/outline' => 1]]);
        $this->assertSame(1, $this->pinned());
    }

    private function pinned(): int
    {
        $registry = new PromptRegistry();
        $registry->addPath('test', $this->dir);

        return $registry->get('test', 'outline')->version;
    }

    public function testFrontMatterVersionMustMatchTheFileName(): void
    {
        file_put_contents($this->dir . '/outline/v4.md', "---\nversion: 3\n---\nWrong.\n");
        $registry = new PromptRegistry();
        $registry->addPath('test', $this->dir);
        $this->expectException(\InvalidArgumentException::class);
        $registry->get('test', 'outline');
    }
}
