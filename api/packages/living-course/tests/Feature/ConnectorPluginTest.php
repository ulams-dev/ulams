<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Ulams\ExamplePlugin\Connectors\ExampleConnector;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\TestCase;

/** The plugin path of ADR 0032: a connector from another package, end to end. */
class ConnectorPluginTest extends TestCase
{
    private const PAGE_ONE = "# Brewing notes\n\nUse a ratio of 1:16 for filter coffee, a medium grind and water just off the boil for every cup you make at home.\n";

    protected function setUp(): void
    {
        parent::setUp();
        app(SourceConnectorRegistry::class)->register(new ExampleConnector());
        config(['living_course.connectors' => ['upload', 'git', 'url', 'example']]);
    }

    public function testAPluginConnectorIsOfferedConnectedCheckedAndDiffed(): void
    {
        $author = $this->author();
        $keys = array_column($this->actingAs($author, 'api')->getJson('/api/admin/living-course/connectors')->assertOk()->json('data'), 'key');
        $this->assertContains('example', $keys);

        $session = $this->newSession($author);
        $response = $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/{$session->id}/sources/connect", [
            'connector' => 'example', 'config' => ['pages' => [['path' => 'notes/brewing.md', 'text' => self::PAGE_ONE]]], 'schedule' => 'manual',
        ])->assertCreated();
        $this->assertNull($response->json('data.webhookSecret'));
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $this->assertSame('example', $connection->connector);
        $this->assertSame('example', Revision::query()->findOrFail($connection->synced_revision_id)->origin);

        // nothing changed: no revision
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/connections/{$connection->id}/check")->assertStatus(202);
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->count());

        // the "remote" changes
        $connection->forceFill(['config' => ['pages' => [['path' => 'notes/brewing.md', 'text' => str_replace('1:16', '1:15', self::PAGE_ONE)]]]])->save();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/connections/{$connection->id}/check")->assertStatus(202);
        $two = Revision::query()->where('source_id', $connection->source_id)->where('number', 2)->firstOrFail();
        $this->assertSame(1, $two->metadata['counts']['changed']);
        $this->assertSame(1, $two->metadata['counts']['substantive']);
    }

    public function testAConnectorThatIsNotEnabledCannotBeUsed(): void
    {
        config(['living_course.connectors' => ['upload', 'git', 'url']]);
        $author = $this->author();
        $session = $this->newSession($author);

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/{$session->id}/sources/connect", ['connector' => 'example', 'config' => ['pages' => [['path' => 'a.md', 'text' => 'x']]]])->assertStatus(422);
    }

    public function testBadSettingsAreExplainedByTheConnector(): void
    {
        $author = $this->author();
        $session = $this->newSession($author);

        $message = $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/{$session->id}/sources/connect", ['connector' => 'example', 'config' => ['pages' => []]])->assertStatus(422)->json('message');

        $this->assertSame('Add at least one page.', $message);
    }
}
