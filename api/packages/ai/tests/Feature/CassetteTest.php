<?php

namespace Ulams\Ai\Tests\Feature;

use Illuminate\Support\Str;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Drivers\FakeDriver;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Fake\CassetteStore;
use Ulams\Ai\Fake\FakeResponders;
use Ulams\Ai\Prompts\Prompt;
use Ulams\Ai\Tests\TestCase;

class CassetteTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/ulams-cassettes-' . Str::random(8);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function driverRequest(string $content, int $version = 1, array $schema = ['type' => 'object']): DriverRequest
    {
        return new DriverRequest('lesson', 'cb/lesson', $version, 'default', 'm', 'medium', 1000, 'sys', [ContentBlock::text($content)], $schema);
    }

    public function testNormalisationReplacesGeneratedIdsInOrder(): void
    {
        $ulid = strtolower((string) Str::ulid());
        [$text, $map] = CassetteStore::normalise("frg_abcdefghijkl and {$ulid} then frg_abcdefghijkl and frg_zzzzzzzzzzzz");

        $this->assertSame('{{frg_1}} and {{id_1}} then {{frg_1}} and {{frg_2}}', $text);
        $this->assertSame(['frg_1' => 'frg_abcdefghijkl', 'id_1' => $ulid, 'frg_2' => 'frg_zzzzzzzzzzzz'], $map);
        $this->assertSame("frg_abcdefghijkl and {$ulid} then frg_abcdefghijkl and frg_zzzzzzzzzzzz", CassetteStore::denormalise($text, $map));
    }

    public function testRecordingReplaysAgainstNewIds(): void
    {
        $store = new CassetteStore($this->dir);
        $recorded = $this->driverRequest('<fragment id="frg_aaaaaaaaaaaa">x</fragment> lesson 01J00000000000000000000001');
        $store->save($recorded, new DriverResponse('{"citations":["frg_aaaaaaaaaaaa"],"lesson":"01J00000000000000000000001"}', 'model-x', 'end_turn', new Usage(5, 6)));

        $stored = json_decode((string) file_get_contents($store->path($recorded)), true);
        $this->assertSame('{"citations":["{{frg_1}}"],"lesson":"{{id_1}}"}', $stored['text']);

        // the same flow on a fresh database: other ids, same shape
        $replay = $store->load($this->driverRequest('<fragment id="frg_bbbbbbbbbbbb">x</fragment> lesson 01J99999999999999999999999'));
        $this->assertNotNull($replay);
        $this->assertSame('{"citations":["frg_bbbbbbbbbbbb"],"lesson":"01J99999999999999999999999"}', $replay->text);
        $this->assertSame('model-x', $replay->model);
        $this->assertSame(6, $replay->usage->outputTokens);
    }

    public function testPromptVersionOrSchemaChangeNeedsNewCassettes(): void
    {
        $store = new CassetteStore($this->dir);
        $store->save($this->driverRequest('same'), new DriverResponse('{}', 'm', 'end_turn', new Usage()));

        $this->assertNotNull($store->load($this->driverRequest('same')));
        $this->assertNull($store->load($this->driverRequest('same', 2)));
        $this->assertNull($store->load($this->driverRequest('same', 1, ['type' => 'object', 'required' => ['a']])));
        $this->assertNull($store->load($this->driverRequest('different')));
    }

    public function testSchemaKeyOrderDoesNotChangeTheHash(): void
    {
        $store = new CassetteStore($this->dir);
        $a = $this->driverRequest('x', 1, ['type' => 'object', 'required' => ['a']]);
        $b = $this->driverRequest('x', 1, ['required' => ['a'], 'type' => 'object']);
        $this->assertSame($store->key($a)['hash'], $store->key($b)['hash']);
    }

    public function testSyntheticModeUsesARegisteredResponderOnlyWhenNoCassette(): void
    {
        $responders = new FakeResponders();
        $responders->register('lesson', fn (DriverRequest $r) => ['echo' => $r->task]);
        $cassetteOnly = new FakeDriver(new CassetteStore($this->dir), $responders, 'cassette');
        $synthetic = new FakeDriver(new CassetteStore($this->dir), $responders, 'synthetic');

        $this->assertSame('{"echo":"lesson"}', $synthetic->send($this->driverRequest('a'))->text);
        $this->expectException(\Ulams\Ai\Exceptions\DriverException::class);
        $cassetteOnly->send($this->driverRequest('a'));
    }

    public function testClientRecordsCassettesWhenRecordingIsOn(): void
    {
        config(['ai.record.enabled' => true, 'ai.record.path' => $this->dir]);
        $this->app->forgetInstance(LlmClient::class);
        $this->fake()->queue('outline', new DriverResponse('{"ok":true}', 'm', 'end_turn', new Usage(1, 1), 'msg_live'));

        $request = new LlmRequest('outline', Prompt::inline('t/outline', 1, 'sys'), [ContentBlock::text('frg_cccccccccccc')], ['type' => 'object']);
        $this->app->make(LlmClient::class)->generate($request);

        $files = glob($this->dir . '/outline/v1/*.json');
        $this->assertCount(1, $files);
        $this->assertSame('{"ok":true}', json_decode((string) file_get_contents($files[0]), true)['text']);
    }
}
