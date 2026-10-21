<?php

namespace Ulams\TemplatesEmail\Tests\Services;

use Illuminate\Support\Facades\Log;
use Mockery;
use Qferrer\Mjml\Exception\ApiException;
use Qferrer\Mjml\Http\CurlInterface;
use Qferrer\Mjml\Http\CurlResponse;
use Ulams\TemplatesEmail\Services\CurlApi;
use Ulams\TemplatesEmail\Services\MjmlService;
use Ulams\TemplatesEmail\Tests\TestCase;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;

class CurlApiTest extends TestCase
{
    private function curl(int $status, array $body, ?array &$captured = null): CurlInterface
    {
        $curl = Mockery::mock(CurlInterface::class);
        $curl->shouldReceive('request')->once()->andReturnUsing(function (string $url, array $options) use ($status, $body, &$captured) {
            $captured = ['url' => $url, 'options' => $options];

            return new CurlResponse(json_encode($body), $status);
        });

        return $curl;
    }

    public function testRequestIsBounded(): void
    {
        $captured = null;
        $api = new CurlApi('http://mjml:15500/v1', $this->curl(200, ['html' => '<p>ok</p>'], $captured));

        $this->assertSame('<p>ok</p>', $api->getHtml('<mjml></mjml>'));

        $this->assertSame('http://mjml:15500/v1/render', $captured['url']);
        $options = $captured['options'];
        $this->assertSame(5, $options[CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(20, $options[CURLOPT_TIMEOUT]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame(0, $options[CURLOPT_MAXREDIRS]);
    }

    public function testTimeoutsAreConfigurable(): void
    {
        $captured = null;
        $api = new CurlApi('http://mjml:15500/v1', $this->curl(200, ['html' => ''], $captured), 2, 7);

        $api->getHtml('<mjml></mjml>');

        $this->assertSame(2, $captured['options'][CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(7, $captured['options'][CURLOPT_TIMEOUT]);
    }

    public function testServiceReadsTimeoutsFromConfig(): void
    {
        config([
            UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_url' => 'http://mjml:15500/v1',
            UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.connect_timeout' => 3,
            UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.timeout' => 9,
        ]);

        $renderer = (new MjmlService())->renderer();
        $client = (new \ReflectionProperty($renderer, 'client'))->getValue($renderer);

        $this->assertSame(3, (new \ReflectionProperty($client, 'connectTimeout'))->getValue($client));
        $this->assertSame(9, (new \ReflectionProperty($client, 'timeout'))->getValue($client));
    }

    public function testErrorsOfASuccessfulRenderAreLogged(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context) {
            return $context['errors'] === ['line 4: Attribute foo is illegal (mj-text)'];
        });
        $api = new CurlApi('http://mjml:15500/v1', $this->curl(200, [
            'html' => '<p>ok</p>',
            'errors' => [['line' => 4, 'message' => 'Attribute foo is illegal', 'tagName' => 'mj-text']],
        ]));

        $this->assertSame('<p>ok</p>', $api->getHtml('<mjml></mjml>'));
    }

    public function testErrorsAreInTheException(): void
    {
        $api = new CurlApi('http://mjml:15500/v1', $this->curl(400, [
            'message' => 'Invalid MJML template',
            'errors' => [['line' => 2, 'message' => 'Unknown tag', 'tagName' => 'mj-nope']],
        ]));

        try {
            $api->getHtml('<mjml><secret-template-text/></mjml>');
            $this->fail('An ApiException was expected.');
        } catch (ApiException $e) {
            $this->assertStringContainsString('Unexpected HTTP code: 400', $e->getMessage());
            $this->assertStringContainsString('Invalid MJML template', $e->getMessage());
            $this->assertStringContainsString('line 2: Unknown tag (mj-nope)', $e->getMessage());
            // Neither the template nor the endpoint is leaked into the message.
            $this->assertStringNotContainsString('secret-template-text', $e->getMessage());
            $this->assertStringNotContainsString('mjml:15500', $e->getMessage());
        }
    }

    public function testRedirectResponseIsAnError(): void
    {
        $api = new CurlApi('http://mjml:15500/v1', $this->curl(302, ['message' => 'Found']));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unexpected HTTP code: 302');

        $api->getHtml('<mjml></mjml>');
    }

    public function testNonJsonResponseIsAnError(): void
    {
        $curl = Mockery::mock(CurlInterface::class);
        $curl->shouldReceive('request')->once()->andReturn(new CurlResponse('<html>gateway</html>', 502));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unable to decode the JSON response');

        (new CurlApi('http://mjml:15500/v1', $curl))->getHtml('<mjml></mjml>');
    }
}
