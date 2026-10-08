<?php

namespace Ulams\Uploads\Tests\Unit;

use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\Scanning\ClamdScanner;
use Ulams\Uploads\Tests\TestCase;

class ClamdScannerTest extends TestCase
{
    /** The EICAR anti-virus test string (harmless by definition). */
    private const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'ulams-av-');
        file_put_contents($this->file, self::EICAR);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testSendsTheInstreamProtocolAndAcceptsACleanFile(): void
    {
        [$client, $server] = $this->socketPair("stream: OK\0");

        (new ClamdScanner(connect: fn () => $client))->scan($this->file);

        $sent = stream_get_contents($server);
        $this->assertStringStartsWith("zINSTREAM\0", $sent);
        $this->assertSame(pack('N', strlen(self::EICAR)) . self::EICAR . pack('N', 0), substr($sent, 10));
    }

    public function testRejectsAnInfectedFile(): void
    {
        [$client, $server] = $this->socketPair("stream: Eicar-Test-Signature FOUND\0");

        try {
            (new ClamdScanner(connect: fn () => $client))->scan($this->file);
            $this->fail('Expected the EICAR file to be rejected.');
        } catch (UploadRejected $e) {
            $this->assertSame('infected', $e->reason);
            $this->assertStringContainsString('Eicar-Test-Signature', $e->getMessage());
        }
    }

    public function testFailsClosedWhenClamdIsUnreachable(): void
    {
        $scanner = new ClamdScanner(connect: fn () => throw new \RuntimeException('connection refused'));

        $this->expectExceptionObject(new UploadRejected('scan_failed', 'The file could not be scanned for viruses; try again later.'));
        $scanner->scan($this->file);
    }

    public function testCanFailOpen(): void
    {
        (new ClamdScanner(failClosed: false, connect: fn () => throw new \RuntimeException('down')))->scan($this->file);

        $this->addToAssertionCount(1);
    }

    /**
     * @return resource[] [client, server]; the server's reply is written up front.
     */
    private function socketPair(string $reply): array
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($server, $reply);

        return [$client, $server];
    }
}
