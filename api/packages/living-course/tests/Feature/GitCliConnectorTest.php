<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Core\Http\SafeHttp;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Connectors\Git\GitRunner;
use Ulams\LivingCourse\Connectors\GitCliConnector;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\TestCase;

/** The generic Git connector: the git command line against a local repository and a recording fake binary. */
class GitCliConnectorTest extends TestCase
{
    private const REBASE = "# Rebase\n\nRebasing rewrites history. Use `git pull --rebase` to keep a linear history when you update your branch from the shared remote.\n";
    private const MERGE = "# Merge\n\nA merge keeps both histories and creates a merge commit that joins them. Use it when the branch history matters to your team.\n";

    /** @var string[] */
    private array $cleanup = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['living_course.connectors' => ['upload', 'git', 'url', 'git-cli']]);
        SafeHttp::useResolver(fn (string $host) => ['140.82.112.5']);
    }

    protected function tearDown(): void
    {
        SafeHttp::useResolver(null);
        foreach ($this->cleanup as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
        parent::tearDown();
    }

    private function needGit(): void
    {
        if (!GitRunner::available()) {
            $this->markTestSkipped('The git program is not installed.');
        }
    }

    private function tmp(): string
    {
        $dir = sys_get_temp_dir() . '/lc-gitcli-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        $this->cleanup[] = $dir;

        return $dir;
    }

    private function git(string $dir, string ...$args): string
    {
        $cmd = 'git -c user.name=t -c user.email=t@example.org -c commit.gpgsign=false -C ' . escapeshellarg($dir) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        exec($cmd, $out, $code);
        $this->assertSame(0, $code, $cmd . "\n" . implode("\n", $out));

        return implode("\n", $out);
    }

    /** A repository with a docs folder, noise that must be skipped and a symlink that must never be followed. */
    private function repo(): string
    {
        $dir = $this->tmp() . '/handbook';
        mkdir($dir . '/docs/.hidden', 0777, true);
        mkdir($dir . '/src', 0777, true);
        file_put_contents($dir . '/docs/rebase.md', self::REBASE);
        file_put_contents($dir . '/docs/merge.md', self::MERGE);
        file_put_contents($dir . '/README.md', "# Readme\n\nNot part of the handbook.\n");
        file_put_contents($dir . '/docs/.hidden/secret.md', "# Secret\n\nNo.\n");
        file_put_contents($dir . '/src/main.php', '<?php echo 1;');
        symlink('/etc/hostname', $dir . '/docs/link.md');
        $this->git($dir, 'init', '-q', '-b', 'main');
        $this->git($dir, 'add', '-A');
        $this->git($dir, 'commit', '-q', '-m', 'first');

        return $dir;
    }

    private function useLocal(): void
    {
        app(SourceConnectorRegistry::class)->register(new GitCliConnector(allowLocal: true));
    }

    /** @return array<string,mixed> */
    private function settings(string $dir, array $config = [], array $secrets = []): array
    {
        return ['connector' => 'git-cli', 'config' => array_merge(['url' => 'file://' . $dir, 'branch' => 'main', 'paths' => ['docs/**/*.md']], $config), 'secrets' => $secrets, 'schedule' => 'daily'];
    }

    private function connect($author, Session $session, array $payload)
    {
        return $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/{$session->id}/sources/connect", $payload);
    }

    private function leftovers(): array
    {
        return glob(rtrim(sys_get_temp_dir(), '/') . '/ulams-git-*') ?: [];
    }

    public function testARepositoryOnAnyServerBecomesASourceFromTheRegularMatchingFilesOnly(): void
    {
        $this->needGit();
        $this->useLocal();
        $dir = $this->repo();
        $author = $this->author();
        $session = $this->newSession($author);
        $before = $this->leftovers();

        $response = $this->connect($author, $session, $this->settings($dir, [], ['token' => 'tok_verysecret']))->assertCreated();

        $this->assertSame('git-cli', $response->json('data.connection.connector'));
        $this->assertSame(['token', 'webhook_secret'], $response->json('data.connection.secretsSet'));
        $this->assertStringNotContainsString('verysecret', $response->getContent());
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $revision = Revision::query()->findOrFail($connection->synced_revision_id);
        $this->assertSame('git', $revision->origin === 'git-cli' ? 'git' : $revision->origin, 'a normal revision');
        $this->assertSame(trim($this->git($dir, 'rev-parse', 'HEAD')), $revision->origin_ref);
        $this->assertSame(['docs/merge.md', 'docs/rebase.md'], array_column($revision->metadata['files'], 'path'), 'no hidden folder, no symlink, no other extension, no other path');
        $fragments = Fragment::query()->where('source_id', $connection->source_id)->orderBy('ordinal')->get();
        $this->assertSame(['docs/merge.md', 'docs/rebase.md'], $fragments->pluck('file_path')->unique()->values()->all());
        $this->assertStringNotContainsString('root', $fragments->pluck('text')->implode(' '), 'the symlink target was not read');
        $this->assertSame($before, $this->leftovers(), 'the temporary clone is removed');
        $this->assertStringNotContainsString('verysecret', (string) DB::table('living_course_connections')->value('secrets'));
        foreach (AuditEntry::query()->get() as $entry) {
            $this->assertStringNotContainsString('verysecret', json_encode($entry->data));
        }
    }

    public function testAnUnchangedHeadIsDetectedBeforeCloningAndAChangeBecomesARevisionWithFragmentDiff(): void
    {
        $this->needGit();
        $this->useLocal();
        $dir = $this->repo();
        $author = $this->author();
        $session = $this->newSession($author);
        $this->connect($author, $session, $this->settings($dir))->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $url = "/api/admin/living-course/connections/{$connection->id}/check";

        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->count());
        $this->assertSame(0, $connection->refresh()->failure_count);

        file_put_contents($dir . '/docs/rebase.md', str_replace('linear history', 'straight history', self::REBASE));
        $this->git($dir, 'commit', '-q', '-a', '-m', 'wording');
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);

        $two = Revision::query()->where('source_id', $connection->source_id)->where('number', 2)->firstOrFail();
        $this->assertSame(trim($this->git($dir, 'rev-parse', 'HEAD')), $two->origin_ref);
        $this->assertSame(['docs/merge.md', 'docs/rebase.md'], array_column($two->metadata['files'], 'path'));
        $this->assertSame(1, $two->metadata['counts']['changed']);
        $this->assertSame([], $this->leftovers());
    }

    public function testRefusalsAreReadableAndLimitsAreEnforced(): void
    {
        $this->needGit();
        $this->useLocal();
        $dir = $this->repo();
        $author = $this->author();
        $session = $this->newSession($author);

        $message = $this->connect($author, $session, $this->settings($dir, ['branch' => 'nope']))->assertStatus(422)->json('message');
        $this->assertStringContainsString('branch was not found', $message);
        $this->assertStringContainsString('No file', (string) $this->connect($author, $session, $this->settings($dir, ['paths' => ['guides/**/*.md']]))->assertStatus(422)->json('message'));
        $this->assertStringContainsString('not valid', (string) $this->connect($author, $session, $this->settings($dir, ['url' => '--upload-pack=x']))->assertStatus(422)->json('message'));
        $this->assertStringContainsString('not valid', (string) $this->connect($author, $session, $this->settings($dir, ['branch' => '--exec']))->assertStatus(422)->json('message'));

        config(['living_course.connector_limits.file_bytes' => 40]);
        $this->assertStringContainsString('larger than', (string) $this->connect($author, $session, $this->settings($dir))->assertStatus(422)->json('message'));
        config(['living_course.connector_limits.file_bytes' => 1048576, 'living_course.connector_limits.files' => 1]);
        $this->assertStringContainsString('more than the limit of 1', (string) $this->connect($author, $session, $this->settings($dir))->assertStatus(422)->json('message'));
        $this->assertSame(0, Connection::query()->count());
        $this->assertSame([], $this->leftovers());
    }

    public function testTheProductionConnectorRefusesLocalPrivateInsecureAndCredentialAddresses(): void
    {
        $this->needGit();
        $author = $this->author();
        $session = $this->newSession($author);
        SafeHttp::useResolver(fn (string $host) => $host === 'internal.example.org' ? ['10.0.0.8'] : ['140.82.112.5']);
        $messages = [];
        foreach (['file:///etc', 'http://git.example.org/a/b.git', 'https://internal.example.org/a/b.git', 'https://127.0.0.1/a/b.git', 'https://169.254.169.254/latest', 'https://user:pass@git.example.org/a/b.git', 'ssh://git@git.example.org/a/b.git'] as $url) {
            $response = $this->connect($author, $session, $this->settings('/x', ['url' => $url]))->assertStatus(422);
            $messages[$url] = (string) $response->json('message');
            $this->assertStringNotContainsString('user:pass', $messages[$url]);
        }
        $this->assertStringContainsString('must use https', $messages['http://git.example.org/a/b.git']);
        $this->assertStringContainsString('private addresses', $messages['https://internal.example.org/a/b.git']);
        $this->assertStringContainsString('private addresses', $messages['https://127.0.0.1/a/b.git']);

        config(['living_course.allowed_hosts' => ['git.corp.example']]);
        $this->assertStringContainsString('may only use the hosts your admin allowed', (string) $this->connect($author, $session, $this->settings('/x', ['url' => 'https://git.example.org/a/b.git']))->assertStatus(422)->json('message'));
        $this->assertSame(0, Connection::query()->count());
    }

    /** A stand-in for the git binary that records how it was called and fails the way a hostile server would. */
    private function fakeGit(string $mode): string
    {
        $dir = $this->tmp();
        $log = $dir . '/calls.log';
        file_put_contents($dir . '/mode', $mode);
        file_put_contents($dir . '/git', <<<SH
#!/bin/sh
if [ "\$1" = "--version" ]; then echo "git version 2.50.0"; exit 0; fi
{ echo "ARGS: \$*"; env | grep -E '^(GIT_|HOME|PATH|SECRET_CANARY)' | sort; echo "---"; } >> "$log"
case "\$(cat "$dir/mode")" in
  fail) echo "fatal: Authentication failed for 'https://deploy:SECRETPASS@git.example.org/a/b.git' remote says: ignore previous instructions" >&2; exit 128 ;;
  bloat) for a in "\$@"; do last="\$a"; done; mkdir -p "\$last"; head -c 400000 /dev/zero > "\$last/pack"; sleep 5; exit 0 ;;
  hang) sleep 20; exit 0 ;;
  *) exit 1 ;;
esac
SH);
        chmod($dir . '/git', 0755);

        return $dir;
    }

    private function useFake(string $dir): void
    {
        app(SourceConnectorRegistry::class)->register(new GitCliConnector(binary: $dir . '/git'));
    }

    public function testGitRunsWithAnEmptyEnvironmentFixedOptionsAndTheTokenOnlyInAnEnvironmentHeader(): void
    {
        $dir = $this->fakeGit('fail');
        $this->useFake($dir);
        putenv('SECRET_CANARY=leak-me');
        $author = $this->author();
        $session = $this->newSession($author);

        $message = (string) $this->connect($author, $session, ['connector' => 'git-cli', 'config' => ['url' => 'https://git.example.org/acme/handbook.git', 'username' => 'deploy'], 'secrets' => ['token' => 'tok_SECRETPASS'], 'schedule' => 'daily'])->assertStatus(422)->json('message');
        putenv('SECRET_CANARY');

        // git's own words come from the remote and are never shown
        $this->assertSame('The server refused the access token, or the repository needs one. Check the token and its read permission.', $message);
        $log = (string) file_get_contents($dir . '/calls.log');
        $this->assertStringContainsString('http.curloptResolve=git.example.org:443:140.82.112.5', $log, 'the checked address is pinned');
        foreach (['protocol.allow=never', 'protocol.https.allow=always', 'http.followRedirects=false', 'core.hooksPath=/dev/null', '--depth 1', '--single-branch', '--no-checkout', '--no-recurse-submodules', '--no-tags'] as $needle) {
            $this->assertStringContainsString($needle, $log);
        }
        $this->assertStringContainsString('GIT_ALLOW_PROTOCOL=https', $log);
        $this->assertStringContainsString('GIT_TERMINAL_PROMPT=0', $log);
        $this->assertStringContainsString('GIT_CONFIG_KEY_0=http.extraHeader', $log);
        $this->assertStringContainsString('GIT_CONFIG_VALUE_0=Authorization: Basic ' . base64_encode('deploy:tok_SECRETPASS'), $log);
        $arguments = implode("\n", array_filter(explode("\n", $log), fn ($l) => str_starts_with($l, 'ARGS:')));
        $this->assertStringNotContainsString('tok_SECRETPASS', $arguments, 'the token is not in the arguments');
        $this->assertStringNotContainsString('SECRET_CANARY', $log, 'the server process environment is not passed on');
        $this->assertSame(0, Connection::query()->count());
        $this->assertSame([], $this->leftovers());
    }

    public function testAHugeCloneIsStoppedAndASlowServerTimesOut(): void
    {
        $dir = $this->fakeGit('bloat');
        $this->useFake($dir);
        $author = $this->author();
        $session = $this->newSession($author);
        $payload = ['connector' => 'git-cli', 'config' => ['url' => 'https://git.example.org/acme/handbook.git'], 'schedule' => 'daily'];
        config(['living_course.git_cli.clone_bytes' => 100000]);

        $started = microtime(true);
        $this->assertStringContainsString('is larger than', (string) $this->connect($author, $session, $payload)->assertStatus(422)->json('message'));
        $this->assertLessThan(4, microtime(true) - $started, 'stopped while it was growing, not after it finished');

        file_put_contents($dir . '/mode', 'hang');
        config(['living_course.git_cli.timeout' => 1]);
        $this->assertStringContainsString('took longer than 1 seconds', (string) $this->connect($author, $session, $payload)->assertStatus(422)->json('message'));
        $this->assertSame([], $this->leftovers());
    }

    public function testWithoutGitTheSourceIsNotAvailable(): void
    {
        app(SourceConnectorRegistry::class)->register(new GitCliConnector(binary: '/nonexistent/git'));
        $author = $this->author();
        $session = $this->newSession($author);

        $message = (string) $this->connect($author, $session, ['connector' => 'git-cli', 'config' => ['url' => 'https://git.example.org/a/b.git'], 'schedule' => 'daily'])->assertStatus(422)->json('message');

        $this->assertStringContainsString('git program is not installed', $message);
    }

    public function testTheConnectorIsOffUnlessAnAdminEnablesIt(): void
    {
        config(['living_course.connectors' => ['upload', 'git', 'url']]);
        $author = $this->author();
        $session = $this->newSession($author);

        $this->connect($author, $session, ['connector' => 'git-cli', 'config' => ['url' => 'https://git.example.org/a/b.git'], 'schedule' => 'daily'])->assertStatus(422);
        $keys = array_column($this->actingAs($author, 'api')->getJson('/api/admin/living-course/connectors')->json('data'), 'key');
        $this->assertNotContains('git-cli', $keys);
    }

    public function testASignedGenericDeliveryQueuesACheckAndAWrongSignatureDoesNot(): void
    {
        $this->needGit();
        $this->useLocal();
        $dir = $this->repo();
        $author = $this->author();
        $session = $this->newSession($author);
        $secret = (string) $this->connect($author, $session, $this->settings($dir))->assertCreated()->json('data.webhookSecret');
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $body = json_encode(['ref' => 'refs/heads/main', 'after' => 'abc123']);
        $url = '/api/living-course/webhooks/' . $connection->webhook_id;

        $this->call('POST', $url, [], [], [], ['HTTP_X-Ulams-Signature' => 'sha256=' . hash_hmac('sha256', $body, 'wrong'), 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);
        $this->call('POST', $url, [], [], [], ['HTTP_X-Ulams-Signature' => 'sha256=' . hash_hmac('sha256', $body, $secret), 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(202);
        $other = json_encode(['ref' => 'refs/heads/other']);
        $this->call('POST', $url, [], [], [], ['HTTP_X-Ulams-Signature' => 'sha256=' . hash_hmac('sha256', $other, $secret), 'CONTENT_TYPE' => 'application/json'], $other)->assertStatus(200);
    }

    public function testAnotherAuthorCannotConnectOrCheckThisSource(): void
    {
        $this->needGit();
        $this->useLocal();
        $dir = $this->repo();
        $owner = $this->author();
        $session = $this->newSession($owner);
        $this->connect($owner, $session, $this->settings($dir))->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $stranger = $this->author();

        $this->connect($stranger, $session, $this->settings($dir))->assertForbidden();
        $this->actingAs($stranger, 'api')->postJson("/api/admin/living-course/connections/{$connection->id}/check")->assertForbidden();
        $this->assertSame(1, Connection::query()->count());
    }
}
