<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use Ulams\Core\Http\SafeHttp;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Connectors\Google\GoogleDriveConnector;
use Ulams\LivingCourse\Connectors\Notion\NotionConnector;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Oauth\OAuthService;
use Ulams\LivingCourse\Tests\Support\FakeVendor;
use Ulams\LivingCourse\Tests\TestCase;

/** Google Drive and Notion connectors and their "Connect your account" sign-in, against scripted vendors (no network). */
class DriveNotionConnectorTest extends TestCase
{
    private const DOC = 'doc1234567890';

    private const BREW = "# Brewing guide\n\n## Ratio\n\nThe brew ratio is the weight of coffee compared to the weight of water. A ratio of 1:16 means one gram of coffee for every sixteen grams of water.\n\n## Temperature\n\nWater between 90 and 96 degrees extracts coffee well. Water just off the boil suits light roasts.\n";

    private FakeVendor $vendor;

    /** @var string[] */
    private array $versions = ['12'];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'living_course.connectors' => ['upload', 'git', 'url', 'google-drive', 'notion'],
            'living_course.oauth' => ['return_url' => null, 'google' => ['client_id' => 'gid', 'client_secret' => 'gsecret'], 'notion' => ['client_id' => 'nid', 'client_secret' => 'nsecret']],
        ]);
        SafeHttp::useResolver(fn (string $host) => ['142.250.1.1']);
        $this->vendor = new FakeVendor();
        $oauth = new OAuthService($this->vendor->handler());
        $this->app->instance(OAuthService::class, $oauth);
        $registry = app(SourceConnectorRegistry::class);
        $registry->register(new GoogleDriveConnector($this->vendor->handler(), $oauth));
        $registry->register(new NotionConnector($this->vendor->handler()));
        $this->googleTokens();
    }

    protected function tearDown(): void
    {
        SafeHttp::useResolver(null);
        parent::tearDown();
    }

    /* ------------------------------------------------------------------ vendors */

    private function googleTokens(): void
    {
        $this->vendor->on('POST', '#oauth2\.googleapis\.com/token#', function (RequestInterface $r) {
            parse_str((string) $r->getBody(), $form);
            $json = fn (array $d, int $s = 200) => new \GuzzleHttp\Psr7\Response($s, ['Content-Type' => 'application/json'], (string) json_encode($d));
            if (($form['grant_type'] ?? '') === 'authorization_code') {
                return ($form['code'] ?? '') === 'good-code' && ($form['client_secret'] ?? '') === 'gsecret' ? $json(['access_token' => 'at_first', 'refresh_token' => 'rt_good', 'expires_in' => 3600]) : $json(['error' => 'invalid_grant', 'error_description' => 'Bad code from hostile server'], 400);
            }

            return ($form['refresh_token'] ?? '') === 'rt_good' ? $json(['access_token' => 'at_' . substr(md5((string) microtime(true)), 0, 6), 'expires_in' => 3600]) : $json(['error' => 'invalid_grant'], 400);
        });
    }

    private function doc(string $version = '12', string $text = self::BREW, string $name = 'Brewing guide'): void
    {
        $this->vendor->on('GET', '#/files/' . self::DOC . '\?#', ['id' => self::DOC, 'name' => $name, 'mimeType' => 'application/vnd.google-apps.document', 'version' => $version, 'modifiedTime' => '2026-10-01T10:00:0' . $version[0] . 'Z']);
        $this->vendor->on('GET', '#/files/' . self::DOC . '/export\?mimeType=text/markdown#', $text);
    }

    private function sessionAndGrant($author, string $provider = 'google'): array
    {
        $session = $this->newSession($author);
        $start = $this->actingAs($author, 'api')->postJson("/api/admin/living-course/oauth/{$provider}/start", ['sessionId' => $session->id])->assertOk()->json('data.url');
        parse_str((string) parse_url($start, PHP_URL_QUERY), $query);
        $code = $provider === 'google' ? 'good-code' : 'notion-code';
        $grant = $this->getJson("/api/living-course/oauth/{$provider}/callback?code={$code}&state=" . urlencode($query['state']))->assertOk()->json('data.grant');

        return [$session, $grant, $start];
    }

    private function connect($author, Session $session, array $payload)
    {
        return $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/{$session->id}/sources/connect", $payload);
    }

    private function driveSettings(string $grant, string $file = 'https://docs.google.com/document/d/' . self::DOC . '/edit'): array
    {
        return ['connector' => 'google-drive', 'config' => ['fileId' => $file], 'secrets' => ['grant' => $grant], 'schedule' => 'daily'];
    }

    /* ------------------------------------------------------------------ sign-in */

    public function testTheSignInStartsAtTheVendorWithAnEncryptedStateAndNeedsAnOwnedSession(): void
    {
        $author = $this->author();
        $session = $this->newSession($author);

        $url = $this->actingAs($author, 'api')->postJson("/api/admin/living-course/oauth/google/start", ['sessionId' => $session->id])->assertOk()->json('data.url');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('gid', $q['client_id']);
        $this->assertSame('https://www.googleapis.com/auth/drive.readonly', $q['scope']);
        $this->assertSame('offline', $q['access_type']);
        $this->assertStringEndsWith('/api/living-course/oauth/google/callback', $q['redirect_uri']);
        $this->assertStringNotContainsString('gsecret', $url);
        $this->assertStringNotContainsString((string) $author->getKey(), $q['state'], 'the state is opaque');

        $this->actingAs($this->author(), 'api')->postJson('/api/admin/living-course/oauth/google/start', ['sessionId' => $session->id])->assertForbidden();
        $this->actingAs($author, 'api')->postJson('/api/admin/living-course/oauth/dropbox/start', ['sessionId' => $session->id])->assertStatus(422);

        config(['living_course.oauth.google.client_secret' => '']);
        $this->assertStringContainsString('not set up', (string) $this->actingAs($author, 'api')->postJson('/api/admin/living-course/oauth/google/start', ['sessionId' => $session->id])->assertStatus(422)->json('message'));
    }

    public function testTheCallbackKeepsTokensOnTheServerAndOnlyHandsOutAOneTimeGrant(): void
    {
        $author = $this->author();
        [$session, $grant] = $this->sessionAndGrant($author);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $grant);
        $this->assertStringNotContainsString('rt_good', $grant);
        $exchange = $this->vendor->matching('#oauth2\.googleapis\.com/token#')[0];
        parse_str((string) $exchange->getBody(), $form);
        $this->assertSame('authorization_code', $form['grant_type']);
        $this->assertStringEndsWith('/api/living-course/oauth/google/callback', $form['redirect_uri']);

        // a forged, foreign or refused callback never produces a grant, and never repeats what the vendor said
        $this->getJson('/api/living-course/oauth/google/callback?code=good-code&state=forged')->assertStatus(422);
        $this->getJson('/api/living-course/oauth/google/callback?error=access_denied&state=x')->assertStatus(422);
        $start = $this->actingAs($author, 'api')->postJson('/api/admin/living-course/oauth/google/start', ['sessionId' => $session->id])->json('data.url');
        parse_str((string) parse_url($start, PHP_URL_QUERY), $q);
        $response = $this->getJson('/api/living-course/oauth/google/callback?code=wrong&state=' . urlencode($q['state']))->assertStatus(422);
        $this->assertStringNotContainsString('hostile', $response->getContent());
        $this->getJson('/api/living-course/oauth/notion/callback?code=notion-code&state=' . urlencode($q['state']))->assertStatus(422);

        config(['living_course.oauth.return_url' => 'https://studio.example.org/connect']);
        $this->travel(11)->minutes();
        $this->getJson('/api/living-course/oauth/google/callback?code=good-code&state=' . urlencode($q['state']))->assertRedirectContains('oauth_error=');
        $this->travelBack();
        $this->getJson('/api/living-course/oauth/google/callback?code=good-code&state=' . urlencode($q['state']))->assertRedirectContains('https://studio.example.org/connect?provider=google&grant=');
    }

    /* ------------------------------------------------------------------ drive */

    public function testAGoogleDocBecomesASourceThroughTheSignInAndTheGrantWorksOnce(): void
    {
        $this->doc();
        $author = $this->author();
        [$session, $grant] = $this->sessionAndGrant($author);

        $response = $this->connect($author, $session, $this->driveSettings($grant))->assertCreated();

        $this->assertSame('google-drive', $response->json('data.connection.connector'));
        $this->assertSame(['refresh_token'], $response->json('data.connection.secretsSet'));
        $this->assertNull($response->json('data.webhookSecret'));
        $this->assertStringNotContainsString('rt_good', $response->getContent());
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $this->assertSame('rt_good', ((array) $connection->secrets)['refresh_token']);
        $this->assertStringNotContainsString('rt_good', (string) DB::table('living_course_connections')->value('secrets'), 'encrypted at rest');
        $revision = Revision::query()->findOrFail($connection->synced_revision_id);
        $this->assertSame('google-drive', $revision->origin);
        $this->assertSame(['Brewing guide.md'], array_column($revision->metadata['files'], 'path'));
        $this->assertSame('12|2026-10-01T10:00:01Z', $revision->metadata['files'][0]['blob']);
        $this->assertStringContainsString('brew ratio', Fragment::query()->where('source_id', $connection->source_id)->get()->pluck('text')->implode(' '));
        $this->assertSame('Brewing guide', $session->refresh()->title);

        // every API call carries a fresh access token from the refresh token, the export asks for Markdown
        $calls = $this->vendor->matching('#googleapis\.com/drive/v3#');
        $this->assertNotEmpty($calls);
        foreach ($calls as $call) {
            $this->assertMatchesRegularExpression('/^Bearer at_/', $call->getHeaderLine('Authorization'));
        }
        $this->assertSame(1, $this->vendor->count('#/export\?mimeType=text/markdown#'));
        foreach (AuditEntry::query()->get() as $entry) {
            $this->assertStringNotContainsString('rt_good', json_encode($entry->data));
        }

        // a grant redeems once, and only for the user it was made for
        $second = $this->newSession($author);
        $this->connect($author, $second, $this->driveSettings($grant))->assertStatus(422);
        [, $other] = $this->sessionAndGrant($author);
        $stranger = $this->author();
        $mine = $this->newSession($stranger);
        $this->connect($stranger, $mine, $this->driveSettings($other))->assertStatus(422);
        $this->assertSame(1, Connection::query()->count());
    }

    public function testAnUnchangedFileCostsOnlyAMetadataRequestAndANewVersionBecomesARevision(): void
    {
        $this->doc();
        $author = $this->author();
        [$session, $grant] = $this->sessionAndGrant($author);
        $this->connect($author, $session, $this->driveSettings($grant))->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $url = "/api/admin/living-course/connections/{$connection->id}/check";
        $exports = $this->vendor->count('#/export#');

        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $this->assertSame($exports, $this->vendor->count('#/export#'), 'nothing exported for an unchanged version');
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->count());

        $this->doc('13', str_replace('1:16', '1:15', self::BREW));
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);

        $two = Revision::query()->where('source_id', $connection->source_id)->where('number', 2)->firstOrFail();
        $this->assertSame($exports + 1, $this->vendor->count('#/export#'));
        $this->assertSame(1, $two->metadata['counts']['changed']);
        $this->assertSame('manual', $two->trigger);
    }

    public function testAFolderListsSupportedFilesOnlyNamesThemSafelyAndStopsAtItsLimits(): void
    {
        $this->doc();
        $this->vendor->on('GET', '#/files/folder12345678\?#', ['id' => 'folder12345678', 'name' => 'Handbook', 'mimeType' => 'application/vnd.google-apps.folder']);
        $listing = [
            ['id' => self::DOC, 'name' => 'Brewing guide', 'mimeType' => 'application/vnd.google-apps.document', 'version' => '12', 'modifiedTime' => '2026-10-01T10:00:01Z'],
            ['id' => 'notes12345678', 'name' => '../../etc/Notes.md', 'mimeType' => 'text/markdown', 'version' => '3', 'modifiedTime' => '2026-10-02T10:00:00Z', 'size' => '40'],
            ['id' => 'sheet12345678', 'name' => 'Budget', 'mimeType' => 'application/vnd.google-apps.spreadsheet', 'version' => '1', 'modifiedTime' => '2026-10-02T10:00:00Z'],
            ['id' => 'dupe123456789', 'name' => 'Brewing guide', 'mimeType' => 'text/plain', 'version' => '1', 'modifiedTime' => '2026-10-03T10:00:00Z', 'size' => '40'],
            ['id' => 'trash12345678', 'name' => 'Old', 'mimeType' => 'text/plain', 'version' => '1', 'modifiedTime' => '2026-10-03T10:00:00Z', 'size' => '40', 'trashed' => true],
        ];
        $this->vendor->on('GET', "#/files\?.*'folder12345678'.in.parents#", ['files' => $listing]);
        $this->vendor->on('GET', '#/files/notes12345678\?alt=media#', "# Notes\n\nKeep the grinder clean and dry between brews, because old oils turn rancid and spoil the taste of the next cup.\n");
        $this->vendor->on('GET', '#/files/dupe123456789\?alt=media#', "# Duplicate\n\nAnother file with the same name must not replace the first one in the revision, so it gets a short id in its name instead.\n");
        $author = $this->author();
        [$session, $grant] = $this->sessionAndGrant($author);

        $created = $this->connect($author, $session, $this->driveSettings($grant, 'folder12345678'));
        $this->assertSame(201, $created->status(), $created->getContent() . ' ' . implode(' | ', $this->vendor->missed));

        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $paths = array_column(Revision::query()->findOrFail($connection->synced_revision_id)->metadata['files'], 'path');
        $this->assertSame(['.. .. etc Notes.md', 'Brewing guide-doc123.md', 'Brewing guide-dupe12.md'], $paths, 'no slashes, no spreadsheet, no trashed file, files with one name told apart by their id');

        // limits
        config(['living_course.connector_limits.files' => 2]);
        $this->assertStringContainsString('more than the limit of 2', (string) $this->connect($author, $this->newSession($author), $this->driveSettings($this->sessionAndGrant($author)[1], 'folder12345678'))->assertStatus(422)->json('message'));
    }

    public function testDriveRefusalsAreReadableAndNeverRepeatTheVendor(): void
    {
        $author = $this->author();
        [$session, $grant] = $this->sessionAndGrant($author);
        $message = fn () => (string) $this->connect($author, $session, $this->driveSettings($grant))->assertStatus(422)->json('message');

        $this->assertStringContainsString('not found', $message());
        $this->vendor->on('GET', '#/files/' . self::DOC . '\?#', ['error' => ['message' => 'hostile text with Bearer at_secret']], 403);
        $this->assertStringContainsString('may not read this file', $message());
        $this->assertStringNotContainsString('hostile', $message());
        $this->vendor->on('GET', '#/files/' . self::DOC . '\?#', '', 503);
        $this->assertStringContainsString('not available right now', $message());

        $this->assertStringContainsString('Paste the address', (string) $this->connect($author, $session, $this->driveSettings($grant, 'x'))->assertStatus(422)->json('message'));
        $this->assertStringContainsString('Connect your Google account first', (string) $this->connect($author, $session, ['connector' => 'google-drive', 'config' => ['fileId' => self::DOC], 'schedule' => 'daily'])->assertStatus(422)->json('message'));

        config(['living_course.allowed_hosts' => ['api.notion.com']]);
        $this->assertStringContainsString('may only use the hosts your admin allowed', $message());
        $this->assertSame(0, Connection::query()->count());
    }

    public function testARevokedRefreshTokenFailsTheCheckWithAReconnectMessage(): void
    {
        $this->doc();
        $author = $this->author();
        [$session, $grant] = $this->sessionAndGrant($author);
        $this->connect($author, $session, $this->driveSettings($grant))->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $this->vendor->on('POST', '#oauth2\.googleapis\.com/token#', ['error' => 'invalid_grant'], 400);

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/connections/{$connection->id}/check")->assertStatus(202);

        $connection->refresh();
        $this->assertSame(1, $connection->failure_count);
        $this->assertStringContainsString('Connect your Google account again', (string) $connection->last_error);

        // reconnecting with a fresh sign-in replaces the secret without a new source
        $this->googleTokens();
        $start = $this->actingAs($author, 'api')->postJson('/api/admin/living-course/oauth/google/start', ['sessionId' => $session->id])->json('data.url');
        parse_str((string) parse_url($start, PHP_URL_QUERY), $q);
        $newGrant = $this->getJson('/api/living-course/oauth/google/callback?code=good-code&state=' . urlencode($q['state']))->json('data.grant');
        $this->actingAs($author, 'api')->putJson("/api/admin/living-course/connections/{$connection->id}", ['secrets' => ['grant' => $newGrant]])->assertOk();
        $this->assertSame(['refresh_token'], array_keys((array) $connection->refresh()->secrets));
        $this->assertSame(1, Connection::query()->count());
    }

    /* ------------------------------------------------------------------ notion */

    private function notionPage(string $id, string $title, string $edited, array $extra = []): void
    {
        $this->vendor->on('GET', '#api\.notion\.com/v1/pages/' . $id . '$#', ['object' => 'page', 'id' => $id, 'last_edited_time' => $edited, 'properties' => ['Name' => ['type' => 'title', 'title' => [['plain_text' => $title, 'annotations' => []]]]]] + $extra);
    }

    private function notionBlocks(string $id, array $blocks): void
    {
        $this->vendor->on('GET', '#api\.notion\.com/v1/blocks/' . $id . '/children#', ['results' => $blocks, 'has_more' => false]);
    }

    private function text(string $type, string $text, array $extra = []): array
    {
        return ['id' => bin2hex(random_bytes(16)), 'type' => $type, 'has_children' => false, $type => ['rich_text' => [['plain_text' => $text, 'annotations' => []]]] + $extra];
    }

    private function notionSettings(string $page, array $more = []): array
    {
        return ['connector' => 'notion', 'config' => ['pageId' => $page] + $more, 'secrets' => ['token' => 'secret_internal_token'], 'schedule' => 'daily'];
    }

    public function testANotionPageBecomesAMarkdownSourceAndIsCheckedWithOneRequest(): void
    {
        $page = str_repeat('a1', 16);
        $this->notionPage($page, 'Coffee handbook', '2026-10-01T08:00:00.000Z');
        $this->notionBlocks($page, [
            $this->text('heading_2', 'Ratio'),
            $this->text('paragraph', 'The brew ratio is the weight of coffee compared to the weight of water, and a ratio of one to sixteen is a good start.'),
            ['id' => str_repeat('b2', 16), 'type' => 'bulleted_list_item', 'has_children' => true, 'bulleted_list_item' => ['rich_text' => [['plain_text' => 'Grind', 'annotations' => []]]]],
            ['id' => str_repeat('c3', 16), 'type' => 'child_page', 'has_children' => true, 'child_page' => ['title' => 'Nested']],
        ]);
        $this->notionBlocks(str_repeat('b2', 16), [$this->text('bulleted_list_item', 'Medium coarse for filter')]);
        $author = $this->author();
        $session = $this->newSession($author);

        $response = $this->connect($author, $session, $this->notionSettings('https://www.notion.so/Coffee-handbook-' . $page . '?pvs=4'))->assertCreated();

        $this->assertSame(['token'], $response->json('data.connection.secretsSet'));
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $revision = Revision::query()->findOrFail($connection->synced_revision_id);
        $this->assertSame('notion', $revision->origin);
        $this->assertSame(['Coffee handbook.md'], array_column($revision->metadata['files'], 'path'));
        $text = Fragment::query()->where('source_id', $connection->source_id)->orderBy('ordinal')->get()->pluck('text')->implode("\n");
        $this->assertStringContainsString('brew ratio', $text);
        $this->assertStringContainsString('Medium coarse for filter', $text);
        $this->assertStringNotContainsString('Nested', $text, 'child pages are only read when asked for');
        $this->assertSame('Bearer secret_internal_token', $this->vendor->requests[0]->getHeaderLine('Authorization'));
        $this->assertSame('2022-06-28', $this->vendor->requests[0]->getHeaderLine('Notion-Version'));

        $url = "/api/admin/living-course/connections/{$connection->id}/check";
        $blocks = $this->vendor->count('#/blocks/#');
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $this->assertSame($blocks, $this->vendor->count('#/blocks/#'), 'the content is not read for an unchanged page');
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->count());

        $this->notionPage($page, 'Coffee handbook', '2026-10-02T08:00:00.000Z');
        $this->notionBlocks($page, [$this->text('heading_2', 'Ratio'), $this->text('paragraph', 'The brew ratio is the weight of coffee compared to the weight of water, and a ratio of one to fifteen is a good start.')]);
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->where('number', 2)->firstOrFail()->metadata['counts']['changed']);
    }

    public function testNestedPagesAreReadOnRequestAndAChangeInAChildIsFound(): void
    {
        $root = str_repeat('a1', 16);
        $child = str_repeat('c3', 16);
        $this->notionPage($root, 'Handbook', '2026-10-01T08:00:00.000Z');
        $this->notionPage($child, 'Storage', '2026-10-01T09:00:00.000Z');
        $this->notionBlocks($root, [$this->text('paragraph', 'Start here: the handbook explains how to brew a good cup of coffee at home with simple tools.'), ['id' => $child, 'type' => 'child_page', 'has_children' => true, 'child_page' => ['title' => 'Storage']]]);
        $this->notionBlocks($child, [$this->text('paragraph', 'Keep beans in an airtight container away from light, heat and moisture, and use them within four weeks.')]);
        $author = $this->author();
        $session = $this->newSession($author);
        $this->connect($author, $session, $this->notionSettings($root, ['includeChildren' => true]))->assertCreated();
        $connection = Connection::query()->where('session_id', $session->id)->firstOrFail();
        $revision = Revision::query()->findOrFail($connection->synced_revision_id);
        $this->assertSame(['Handbook.md', 'Storage.md'], array_column($revision->metadata['files'], 'path'));

        $url = "/api/admin/living-course/connections/{$connection->id}/check";
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $this->assertSame(1, Revision::query()->where('source_id', $connection->source_id)->count(), 'nothing changed');

        $this->notionPage($child, 'Storage', '2026-10-05T09:00:00.000Z');
        $this->notionBlocks($child, [$this->text('paragraph', 'Keep beans in an airtight container away from light, heat and moisture, and use them within two weeks.')]);
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(202);
        $two = Revision::query()->where('source_id', $connection->source_id)->where('number', 2)->firstOrFail();
        $this->assertSame(1, $two->metadata['counts']['changed']);

        config(['living_course.connector_limits.files' => 1]);
        $this->assertStringContainsString('more than', (string) $this->connect($author, $this->newSession($author), $this->notionSettings($root, ['includeChildren' => true]))->assertStatus(422)->json('message'));
    }

    public function testNotionRefusalsAreReadableAndTheSignInGivesALongLivedToken(): void
    {
        $page = str_repeat('a1', 16);
        $author = $this->author();
        $session = $this->newSession($author);

        $this->assertStringContainsString('Share it with the connected Notion integration', (string) $this->connect($author, $session, $this->notionSettings($page))->assertStatus(422)->json('message'));
        $this->notionPage($page, 'Gone', '2026-10-01T08:00:00.000Z', ['archived' => true]);
        $this->assertStringContainsString('in the trash', (string) $this->connect($author, $session, $this->notionSettings($page))->assertStatus(422)->json('message'));
        $this->assertStringContainsString('Paste the address of a Notion page', (string) $this->connect($author, $session, $this->notionSettings(str_repeat('z', 40)))->assertStatus(422)->json('message'));
        $this->assertStringContainsString('Connect your Notion account first', (string) $this->connect($author, $session, ['connector' => 'notion', 'config' => ['pageId' => $page], 'schedule' => 'daily'])->assertStatus(422)->json('message'));

        // the Notion sign-in trades the code with HTTP basic auth and keeps the access token as the secret
        $this->vendor->on('POST', '#api\.notion\.com/v1/oauth/token#', function (RequestInterface $r) {
            $ok = $r->getHeaderLine('Authorization') === 'Basic ' . base64_encode('nid:nsecret') && (json_decode((string) $r->getBody(), true)['code'] ?? '') === 'notion-code';

            return new \GuzzleHttp\Psr7\Response($ok ? 200 : 401, ['Content-Type' => 'application/json'], (string) json_encode($ok ? ['access_token' => 'ntn_long_lived', 'workspace_name' => 'Acme'] : ['error' => 'x']));
        });
        $this->notionPage($page, 'Coffee', '2026-10-01T08:00:00.000Z');
        $this->notionBlocks($page, [$this->text('paragraph', 'The brew ratio is the weight of coffee compared to the weight of water, and a ratio of one to sixteen is a good start.')]);
        [$fresh, $grant] = $this->sessionAndGrant($author, 'notion');
        $this->connect($author, $fresh, ['connector' => 'notion', 'config' => ['pageId' => $page], 'secrets' => ['grant' => $grant], 'schedule' => 'daily'])->assertCreated();
        $this->assertSame('ntn_long_lived', ((array) Connection::query()->where('session_id', $fresh->id)->firstOrFail()->secrets)['token']);
    }

    public function testTheConnectorListShowsWhetherTheSignInIsSetUpAndNeitherConnectorIsOnByDefault(): void
    {
        $author = $this->author();
        $list = collect($this->actingAs($author, 'api')->getJson('/api/admin/living-course/connectors')->json('data'))->keyBy('key');
        $this->assertSame(['provider' => 'google', 'configured' => true], $list['google-drive']['oauth']);
        $this->assertSame(['provider' => 'notion', 'configured' => true], $list['notion']['oauth']);
        $this->assertArrayNotHasKey('oauth', $list['git']);

        config(['living_course.oauth.notion.client_id' => '']);
        $list = collect($this->actingAs($author, 'api')->getJson('/api/admin/living-course/connectors')->json('data'))->keyBy('key');
        $this->assertFalse($list['notion']['oauth']['configured']);

        $this->assertSame(['upload', 'git', 'url'], (array) (require __DIR__ . '/../../config/living_course.php')['connectors'], 'Drive, Notion and git-cli are opt-in');
    }
}
