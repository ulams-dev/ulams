<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Support\Facades\Route;
use Ulams\Core\Http\Sse\EventStream;
use Ulams\Core\Tests\TestCase;

/** The SSE reader loop shared by the Course Builder and the tutor (ADR 0105). */
class EventStreamTest extends TestCase
{
    /** @var array<int,object> */
    private array $rows = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->rows = array_map(fn ($i) => (object) ['id' => $i * 10, 'payload' => ['type' => $i === 3 ? 'RUN_FINISHED' : 'TEXT', 'n' => $i, 'text' => 'é']], [1, 2, 3, 4]);
        Route::get('/sse-test', fn (\Illuminate\Http\Request $r) => EventStream::response(
            fn (int $after) => array_values(array_filter($this->rows, fn ($row) => $row->id > $after)),
            null,
            (int) ($r->header('Last-Event-ID') ?: 0),
            (int) $r->query('max', 0),
            100,
            $r->query('preamble') ? ['type' => 'HELLO'] : null,
            $r->query('last') ? fn ($row) => $row->payload['type'] === 'RUN_FINISHED' : null,
            (int) $r->query('ping', 10),
        ));
    }

    /** @return array<int,array{id:?int,data:?array}> */
    private function read(string $body): array
    {
        $events = [];
        foreach (preg_split("/\n\n/", trim($body)) as $chunk) {
            $id = null;
            $data = null;
            foreach (explode("\n", $chunk) as $line) {
                if (str_starts_with($line, 'id: ')) {
                    $id = (int) substr($line, 4);
                } elseif (str_starts_with($line, 'data: ')) {
                    $data = json_decode(substr($line, 6), true);
                }
            }
            if ($data !== null) {
                $events[] = ['id' => $id, 'data' => $data];
            }
        }

        return $events;
    }

    public function test_it_sends_the_headers_that_keep_proxies_from_buffering(): void
    {
        $response = $this->get('/sse-test');
        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith("retry: 1000\n\n", $response->streamedContent());
    }

    public function test_it_sends_the_preamble_without_an_id_then_every_row_with_its_id(): void
    {
        $events = $this->read($this->get('/sse-test?preamble=1')->streamedContent());

        $this->assertSame('HELLO', $events[0]['data']['type']);
        $this->assertNull($events[0]['id']);
        $this->assertSame([10, 20, 30, 40], array_column(array_slice($events, 1), 'id'));
        $this->assertSame('é', $events[1]['data']['text']);
    }

    public function test_it_resumes_after_last_event_id(): void
    {
        $events = $this->read($this->withHeaders(['Last-Event-ID' => '20'])->get('/sse-test')->streamedContent());

        $this->assertSame([30, 40], array_column($events, 'id'));
    }

    public function test_it_ends_after_the_row_the_caller_calls_final(): void
    {
        $events = $this->read($this->get('/sse-test?last=1&max=5')->streamedContent());

        $this->assertSame([10, 20, 30], array_column($events, 'id'));
    }

    public function test_it_closes_after_the_time_cap_and_pings_while_idle(): void
    {
        $this->rows = [];
        $started = microtime(true);
        $body = $this->get('/sse-test?max=1&ping=0')->streamedContent();

        $this->assertLessThan(3, microtime(true) - $started);
        $this->assertStringContainsString(': ping', $body);
    }
}
