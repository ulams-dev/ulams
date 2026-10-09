<?php

namespace Ulams\LivingCourse\Tests\Feature;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Ulams\Core\Http\SafeHttp;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\LivingCourse\Connectors\Url\HtmlConverter;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Connectors\UrlConnector;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\TestCase;

/** The URL connector: web pages of one host, HTML to Markdown, conditional requests (plan 6.5). */
class UrlConnectorTest extends TestCase
{
    /** @var array<string,array{status?:int,type?:string,body:string,etag?:?string,location?:string}> */
    private array $pages = [];

    /** @var RequestInterface[] */
    private array $requests = [];

    private const PAGE = <<<'HTML'
<!doctype html>
<html><head><title>CLI reference | Docs</title><script>window.evil = 1</script></head>
<body>
<nav><a href="/">Home</a><a href="/blog">Blog</a></nav>
<main>
  <h1>CLI reference</h1>
  <p>Run <code>docs build</code> to render the site. See the <a href="/guide/install">install guide</a> and <a href="javascript:alert(1)">this</a> or <a href="#top">top</a>.</p>
  <img src="https://tracker.example/pixel.png" alt="pixel">
  <h2>Options</h2>
  <table><tr><th>Flag</th><th>Meaning</th></tr><tr><td><code>--strict</code></td><td>Fail on warnings</td></tr></table>
  <pre><code>docs build --strict
</code></pre>
  <form action="/subscribe"><input name="email"><button>Subscribe</button></form>
  <script>fetch('/steal')</script>
</main>
<footer>Copyright</footer>
</body></html>
HTML;

    protected function setUp(): void
    {
        parent::setUp();
        SafeHttp::useResolver(fn (string $host) => ['93.184.216.34']);
        $this->pages = ['https://docs.example.dev/cli' => ['body' => self::PAGE, 'type' => 'text/html; charset=utf-8', 'etag' => '"v1"']];
        $handler = function (RequestInterface $request, array $options) {
            $this->requests[] = $request;
            $page = $this->pages[(string) $request->getUri()] ?? null;
            if ($page === null) {
                return Create::promiseFor(new Response(404));
            }
            if (isset($page['location'])) {
                return Create::promiseFor(new Response(302, ['Location' => $page['location']]));
            }
            if (isset($page['etag']) && $request->getHeaderLine('If-None-Match') === $page['etag']) {
                return Create::promiseFor(new Response(304, ['ETag' => $page['etag']]));
            }

            return Create::promiseFor(new Response($page['status'] ?? 200, ['Content-Type' => $page['type'] ?? 'text/html'] + (($page['etag'] ?? null) ? ['ETag' => $page['etag']] : []), $page['body']));
        };
        app(SourceConnectorRegistry::class)->register(new UrlConnector($handler));
    }

    protected function tearDown(): void
    {
        SafeHttp::useResolver(null);
        parent::tearDown();
    }

    private function connect(array $urls = ['https://docs.example.dev/cli'], array $more = [])
    {
        $author = $this->author();
        $session = $this->newSession($author);
        $response = $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/{$session->id}/sources/connect", ['connector' => 'url', 'config' => ['urls' => $urls] + $more, 'schedule' => 'weekly']);

        return [$author, $session, $response];
    }

    public function testHtmlIsReducedToTheMainContentAsMarkdown(): void
    {
        $out = (new HtmlConverter())->convert(self::PAGE, 'https://docs.example.dev/cli');

        $md = $out['markdown'];
        $this->assertSame('CLI reference', $out['title']);
        $this->assertStringStartsWith('# CLI reference', $md);
        $this->assertStringContainsString('Run `docs build` to render the site.', $md);
        $this->assertStringContainsString('[install guide](https://docs.example.dev/guide/install)', $md, 'links are absolute');
        $this->assertStringContainsString('## Options', $md);
        $this->assertStringContainsString('`--strict`', $md);
        $this->assertStringContainsString("docs build --strict", $md);
        foreach (['Home', 'Blog', 'Copyright', 'Subscribe', 'pixel', 'tracker.example', 'window.evil', 'steal', 'javascript:'] as $dropped) {
            $this->assertStringNotContainsString($dropped, $md, $dropped);
        }
        $this->assertStringNotContainsString('<', $md);

        $custom = (new HtmlConverter())->convert('<body><div id="a"><p>Wanted text for the course.</p></div><div id="b"><p>Other.</p></div></body>', 'https://x.example/p', '#a');
        $this->assertStringContainsString('Wanted text', $custom['markdown']);
        $this->assertStringNotContainsString('Other', $custom['markdown']);
        $this->expectException(\Ulams\LivingCourse\Connectors\ConnectorException::class);
        (new HtmlConverter())->convert('<p>x</p>', 'https://x.example/p', '[[[');
    }

    public function testConnectingBuildsOneFilePerPageAndChecksAreConditional(): void
    {
        $this->pages['https://docs.example.dev/start'] = ['body' => '<main><h1>Start</h1><p>Begin with the installation of the tool and then read the reference of the commands.</p></main>', 'etag' => null];
        [$author, $session, $response] = $this->connect(['https://docs.example.dev/cli', 'https://docs.example.dev/start'], ['selector' => 'main']);

        $response->assertCreated();
        $this->assertNull($response->json('data.webhookSecret'));
        $this->assertNull($response->json('data.connection.config.token'));
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $this->assertSame('weekly', $connection->schedule);
        $one = Revision::query()->findOrFail($connection->synced_revision_id);
        $this->assertSame('url', $one->origin);
        $this->assertSame(['/cli', '/start'], array_column($one->metadata['files'], 'path'));
        $this->assertSame(['/cli', 'Options'], array_slice(Fragment::query()->where('file_path', '/cli')->orderBy('ordinal')->get()->last()->heading_path, 0, 2));
        $this->assertSame('https://docs.example.dev/cli', $response->json('data.source.name'));

        // the second check sends If-None-Match for the page that had an ETag and reads the stored copy on 304
        $this->requests = [];
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/connections/{$connection->id}/check")->assertStatus(202);
        $byUrl = collect($this->requests)->keyBy(fn ($r) => (string) $r->getUri());
        $this->assertSame('"v1"', $byUrl['https://docs.example.dev/cli']->getHeaderLine('If-None-Match'));
        $this->assertSame('', $byUrl['https://docs.example.dev/start']->getHeaderLine('If-None-Match'));
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->count(), 'nothing changed');
        $this->assertNotNull($connection->refresh()->last_checked_at);
        $this->assertTrue($connection->next_check_at->between(now()->addMinutes(10080), now()->addMinutes(10080 + 1100)));
    }

    public function testAChangedPageCreatesARevisionAndAnUnchangedBodyDoesNot(): void
    {
        [$author, $session] = $this->connect();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $url = "/api/admin/living-course/connections/{$connection->id}/check";

        // the server stops sending ETags but the body is the same: still unchanged
        $this->pages['https://docs.example.dev/cli']['etag'] = null;
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->count());

        $this->pages['https://docs.example.dev/cli']['body'] = str_replace('Fail on warnings', 'Fail on any warning or error', self::PAGE);
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);

        $two = Revision::query()->where('source_id', $connection->source_id)->where('number', 2)->firstOrFail();
        $this->assertSame(1, $two->metadata['counts']['changed'], json_encode($two->metadata['counts']));
        $this->assertSame('url', $two->origin);
        $this->assertStringContainsString('Fail on any warning', \Ulams\LivingCourse\Models\RevisionFragment::query()->where('revision_id', $two->id)->pluck('text')->implode(' '));
    }

    public function testOnlyWebPagesAndTextFilesOfOneHostAreAccepted(): void
    {
        $this->pages['https://docs.example.dev/file.pdf'] = ['body' => '%PDF', 'type' => 'application/pdf'];
        $this->pages['https://docs.example.dev/notes.txt'] = ['body' => "Plain notes about the tool that readers should know before they start using it every day.\n", 'type' => 'text/plain'];
        $this->pages['https://docs.example.dev/secret'] = ['body' => 'no', 'status' => 403];
        $this->pages['https://docs.example.dev/huge'] = ['body' => str_repeat('x', 100), 'type' => 'text/plain'];
        $this->pages['https://docs.example.dev/moved'] = ['body' => '', 'location' => 'https://other.example.dev/x'];

        $this->assertStringContainsString('not a web page', $this->connect(['https://docs.example.dev/file.pdf'])[2]->assertStatus(422)->json('message'));
        $this->assertStringContainsString('needs a login', $this->connect(['https://docs.example.dev/secret'])[2]->assertStatus(422)->json('message'));
        $this->assertStringContainsString('was not found', $this->connect(['https://docs.example.dev/missing'])[2]->assertStatus(422)->json('message'));
        $this->assertStringContainsString('same site', $this->connect(['https://docs.example.dev/cli', 'https://other.example.dev/cli'])[2]->assertStatus(422)->json('message'));
        $this->assertStringContainsString('not valid', $this->connect(['http://docs.example.dev/cli'])[2]->assertStatus(422)->json('message'));
        $this->assertStringContainsString('another host', $this->connect(['https://docs.example.dev/moved'])[2]->assertStatus(422)->json('message'));
        config(['living_course.connector_limits.page_bytes' => 50]);
        $this->assertStringContainsString('larger than', $this->connect(['https://docs.example.dev/huge'])[2]->assertStatus(422)->json('message'));
        config(['living_course.connector_limits.page_bytes' => 5242880]);

        // a plain text file is Markdown already
        $this->connect(['https://docs.example.dev/notes.txt'])[2]->assertCreated();
        $this->assertStringContainsString('Plain notes', Fragment::query()->get()->pluck('text')->implode(' '));
    }

    public function testPrivateHostsAreRefused(): void
    {
        SafeHttp::useResolver(fn (string $host) => ['10.0.0.9']);

        $this->assertStringContainsString('private addresses', $this->connect()[2]->assertStatus(422)->json('message'));
        SafeHttp::useResolver(fn (string $host) => ['93.184.216.34']);
        config(['living_course.allowed_hosts' => ['docs.other.dev']]);
        $this->assertStringContainsString('hosts your admin allowed', $this->connect()[2]->assertStatus(422)->json('message'));
    }
}
