<?php

namespace Ulams\Interactive\Tests\Feature;

use Illuminate\Validation\ValidationException;
use Ulams\Interactive\Services\ManifestValidator;
use Ulams\Interactive\Tests\TestCase;

class ManifestValidatorTest extends TestCase
{
    private function base(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/packages/steps/ulams-interactive.json'), true);
    }

    private function errors(array $manifest, array $paths = ['index.html', 'posters/intro.webp', 'ulams-interactive.json']): array
    {
        try {
            app(ManifestValidator::class)->validate(json_encode($manifest), $paths);
        } catch (ValidationException $e) {
            return $e->errors()['manifest'];
        }

        return [];
    }

    private function assertRejected(array $manifest, string $fragment, array $paths = ['index.html', 'posters/intro.webp', 'ulams-interactive.json']): void
    {
        $joined = implode("\n", $this->errors($manifest, $paths));
        $this->assertNotSame('', $joined, 'the manifest was accepted');
        $this->assertStringContainsString($fragment, $joined);
    }

    public function testAValidManifestIsReturned(): void
    {
        $this->assertSame([], $this->errors($this->base()));
        $manifest = app(ManifestValidator::class)->validate(json_encode($this->base()), ['index.html', 'posters/intro.webp']);
        $this->assertSame('steps', $manifest['id']);
        $this->assertSame('index.html', $manifest['entry']);
    }

    public function testTheEntryDefaultsToIndexHtml(): void
    {
        $manifest = $this->base();
        unset($manifest['entry']);
        $this->assertSame([], $this->errors($manifest));
    }

    public function testNotJsonAndNotAnObject(): void
    {
        foreach (['{nope', '[]', '"x"', ''] as $json) {
            try {
                app(ManifestValidator::class)->validate($json, []);
                $this->fail("accepted {$json}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('manifest', $e->errors());
            }
        }
    }

    public function testSchemaViolations(): void
    {
        $m = $this->base();
        $this->assertRejected(['id' => 'Bad Id'] + $m, '/id');
        $this->assertRejected(['bridge' => 2] + $m, '/bridge');
        $this->assertRejected(['steps' => []] + $m, '/steps');
        $this->assertRejected(['unknown' => true] + $m, 'unknown');
        $this->assertRejected(['network' => ['http://insecure.example.com']] + $m, '/network');
        $this->assertRejected(['network' => ['https://*.example.com']] + $m, '/network');
        $this->assertRejected(['network' => ['https://example.com/path']] + $m, '/network');
        $this->assertRejected(['requires' => ['gpu']] + $m, '/requires');
        $this->assertRejected(['entry' => '../index.html'] + $m, '/entry');
        $this->assertRejected(['entry' => '/index.html'] + $m, '/entry');
        $bad = $m;
        $bad['steps'][1]['id'] = 'Bad Step';
        $this->assertRejected($bad, '/steps/1/id');
        $missing = $m;
        unset($missing['licence']);
        $this->assertRejected($missing, 'licence');
    }

    public function testTheLicenceMustBeOnTheAllowList(): void
    {
        $this->assertRejected(['licence' => 'WTFPL'] + $this->base(), 'not an accepted SPDX id');
        foreach (['MIT', 'GPL-3.0-only', 'CC-BY-SA-4.0', 'LicenseRef-Proprietary'] as $ok) {
            $this->assertSame([], $this->errors(['licence' => $ok] + $this->base()), $ok);
        }
    }

    public function testEveryStepNeedsATextAlternativeInEveryLocale(): void
    {
        $m = $this->base();
        unset($m['steps'][1]['text']['pl']);
        $this->assertRejected($m, 'steps[1].text: missing for the locale "pl"');

        $m = $this->base();
        $m['steps'][2]['text']['en'] = '   ';
        $this->assertRejected($m, 'steps[2].text: missing for the locale "en"');

        $m = $this->base();
        unset($m['steps'][0]['title']['pl']);
        $this->assertRejected($m, 'steps[0].title: missing for the locale "pl"');
    }

    public function testDuplicateStepIdsAndLocaleRules(): void
    {
        $m = $this->base();
        $m['steps'][1]['id'] = 'intro';
        $this->assertRejected($m, '"intro" is used twice');

        $this->assertRejected(['defaultLocale' => 'de'] + $this->base(), 'defaultLocale: must be one of locales');
        $this->assertRejected(['title' => ['pl' => 'tylko']] + $this->base(), 'title: needs the defaultLocale');
        $this->assertRejected(['title' => ['en' => 'x', 'de' => 'y']] + $this->base(), 'not listed in locales');
    }

    public function testEntryAndPostersMustExistInTheArchive(): void
    {
        $this->assertRejected($this->base(), 'entry: "index.html" is not in the archive', ['posters/intro.webp']);
        $this->assertRejected($this->base(), 'poster: "posters/intro.webp" is not in the archive', ['index.html']);
        $this->assertRejected(['entry' => 'app.js'] + $this->base(), 'must be an .html file', ['app.js', 'posters/intro.webp']);
    }

    public function testTheShowcaseNamesRealStepsAndAPosterInTheArchive(): void
    {
        $this->assertSame([], $this->errors($this->base()));
        $this->assertRejected(['showcase' => ['steps' => ['intro', 'nowhere']]] + $this->base(), 'showcase.steps[1]: "nowhere" is not a step');
        $this->assertRejected(['showcase' => ['steps' => ['intro'], 'poster' => 'posters/showcase.webp']] + $this->base(), 'showcase.poster: "posters/showcase.webp" is not in the archive');
        $this->assertRejected(['showcase' => ['steps' => []]] + $this->base(), '/showcase');
        $this->assertRejected(['showcase' => ['steps' => ['intro'], 'poster' => '../x.webp']] + $this->base(), '/showcase');
        $this->assertRejected(['showcase' => ['steps' => ['intro'], 'loop' => true]] + $this->base(), '/showcase');
    }

    public function testAtMostTwoHundredSteps(): void
    {
        $m = $this->base();
        $m['steps'] = array_map(fn ($i) => ['id' => "s{$i}", 'title' => ['en' => 'T', 'pl' => 'T'], 'text' => ['en' => 'x', 'pl' => 'x']], range(1, 201));
        unset($m['steps'][0]['poster']);
        $this->assertRejected($m, '/steps');
    }

    public function testTheTwoSchemaCopiesAreIdentical(): void
    {
        $api = __DIR__ . '/../../resources/schemas/ulams-interactive/v1.json';
        $bridge = __DIR__ . '/../../../../../front/interactive-bridge/schema/ulams-interactive.v1.json';
        if (!is_file($bridge)) {
            $this->markTestSkipped('front/ is outside the mounted api/ directory');
        }
        $this->assertSame(file_get_contents($bridge), file_get_contents($api));
        foreach (glob(dirname($bridge) . '/v1/*.json') as $file) {
            $this->assertSame(file_get_contents($file), file_get_contents(__DIR__ . '/../../resources/schemas/ulams-ix/v1/' . basename($file)), basename($file));
        }
    }
}
