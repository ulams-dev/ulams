<?php

namespace Ulams\Jitsi\Tests\Services;

use Ulams\Jitsi\Exceptions\RecordingUrlNotAllowedException;
use Ulams\Jitsi\Services\FileService;
use Ulams\Jitsi\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class FileServiceTest extends TestCase
{
    /**
     * @dataProvider blockedUrls
     */
    public function testDoesNotConnectToPrivateOrNonHttpsAddresses(string $url): void
    {
        Http::fake();

        try {
            (new FileService())->getFileFromUrl($url);
            $this->fail('Expected the URL to be refused: ' . $url);
        } catch (RecordingUrlNotAllowedException $exception) {
            $this->assertEquals(422, $exception->getCode());
        }

        Http::assertNothingSent();
    }

    public static function blockedUrls(): array
    {
        return [
            'loopback' => ['https://127.0.0.1/video.mp4'],
            'private 10/8' => ['https://10.0.0.5/video.mp4'],
            'private 192.168/16' => ['https://192.168.1.10/video.mp4'],
            'link-local metadata' => ['https://169.254.169.254/video.mp4'],
            'ipv6 loopback' => ['https://[::1]/video.mp4'],
            'ipv4-mapped ipv6' => ['https://[::ffff:10.0.0.1]/video.mp4'],
            'unspecified' => ['https://0.0.0.0/video.mp4'],
            'http' => ['http://93.184.216.34/video.mp4'],
            'file' => ['file:///etc/passwd'],
        ];
    }

    public function testDownloadsFromPublicAddressWithinSizeLimit(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('video-bytes')]);

        $stream = (new FileService())->getFileFromUrl('https://93.184.216.34/video.mp4');

        $this->assertEquals('video-bytes', stream_get_contents($stream));
        fclose($stream);
    }

    public function testRefusesRecordingsOverTheSizeLimit(): void
    {
        Config::set('jitsi.recording_max_bytes', 10);
        Http::fake(['93.184.216.34/*' => Http::response(str_repeat('a', 11))]);

        $this->expectException(RecordingUrlNotAllowedException::class);

        (new FileService())->getFileFromUrl('https://93.184.216.34/video.mp4');
    }

    public function testDoesNotFollowRedirects(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('', 302, ['Location' => 'https://127.0.0.1/video.mp4'])]);

        $this->expectException(RecordingUrlNotAllowedException::class);

        (new FileService())->getFileFromUrl('https://93.184.216.34/video.mp4');
    }
}
