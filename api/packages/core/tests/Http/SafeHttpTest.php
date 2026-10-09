<?php

namespace Ulams\Core\Tests\Http;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Ulams\Core\Http\SafeHttp;
use Ulams\Core\Http\UnsafeUrlException;
use Ulams\Core\Tests\TestCase;

/** ADR 0032: the shared SSRF-safe client, against a fake resolver (no network). */
class SafeHttpTest extends TestCase
{
    protected function tearDown(): void
    {
        SafeHttp::useResolver(null);
        parent::tearDown();
    }

    /** @param array<string,string[]> $hosts */
    private function dns(array $hosts): void
    {
        SafeHttp::useResolver(fn (string $host) => $hosts[$host] ?? []);
    }

    private function assertRefused(string $url, array $options = [], string $why = ''): void
    {
        try {
            SafeHttp::check($url, $options);
        } catch (UnsafeUrlException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail("{$url} was accepted. {$why}");
    }

    public function testAddressLiteralsInEveryPrivateRangeAreRefused(): void
    {
        foreach ([
            'https://127.0.0.1/x', 'https://10.0.0.5/x', 'https://172.16.0.1/x', 'https://192.168.1.1/x', 'https://169.254.169.254/latest/meta-data',
            'https://100.64.0.1/x', 'https://0.0.0.0/x', 'https://[::1]/x', 'https://[::]/x', 'https://[::ffff:10.0.0.1]/x', 'https://[::ffff:169.254.169.254]/x',
            'https://[fc00::1]/x', 'https://[fd12:3456::1]/x', 'https://[fe80::1]/x', 'https://[ff02::1]/x', 'https://[64:ff9b::a00:1]/x', 'https://224.0.0.1/x', 'https://240.0.0.1/x',
        ] as $url) {
            $this->assertRefused($url);
        }
        $this->assertSame('1.1.1.1:443:1.1.1.1', SafeHttp::check('https://1.1.1.1/x'));
        $this->assertSame('2606:4700:4700::1111:8443:2606:4700:4700::1111', SafeHttp::check('https://[2606:4700:4700::1111]:8443/x'));
    }

    public function testOnlyHttpsAndPlainHostsAreAccepted(): void
    {
        $this->assertRefused('http://example.com/x');
        $this->assertRefused('ftp://example.com/x');
        $this->assertRefused('file:///etc/passwd');
        $this->assertRefused('https://user:pass@example.com/x', [], 'credentials in the URL');
        $this->assertRefused('https:///nohost');
    }

    public function testANameThatResolvesToAPrivateAddressIsRefusedEvenWhenOtherRecordsArePublic(): void
    {
        $this->dns(['good.example' => ['93.184.216.34'], 'rebind.example' => ['93.184.216.34', '10.0.0.7'], 'v6.example' => ['2606:4700::1', 'fd00::5'], 'void.example' => []]);

        $this->assertSame('good.example:443:93.184.216.34', SafeHttp::check('https://good.example/x'));
        $this->assertRefused('https://rebind.example/x');
        $this->assertRefused('https://v6.example/x');
        $this->assertRefused('https://void.example/x');
    }

    public function testHostLists(): void
    {
        $this->dns(['docs.example.dev' => ['93.184.216.34'], 'other.example.dev' => ['93.184.216.35']]);
        $this->assertRefused('https://other.example.dev/x', ['allowed_hosts' => ['docs.example.dev']]);
        $this->assertSame('docs.example.dev:443:93.184.216.34', SafeHttp::check('https://docs.example.dev/x', ['allowed_hosts' => ['DOCS.example.dev']]));
        // a named insecure host (the compose Gitea of the tests) and the development switch skip the checks
        $this->assertNull(SafeHttp::check('http://gitea:3000/x', ['insecure_hosts' => ['gitea']]));
        $this->assertNull(SafeHttp::check('http://moodle:8080/x', ['allow_insecure' => true]));
        $this->assertRefused('http://other:3000/x', ['insecure_hosts' => ['gitea']]);
    }

    public function testTheCheckedAddressIsPinnedForTheConnection(): void
    {
        $this->dns(['good.example' => ['93.184.216.34']]);
        $seen = [];
        $mock = new MockHandler([function ($request, array $options) use (&$seen) {
            $seen = $options['curl'] ?? [];

            return new Response(200, [], 'ok');
        }]);

        $body = (string) SafeHttp::client($mock)->get('https://good.example/path')->getBody();

        $this->assertSame('ok', $body);
        $this->assertSame(['good.example:443:93.184.216.34'], $seen[CURLOPT_RESOLVE]);
    }

    public function testRequestsToPrivateAddressesNeverReachTheHandler(): void
    {
        $this->dns(['intranet.example' => ['10.1.2.3']]);
        $mock = new MockHandler([new Response(200, [], 'secret')]);

        $this->expectException(UnsafeUrlException::class);
        try {
            SafeHttp::client($mock)->get('https://intranet.example/admin');
        } finally {
            $this->assertCount(1, $mock, 'the queued response was not consumed');
        }
    }

    public function testRedirectsAreOffByDefault(): void
    {
        $this->dns(['good.example' => ['93.184.216.34']]);
        $mock = new MockHandler([new Response(302, ['Location' => 'https://good.example/elsewhere']), new Response(200, [], 'followed')]);

        $response = SafeHttp::client($mock)->get('https://good.example/x', ['http_errors' => false]);

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testEveryRedirectHopIsCheckedAndMustStayOnTheHost(): void
    {
        $this->dns(['good.example' => ['93.184.216.34'], 'other.example' => ['93.184.216.99'], 'evil.example' => ['10.0.0.1']]);

        $ok = new MockHandler([new Response(301, ['Location' => 'https://good.example/new']), new Response(200, [], 'moved')]);
        $this->assertSame('moved', (string) SafeHttp::client($ok, ['max_redirects' => 3])->get('https://good.example/old')->getBody());

        $other = new MockHandler([new Response(302, ['Location' => 'https://other.example/x']), new Response(200, [], 'nope')]);
        try {
            SafeHttp::client($other, ['max_redirects' => 3])->get('https://good.example/old');
            $this->fail('A redirect to another host was followed.');
        } catch (UnsafeUrlException $e) {
            $this->assertStringContainsString('another host', $e->getMessage());
        }

        $private = new MockHandler([new Response(302, ['Location' => 'https://evil.example/x'])]);
        $this->expectException(UnsafeUrlException::class);
        SafeHttp::client($private, ['max_redirects' => 3])->get('https://good.example/old');
    }

    public function testALargeResponseIsCutOff(): void
    {
        $this->dns(['good.example' => ['93.184.216.34']]);
        $mock = new MockHandler([new Response(200, ['Content-Length' => '999999'], 'x')]);

        try {
            SafeHttp::client($mock, ['max_bytes' => 1000])->get('https://good.example/big');
            $this->fail('The large response was read.');
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Guzzle wraps what an on_headers callback throws
            $this->assertInstanceOf(UnsafeUrlException::class, $e->getPrevious());
            $this->assertStringContainsString('larger than 1000 bytes', $e->getPrevious()->getMessage());
        }
    }

    public function testACallerCanAskForItsOwnExceptionType(): void
    {
        $this->expectException(\DomainException::class);
        SafeHttp::check('http://example.com', ['exception' => fn (string $m) => new \DomainException($m)]);
    }
}
