<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Ulams\Core\Http\SafeHttp;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Connectors\Git\PathFilter;
use Ulams\LivingCourse\Connectors\GitConnector;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Events\SourceCheckFailing;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\Support\FakeGitHost;
use Ulams\LivingCourse\Tests\TestCase;

/** The Git connector over the hosts' REST APIs, against an in-memory host (ADR 0032). */
class GitConnectorTest extends TestCase
{
    private const REBASE = "# Rebase\n\nRebasing rewrites history. Use `git pull --rebase` to keep a linear history when you update your branch from the shared remote.\n";
    private const MERGE = "# Merge\n\nA merge keeps both histories and creates a merge commit that joins them. Use it when the branch history matters to your team.\n";

    protected function setUp(): void
    {
        parent::setUp();
        SafeHttp::useResolver(fn (string $host) => ['140.82.112.5']);
    }

    protected function tearDown(): void
    {
        SafeHttp::useResolver(null);
        parent::tearDown();
    }

    private function host(string $kind = 'github'): FakeGitHost
    {
        $git = new FakeGitHost($kind);
        $git->files = ['docs/rebase.md' => self::REBASE, 'docs/merge.md' => self::MERGE, 'README.md' => "# Readme\n\nNot part of the handbook.\n", 'docs/.hidden/secret.md' => "# Secret\n\nNo.\n", 'src/main.php' => '<?php echo 1;'];
        app(SourceConnectorRegistry::class)->register(new GitConnector($git->handler()));

        return $git;
    }

    /** @return array<string,mixed> */
    private function settings(string $host = 'github', array $more = []): array
    {
        $config = array_filter(['host' => $host, 'repository' => 'acme/handbook', 'branch' => 'main', 'paths' => ['docs/**/*.md'], 'base_url' => $host === 'gitea' ? 'https://git.example.org' : null]);

        return ['connector' => 'git', 'config' => array_merge($config, $more['config'] ?? []), 'secrets' => array_merge(['token' => 'ghp_verysecrettoken'], $more['secrets'] ?? []), 'schedule' => 'daily'];
    }

    private function connect($author, Session $session, array $payload)
    {
        return $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/{$session->id}/sources/connect", $payload);
    }

    public function testConnectingARepositoryCreatesTheSourceFromTheMatchingFiles(): void
    {
        $git = $this->host();
        $author = $this->author();
        $session = $this->newSession($author);

        $response = $this->connect($author, $session, $this->settings())->assertCreated();

        $data = $response->json('data');
        $this->assertSame('git', $data['connection']['connector']);
        $this->assertSame('daily', $data['connection']['schedule']);
        $this->assertSame(['token', 'webhook_secret'], $data['connection']['secretsSet']);
        $this->assertSame(43, strlen($data['webhookSecret']), '32 random bytes, base64url');
        $this->assertStringEndsWith('/api/living-course/webhooks/' . Connection::query()->findOrFail($data['connection']['id'])->webhook_id, $data['connection']['webhookUrl']);
        $this->assertStringNotContainsString('verysecrettoken', $response->getContent());
        $this->assertSame('acme/handbook', $data['source']['name']);

        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $revision = Revision::query()->findOrFail($connection->synced_revision_id);
        $this->assertSame(1, $revision->number);
        $this->assertSame('git', $revision->origin);
        $this->assertSame($git->head(), $revision->origin_ref);
        $this->assertSame(['docs/merge.md', 'docs/rebase.md'], array_column($revision->metadata['files'], 'path'), 'only the matching files, in path order');
        $this->assertSame(FakeGitHost::blobId(self::REBASE), $revision->metadata['files'][1]['blob']);

        $fragments = Fragment::query()->where('source_id', $connection->source_id)->orderBy('ordinal')->get();
        $this->assertSame(['docs/merge.md', 'docs/rebase.md'], $fragments->pluck('file_path')->unique()->values()->all());
        $this->assertSame(['docs/merge.md'], array_slice($fragments->first()->heading_path, 0, 1));
        $this->assertStringStartsWith('merge.md', $fragments->first()->label());
        $this->assertSame('ready', $session->sources()->first()->status);
        $this->assertSame('acme/handbook', $session->refresh()->title);

        // the token is encrypted at rest and never in the audit trail
        $this->assertStringNotContainsString('verysecrettoken', (string) DB::table('living_course_connections')->value('secrets'));
        foreach (AuditEntry::query()->get() as $entry) {
            $this->assertStringNotContainsString('verysecrettoken', json_encode($entry->data));
        }
        $this->assertSame(1, AuditEntry::query()->where('action', 'connection.created')->where('subject_id', $connection->id)->count());
        $this->assertSame(['Bearer ghp_verysecrettoken'], [$git->requests[0]->getHeaderLine('Authorization')]);
    }

    public function testACheckWithTheSameHeadDownloadsNothingAndAChangedHeadFetchesOnlyChangedFiles(): void
    {
        $git = $this->host();
        $author = $this->author();
        $session = $this->newSession($author);
        $this->connect($author, $session, $this->settings())->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $url = "/api/admin/living-course/connections/{$connection->id}/check";
        $blobsBefore = $git->count('~/git/blobs/~');
        $this->assertSame(2, $blobsBefore);

        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202)->assertJsonPath('data.queued', true);
        $this->assertSame($blobsBefore, $git->count('~/git/blobs/~'), 'nothing downloaded for an unchanged head');
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->count());
        $this->assertNotNull($connection->refresh()->last_checked_at);
        $this->assertSame(0, $connection->failure_count);

        $git->files['docs/rebase.md'] = str_replace('linear history', 'straight history', self::REBASE);
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);

        $this->assertSame($blobsBefore + 1, $git->count('~/git/blobs/~'), 'only the changed blob was downloaded');
        $two = Revision::query()->where('source_id', $connection->source_id)->where('number', 2)->firstOrFail();
        $this->assertSame($git->head(), $two->origin_ref);
        $this->assertSame('manual', $two->trigger);
        $this->assertSame('git', $two->origin);
        $this->assertSame(['docs/merge.md', 'docs/rebase.md'], array_column($two->metadata['files'], 'path'), 'the unchanged file is still part of the revision');
        $this->assertSame(1, $two->metadata['counts']['changed']);
        $this->assertSame(0, $two->metadata['counts']['removed'] + $two->metadata['counts']['added']);
        $this->assertSame('ingested', $two->status);
        $this->assertSame($connection->refresh()->latest_revision_id, $two->id);
    }

    public function testARenamedFileIsReportedAsMoved(): void
    {
        $git = $this->host();
        $author = $this->author();
        $session = $this->newSession($author);
        $this->connect($author, $session, $this->settings())->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();

        $git->files['docs/rewrite.md'] = $git->files['docs/rebase.md'];
        unset($git->files['docs/rebase.md']);
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/connections/{$connection->id}/check")->assertStatus(202);

        $two = Revision::query()->where('source_id', $connection->source_id)->where('number', 2)->firstOrFail();
        $this->assertSame(1, $two->metadata['counts']['moved']);
        $this->assertSame(0, $two->metadata['counts']['removed'] + $two->metadata['counts']['added'] + $two->metadata['counts']['changed']);
    }

    public function testMdxIsReducedToMarkdownAndTheFilesAreFilteredByPathExtensionAndDotDirectories(): void
    {
        $filter = new PathFilter(['docs/**/*.md', 'guide/*.mdx'], ['md', 'mdx']);
        $this->assertTrue($filter->accepts('docs/a.md'));
        $this->assertTrue($filter->accepts('docs/deep/er/a.md'));
        $this->assertTrue($filter->accepts('guide/intro.mdx'));
        $this->assertFalse($filter->accepts('guide/deep/intro.mdx'));
        $this->assertFalse($filter->accepts('docs/a.txt'));
        $this->assertFalse($filter->accepts('README.md'));
        $this->assertFalse($filter->accepts('docs/.hidden/a.md'));
        $this->assertFalse($filter->accepts('docs/node_modules/x/a.md'));
        $this->assertTrue((new PathFilter([], ['md']))->accepts('any/where/a.md'));

        $mdx = "import Tabs from '@theme/Tabs';\nexport const meta = {};\n\n# Title\n\n<Tabs>\n<TabItem value=\"a\">hidden</TabItem>\n</Tabs>\n\nReal text stays <b>here</b>.\n\n<Callout type=\"note\" />\n";
        $clean = PathFilter::stripMdx($mdx);
        $this->assertStringNotContainsString('import', $clean);
        $this->assertStringNotContainsString('Tabs', $clean);
        $this->assertStringNotContainsString('Callout', $clean);
        $this->assertStringContainsString('Real text stays', $clean);

        $git = $this->host();
        $git->files = ['guide/intro.mdx' => $mdx . str_repeat("More explanatory words about brewing coffee at home with care. ", 20)];
        $author = $this->author();
        $session = $this->newSession($author);
        $this->connect($author, $session, $this->settings('github', ['config' => ['paths' => ['guide/*.mdx'], 'extensions' => ['mdx']]]))->assertCreated();
        $text = Fragment::query()->get()->pluck('text')->implode("\n");
        $this->assertStringContainsString('Real text stays', $text);
        $this->assertStringNotContainsString('TabItem', $text);
    }

    public function testGitLabAndGiteaWorkThroughTheirOwnApis(): void
    {
        foreach (['gitlab' => 'PRIVATE-TOKEN', 'gitea' => 'Authorization'] as $kind => $header) {
            $git = $this->host($kind);
            $author = $this->author();
            $session = $this->newSession($author);
            $payload = $this->settings($kind, ['config' => $kind === 'gitlab' ? ['repository' => 'acme/docs/handbook', 'base_url' => 'https://gitlab.example.org/api/v4'] : []]);

            $this->connect($author, $session, $payload)->assertCreated();

            $revision = Revision::query()->where('number', 1)->latest('created_at')->first();
            $this->assertSame($git->head(), $revision->origin_ref, $kind);
            $this->assertSame(['docs/merge.md', 'docs/rebase.md'], array_column($revision->metadata['files'], 'path'), $kind);
            $this->assertStringContainsString('verysecrettoken', $git->requests[0]->getHeaderLine($header), $kind);
        }
    }

    public function testRefusalsAreReadableAndNeverMentionTheToken(): void
    {
        $git = $this->host();
        $author = $this->author();
        $session = $this->newSession($author);
        $cases = [
            401 => 'refused the token',
            403 => 'no access to this repository',
            404 => 'was not found',
            429 => 'rate limit',
        ];
        foreach ($cases as $status => $expected) {
            $git->failWith = [$status];
            $message = $this->connect($author, $session, $this->settings())->assertStatus(422)->json('message');
            $this->assertStringContainsString($expected, $message);
            $this->assertStringNotContainsString('verysecrettoken', $message);
        }
        $this->assertSame(0, Connection::query()->where('session_id', $session->id)->count(), 'a refused connection leaves nothing behind');

        $this->connect($author, $session, ['connector' => 'git', 'config' => ['host' => 'github', 'repository' => 'nonsense'], 'secrets' => []])->assertStatus(422);
        $this->connect($author, $session, ['connector' => 'git', 'config' => ['host' => 'gitea', 'repository' => 'a/b']])->assertStatus(422);
        $this->connect($author, $session, ['connector' => 'nope', 'config' => ['x' => 1]])->assertStatus(422);
        $this->connect($author, $session, ['connector' => 'upload', 'config' => []])->assertStatus(422);

        $git->files = ['README.md' => 'x'];
        $this->assertStringContainsString('No file in this branch matches', $this->connect($author, $session, $this->settings())->assertStatus(422)->json('message'));
    }

    public function testLimitsApplyToFilesCountAndSizes(): void
    {
        $git = $this->host();
        $author = $this->author();
        $session = $this->newSession($author);
        config(['living_course.connector_limits.files' => 1]);
        $this->assertStringContainsString('more than the limit of 1', $this->connect($author, $session, $this->settings())->assertStatus(422)->json('message'));

        config(['living_course.connector_limits.files' => 500, 'living_course.connector_limits.file_bytes' => 50]);
        $this->assertStringContainsString('larger than', $this->connect($author, $session, $this->settings())->assertStatus(422)->json('message'));

        config(['living_course.connector_limits.file_bytes' => 1048576, 'living_course.connector_limits.total_bytes' => 100]);
        $this->assertStringContainsString('add up to more than', $this->connect($author, $session, $this->settings())->assertStatus(422)->json('message'));
    }

    public function testTheHostIsCheckedAgainstTheNetworkRules(): void
    {
        $this->host('gitea');
        $author = $this->author();
        $session = $this->newSession($author);
        SafeHttp::useResolver(fn (string $host) => $host === 'intranet.example' ? ['10.0.0.5'] : ['140.82.112.5']);

        $private = $this->settings('gitea', ['config' => ['base_url' => 'https://intranet.example']]);
        $this->assertStringContainsString('private addresses', $this->connect($author, $session, $private)->assertStatus(422)->json('message'));
        $plain = $this->settings('gitea', ['config' => ['base_url' => 'http://git.example.org']]);
        $this->assertStringContainsString('must use https', $this->connect($author, $session, $plain)->assertStatus(422)->json('message'));

        config(['living_course.allowed_hosts' => ['gitlab.com']]);
        $this->assertStringContainsString('hosts your admin allowed', $this->connect($author, $session, $this->settings('gitea'))->assertStatus(422)->json('message'));
    }

    public function testThreeFailedChecksPutTheConnectionInErrorAndTellTheAuthor(): void
    {
        Event::fake([SourceCheckFailing::class]);
        $git = $this->host();
        $author = $this->author();
        $session = $this->newSession($author);
        $this->connect($author, $session, $this->settings())->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $url = "/api/admin/living-course/connections/{$connection->id}/check";
        $git->files['docs/rebase.md'] .= 'changed';

        for ($i = 1; $i <= 3; $i++) {
            $git->failWith = [500];
            $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
            $this->assertSame($i, $connection->refresh()->failure_count);
        }

        $this->assertSame('error', $connection->status);
        $this->assertStringContainsString('answered 500', (string) $connection->last_error);
        Event::assertDispatchedTimes(SourceCheckFailing::class, 1);
        $this->assertGreaterThan(now()->addHours(20), $connection->next_check_at, 'a connection in error backs off to daily');
        $this->assertSame(3, AuditEntry::query()->where('action', 'revision.failed')->where('subject_id', $connection->id)->count());

        // a successful check clears the error
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $this->assertSame('active', $connection->refresh()->status);
        $this->assertSame(0, $connection->failure_count);
        $this->assertNull($connection->last_error);
    }

    public function testCheckAndConnectAreGuardedAndUploadsHaveNothingToCheck(): void
    {
        $this->host();
        $owner = $this->author();
        $session = $this->newSession($owner);
        $this->connect($owner, $session, $this->settings())->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $check = "/api/admin/living-course/connections/{$connection->id}/check";
        $rotate = "/api/admin/living-course/connections/{$connection->id}/webhook-secret";
        $connect = "/api/admin/living-course/sessions/{$session->id}/sources/connect";

        foreach ([[$check, []], [$rotate, []], [$connect, $this->settings()]] as [$url, $body]) {
            $this->actingAs($this->tutor(), 'api')->postJson($url, $body)->assertStatus(403);
            $this->actingAs($this->student(), 'api')->postJson($url, $body)->assertStatus(403);
            $this->actingAs($this->readOnlyAdmin(), 'api')->postJson($url, $body)->assertStatus(403);
        }
        $this->app['auth']->forgetGuards();
        $this->postJson($check)->assertStatus(401);
        $this->getJson('/api/admin/living-course/connectors')->assertStatus(401);

        $old = Connection::query()->findOrFail($connection->id)->secrets['webhook_secret'];
        $new = $this->actingAs($owner, 'api')->postJson($rotate)->assertOk()->json('data.webhookSecret');
        $this->assertNotSame($old, $new);
        $this->assertSame($new, $connection->refresh()->secrets['webhook_secret']);
        $this->assertSame(1, AuditEntry::query()->where('action', 'connection.secret_rotated')->count());

        $uploaded = $this->sessionWithSource($this->author());
        $upload = $this->connectionOf($uploaded);
        $this->actingAs($uploaded->author, 'api')->postJson("/api/admin/living-course/connections/{$upload->id}/check")->assertStatus(409);
        $this->actingAs($uploaded->author, 'api')->postJson("/api/admin/living-course/connections/{$upload->id}/webhook-secret")->assertStatus(409);
        $this->actingAs($owner, 'api')->postJson('/api/admin/living-course/connections/01aaaaaaaaaaaaaaaaaaaaaaaa/check')->assertStatus(404);

        $connectors = $this->actingAs($owner, 'api')->getJson('/api/admin/living-course/connectors')->assertOk()->json('data');
        $this->assertSame(['upload', 'git', 'url'], array_column($connectors, 'key'));
        $this->assertSame(['token'], collect($connectors)->firstWhere('key', 'git')['secretFields']);
        config(['living_course.connectors' => ['upload']]);
        $this->assertSame(['upload'], array_column($this->actingAs($owner, 'api')->getJson('/api/admin/living-course/connectors')->json('data'), 'key'));
        $this->connect($owner, $this->newSession($owner), $this->settings())->assertStatus(422);
    }
}
