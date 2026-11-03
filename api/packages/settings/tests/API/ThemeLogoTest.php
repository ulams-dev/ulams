<?php

namespace Ulams\Settings\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Settings\Database\Seeders\DatabaseSeeder;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder;
use Ulams\Settings\Models\Setting;
use Ulams\Settings\Tests\TestCase;

/** The tenant logo endpoint: a PNG or a sanitised SVG of at most 200 KB, stored under a random name. */
class ThemeLogoTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private const URL = '/api/admin/theme/logo';

    /** 1x1 PNG */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40"><!-- editor --><rect width="120" height="40" fill="#c8102e"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
        $this->seed(DatabaseSeeder::class);
        Setting::query()->where('group', 'theme')->delete();
        Storage::fake();
    }

    private function upload(string $name, string $content, array $extra = [], $user = null)
    {
        return $this->actingAs($user ?? $this->makeAdmin(), 'api')->post(
            self::URL,
            ['logo' => UploadedFile::fake()->createWithContent($name, $content)] + $extra,
            ['Accept' => 'application/json']
        );
    }

    private function files(): array
    {
        return Storage::disk()->allFiles();
    }

    #[Test]
    public function testAPngIsStoredUnderARandomNameAndPublished(): void
    {
        $data = $this->upload('My Logo (final).png', base64_decode(self::PNG), ['alt' => 'Acme Coffee'])->assertOk()->json('data');

        $this->assertMatchesRegularExpression('#^theme/logo-[0-9a-f-]{36}\.png$#', $data['name']);
        $this->assertStringNotContainsString('final', $data['name']);
        $this->assertSame('image/png', $data['mime']);
        $this->assertSame([1, 1], [$data['width'], $data['height']]);
        $this->assertSame('Acme Coffee', $data['alt']);
        Storage::disk()->assertExists($data['name']);

        $public = $this->getJson('/api/settings')->assertOk()->json('data.theme');
        $this->assertSame($data['url'], $public['logo']);
        $this->assertSame('Acme Coffee', $public['logoAlt']);
    }

    #[Test]
    public function testAnSvgIsStoredInItsSanitisedForm(): void
    {
        $data = $this->upload('logo.svg', self::SVG)->assertOk()->json('data');

        $this->assertMatchesRegularExpression('#^theme/logo-[0-9a-f-]{36}\.svg$#', $data['name']);
        $this->assertSame('image/svg+xml', $data['mime']);
        $this->assertEquals([120, 40], [$data['width'], $data['height']]);
        $stored = Storage::disk()->get($data['name']);
        $this->assertStringContainsString('<rect', $stored);
        $this->assertStringNotContainsString('editor', $stored);
    }

    #[Test]
    public function testTheTypeComesFromTheContentNotTheName(): void
    {
        $data = $this->upload('x.svg', base64_decode(self::PNG))->assertOk()->json('data');
        $this->assertStringEndsWith('.png', $data['name']);
        $data = $this->upload('x.png', self::SVG)->assertOk()->json('data');
        $this->assertStringEndsWith('.svg', $data['name']);
    }

    public static function unsafeSvgs(): array
    {
        $ns = 'xmlns="http://www.w3.org/2000/svg"';

        return [
            'script' => ["<svg $ns><script>alert(1)</script><rect width=\"1\" height=\"1\"/></svg>", 'script'],
            'onload' => ["<svg $ns onload=\"alert(document.domain)\"><rect/></svg>", 'onload'],
            'foreignObject' => ["<svg $ns><foreignObject><iframe/></foreignObject></svg>", 'foreignObject'],
            'external image' => ["<svg $ns><image href=\"https://evil.example/pixel.png\"/></svg>", 'image'],
            'external use' => ["<svg $ns><use href=\"https://evil.example/a.svg#x\"/></svg>", 'href'],
            'style import' => ["<svg $ns><style>@import url(https://evil.example/x.css);</style></svg>", 'style'],
            'xxe' => ["<!DOCTYPE svg [<!ENTITY x SYSTEM \"file:///etc/passwd\">]><svg $ns><text>&x;</text></svg>", 'DOCTYPE'],
            'billion laughs' => ["<!DOCTYPE svg [<!ENTITY a \"aaaaaaaaaa\"><!ENTITY b \"&a;&a;&a;&a;&a;&a;&a;&a;\">]><svg $ns><text>&b;</text></svg>", 'DOCTYPE'],
            'svg as html polyglot' => ["<html><body><svg $ns><rect/></svg><script>alert(1)</script></body></html>", 'svg'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeSvgs')]
    public function testAnUnsafeSvgIsRefusedWithTheReasonAndNothingIsStored(string $svg, string $reason): void
    {
        foreach (['logo.svg', 'logo.png', 'logo.html'] as $name) {
            $response = $this->upload($name, $svg)->assertUnprocessable()->assertJsonValidationErrors(['logo']);
            $this->assertStringContainsStringIgnoringCase($reason, $response->json('errors.logo.0'));
        }
        $this->assertSame([], $this->files());
        $this->assertSame(0, Setting::query()->where('group', 'theme')->count());
    }

    #[Test]
    public function testScriptsAndOtherTypesAreRefused(): void
    {
        $this->upload('shell.php', '<?php system($_GET["c"]);')->assertUnprocessable();
        $this->upload('shell.png', '<?php system($_GET["c"]);')->assertUnprocessable();
        $this->upload('shell.svg', '<?php system($_GET["c"]);')->assertUnprocessable();
        $this->upload('page.html', '<html><script>alert(1)</script></html>')->assertUnprocessable();
        $this->upload('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")->assertUnprocessable();
        $this->upload('photo.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))->assertUnprocessable();
        $this->upload('photo.jpg', "\xFF\xD8\xFF\xE0" . str_repeat("\0", 64))->assertUnprocessable();
        $this->upload('empty.png', '')->assertUnprocessable();
        $this->assertSame([], $this->files());
    }

    #[Test]
    public function testAPngHeaderWithAPayloadThatIsNotAnImageIsRefused(): void
    {
        $this->upload('fake.png', "\x89PNG\r\n\x1a\n" . '<?php echo 1;')->assertUnprocessable();
        $this->assertSame([], $this->files());
    }

    #[Test]
    public function testThePixelSizeIsBounded(): void
    {
        // a valid PNG header that declares 5000 x 5000 pixels
        $png = base64_decode(self::PNG);
        $png = substr($png, 0, 16) . pack('N', 5000) . pack('N', 5000) . substr($png, 24);
        $this->upload('huge.png', $png)->assertUnprocessable();
        $this->assertSame([], $this->files());
    }

    #[Test]
    public function testTheSizeLimitIs200KbForBothTypes(): void
    {
        $png = base64_decode(self::PNG) . str_repeat("\0", 200 * 1024);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><!--' . str_repeat('a', 200 * 1024) . '--><rect/></svg>';
        $this->upload('big.png', $png)->assertUnprocessable()->assertJsonValidationErrors(['logo']);
        $this->upload('big.svg', $svg)->assertUnprocessable()->assertJsonValidationErrors(['logo']);
        $this->assertSame([], $this->files());

        // just under the limit passes
        $ok = '<svg xmlns="http://www.w3.org/2000/svg"><!--' . str_repeat('a', 150 * 1024) . '--><rect/></svg>';
        $this->upload('ok.svg', $ok)->assertOk();
    }

    #[Test]
    public function testTheFileIsRequiredAndTheAltTextHasALimit(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors(['logo']);
        $this->upload('a.png', base64_decode(self::PNG), ['alt' => str_repeat('a', 121)])->assertUnprocessable()->assertJsonValidationErrors(['alt']);
        $this->assertSame([], $this->files());
    }

    #[Test]
    public function testAnArrayInsteadOfAFileIsRefused(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')->postJson(self::URL, ['logo' => ['a.png']])->assertUnprocessable();
        $this->actingAs($this->makeAdmin(), 'api')->postJson(self::URL, ['logo' => '../../.env'])->assertUnprocessable();
    }

    #[Test]
    public function testAReplacementRemovesThePreviousFileAndAnEmptyAltClearsTheOldOne(): void
    {
        $admin = $this->makeAdmin();
        $first = $this->upload('a.png', base64_decode(self::PNG), ['alt' => 'Old'], $admin)->assertOk()->json('data.name');
        $second = $this->upload('b.svg', self::SVG, [], $admin)->assertOk()->json('data.name');

        Storage::disk()->assertMissing($first);
        Storage::disk()->assertExists($second);
        $this->assertCount(1, $this->files());
        $this->assertSame(1, Setting::query()->where(['group' => 'theme', 'key' => 'logo'])->count());
        $this->assertSame(0, Setting::query()->where(['group' => 'theme', 'key' => 'logoAlt'])->count());
        $this->assertSame($second, Setting::query()->where(['group' => 'theme', 'key' => 'logo'])->value('value'));
    }

    #[Test]
    public function testDeletingRemovesTheFileAndBothSettings(): void
    {
        $admin = $this->makeAdmin();
        $name = $this->upload('a.png', base64_decode(self::PNG), ['alt' => 'Acme'], $admin)->assertOk()->json('data.name');

        $this->actingAs($admin, 'api')->deleteJson(self::URL)->assertOk();
        Storage::disk()->assertMissing($name);
        $this->assertSame(0, Setting::query()->where('group', 'theme')->count());
        $this->getJson('/api/settings')->assertJsonMissingPath('data.theme.logo');
        // and again, with nothing there
        $this->actingAs($admin, 'api')->deleteJson(self::URL)->assertOk();
    }

    #[Test]
    public function testDeletingOnlyTouchesFilesThisEndpointStored(): void
    {
        Storage::disk()->put('course/secret.pdf', 'x');
        Setting::query()->create(['group' => 'theme', 'key' => 'logo', 'value' => 'course/secret.pdf', 'type' => 'image', 'public' => true, 'enumerable' => true]);

        $this->actingAs($this->makeAdmin(), 'api')->deleteJson(self::URL)->assertOk();
        Storage::disk()->assertExists('course/secret.pdf');
    }

    #[Test]
    public function testOnlyUsersWithTheSettingsUpdatePermissionMayChangeTheLogo(): void
    {
        $this->upload('a.png', base64_decode(self::PNG), [], $this->makeStudent())->assertForbidden();
        $this->upload('a.png', base64_decode(self::PNG), [], $this->makeInstructor())->assertForbidden();
        $this->actingAs($this->makeStudent(), 'api')->deleteJson(self::URL)->assertForbidden();
        $this->actingAs($this->makeInstructor(), 'api')->deleteJson(self::URL)->assertForbidden();
        $this->assertSame([], $this->files());
        $this->assertSame(0, Setting::query()->where('group', 'theme')->count());
    }

    #[Test]
    public function testAnonymousRequestsAreRefused(): void
    {
        $this->postJson(self::URL, [])->assertUnauthorized();
        $this->deleteJson(self::URL)->assertUnauthorized();
    }
}
