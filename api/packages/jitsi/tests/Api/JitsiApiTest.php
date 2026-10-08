<?php

namespace Ulams\Jitsi\Tests\Api;

use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Jitsi\Services\FileService;
use Ulams\Jitsi\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JitsiApiTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, DatabaseTransactions, WithFaker;

    private const TOKEN = 'jitsi-webhook-token';
    private const SECRET = 'jaas-webhook-secret';

    private array $body;
    private string $url;

    private function postWebhook(array $body, ?string $token = self::TOKEN)
    {
        return $this->withHeaders($token ? ['Authorization' => 'Bearer ' . $token] : [])
            ->json('POST', '/api/jitsi/recorded-video', $body);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('jitsi.webhook_token', self::TOKEN);
        Config::set('jitsi.webhook_secret', self::SECRET);
        Config::set('jitsi.recording_hosts', ['localhost']);

        $recordingSessionId = Str::uuid()->toString();

        $this->url = "https://localhost/test-app-id/{$recordingSessionId}/nagrywaniewideo_consultations_11_1728385200_2024-10-08-11-35-05.mp4";

        $this->body = [
            "eventType" => "RECORDING_UPLOADED",
            "sessionId" => Str::uuid()->toString(),
            "timestamp" => 1728387319418,
            "fqn" => "test-app-id/nagrywaniewideo_consultations_11_1728385200",
            "idempotencyKey" => Str::uuid()->toString(),
            "customerId" => "66d15721481e4cbbac651b1658dff55c",
            "appId" => "test-app-id",
            "data" => [
                "participants" => [
                    [
                        "name" => "admin.ulams",
                        "id" => "auth0|66dffe0e58e320bf6575969c"
                    ]
                ],
                "share" => true,
                "initiatorId" => "auth0|66dffe0e58e320bf6575969c",
                "durationSec" => 2,
                "startTimestamp" => 1728387309767,
                "endTimestamp" => 1728387311997,
                "recordingSessionId" => $recordingSessionId,
                "preAuthenticatedLink" => $this->url,
            ]
        ];
    }

    public function testSaveRecordedVideoInvalidAppId(): void
    {
        Config::set('jitsi.app_id', 'test-app-id');

        $this->body['appId'] = 'wrong-app-id';

        $this->postWebhook($this->body)
            ->assertUnprocessable();
    }

    public function testSaveRecordedVideo(): void
    {
        Config::set('jitsi.app_id', 'test-app-id');

        $this->mockGetFileContent('nagrywaniewideo_consultations_11_1728385200_2024-10-08-11-35-05.mp4');

        $this->postWebhook($this->body)->assertOk();

        Storage::assertExists('consultations/11/1728385200/1728387309767.mp4');
    }

    public function testSaveRecordedVideoNoSuffix(): void
    {
        Config::set('jitsi.app_id', 'test-app-id');

        $this->body['fqn'] = "test-app-id/nagrywaniewideo";

        $this->mockGetFileContent('nagrywaniewideo_2024-10-08-11-35-05.mp4');

        $this->postWebhook($this->body)->assertOk();

        Storage::assertExists('jitsi/videos/nagrywaniewideo/1728387309767.mp4');
    }

    public function testSaveRecordedVideoDifferentSuffix(): void
    {
        Config::set('jitsi.app_id', 'test-app-id');

        $this->body['fqn'] = "test-app-id/nagrywaniewideo_webinar_20";

        $this->mockGetFileContent('nagrywaniewideo_webinar_20_2024-10-08-11-35-05.mp4');

        $this->postWebhook($this->body)->assertOk();

        Storage::assertExists('webinar/20/1728387309767.mp4');
    }

    public function testRecordedVideoWebhookRequiresAuthentication(): void
    {
        Config::set('jitsi.app_id', 'test-app-id');
        $this->mock(FileService::class, fn ($mock) => $mock->shouldNotReceive('getFileFromUrl'));

        $this->postWebhook($this->body, null)->assertUnauthorized();
        $this->postWebhook($this->body, 'wrong-token')->assertUnauthorized();

        $payload = json_encode($this->body);
        $timestamp = time();
        $forged = base64_encode(hash_hmac('sha256', $timestamp . '.' . $payload, 'other-secret', true));
        $this->call('POST', '/api/jitsi/recorded-video', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_JAAS_SIGNATURE' => "t={$timestamp},v1={$forged}",
        ], $payload)->assertUnauthorized();

        Config::set('jitsi.webhook_token', null);
        Config::set('jitsi.webhook_secret', null);
        $this->postWebhook($this->body)->assertForbidden();
    }

    public function testRecordedVideoWebhookAcceptsValidJaasSignature(): void
    {
        Config::set('jitsi.app_id', 'test-app-id');
        $this->mockGetFileContent('nagrywaniewideo_consultations_11_1728385200_2024-10-08-11-35-05.mp4');

        $payload = json_encode($this->body);
        $timestamp = time();
        $signature = base64_encode(hash_hmac('sha256', $timestamp . '.' . $payload, self::SECRET, true));

        $this->call('POST', '/api/jitsi/recorded-video', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_JAAS_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload)->assertOk();

        Storage::assertExists('consultations/11/1728385200/1728387309767.mp4');
    }

    /**
     * @dataProvider disallowedRecordingUrls
     */
    public function testRecordedVideoIsNotDownloadedFromDisallowedUrl(string $url): void
    {
        Config::set('jitsi.app_id', 'test-app-id');
        $this->mock(FileService::class, fn ($mock) => $mock->shouldNotReceive('getFileFromUrl'));

        $this->body['data']['preAuthenticatedLink'] = $url;

        $this->postWebhook($this->body)->assertUnprocessable();
    }

    public static function disallowedRecordingUrls(): array
    {
        return [
            'plain http' => ['http://localhost/recording.mp4'],
            'host not on allow-list' => ['https://attacker.example/recording.mp4'],
            'metadata service' => ['https://169.254.169.254/latest/meta-data.mp4'],
            'file scheme' => ['file:///etc/passwd'],
            'credentials in url' => ['https://user:pass@localhost/recording.mp4'],
            'not a video' => ['https://localhost/recording.php'],
        ];
    }

    public function testRecordedVideoRejectsPathTraversalInFqn(): void
    {
        Config::set('jitsi.app_id', 'test-app-id');
        $this->mock(FileService::class, fn ($mock) => $mock->shouldNotReceive('getFileFromUrl'));

        $this->body['fqn'] = 'test-app-id/nagrywaniewideo_.._.._config';

        $this->postWebhook($this->body)->assertUnprocessable();
    }

    private function mockGetFileContent(string $filename): void
    {
        $this->mock(FileService::class, function ($mock) use ($filename) {
            $mock->shouldReceive('getFileFromUrl')->with($this->url)->andReturn(UploadedFile::fake()->create($filename, 50, 'video/mp4'));
        });
    }
}
