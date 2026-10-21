<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder;
use Ulams\TemplatesEmail\Tests\TestCase;

class EmailImageUploadTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private const URL = '/api/admin/templates/email/images';
    /** 1x1 PNG */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    /** 1x1 GIF */
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
        Storage::fake();
    }

    private function upload(string $name, string $content, ?string $mime = null)
    {
        return $this->actingAs($this->makeAdmin(), 'api')->post(
            self::URL,
            ['image' => UploadedFile::fake()->createWithContent($name, $content)],
            ['Accept' => 'application/json']
        );
    }

    public function testAPngIsStoredInTheTenantStorageUnderARandomName(): void
    {
        $data = $this->upload('My Logo (final).png', base64_decode(self::PNG))->assertOk()->json('data');

        $this->assertMatchesRegularExpression('#^email-images/[0-9a-f-]{36}\.png$#', $data['name']);
        $this->assertStringNotContainsString('logo', $data['name']);
        $this->assertSame('image/png', $data['mime']);
        $this->assertSame([1, 1], [$data['width'], $data['height']]);
        $this->assertNotEmpty($data['url']);
        Storage::disk()->assertExists($data['name']);
    }

    public function testTheTypeComesFromTheContentNotTheName(): void
    {
        // A GIF named .png is stored as .gif
        $data = $this->upload('x.png', base64_decode(self::GIF))->assertOk()->json('data');
        $this->assertStringEndsWith('.gif', $data['name']);
        $this->assertSame('image/gif', $data['mime']);
    }

    public function testSvgIsRefusedEvenWithAScript(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>';
        foreach (['logo.svg', 'logo.png'] as $name) {
            $this->upload($name, $svg)->assertUnprocessable()->assertJsonValidationErrors(['image']);
        }
        $this->assertSame([], Storage::disk()->allFiles());
    }

    public function testScriptsAndOtherTypesAreRefused(): void
    {
        $this->upload('shell.php', '<?php system($_GET["c"]);')->assertUnprocessable();
        $this->upload('shell.png', '<?php system($_GET["c"]);')->assertUnprocessable();
        $this->upload('page.html', '<html><script>alert(1)</script></html>')->assertUnprocessable();
        $this->upload('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")->assertUnprocessable();
        $this->assertSame([], Storage::disk()->allFiles());
    }

    public function testAnImageHeaderWithAPayloadThatIsNotAnImageIsRefused(): void
    {
        $this->upload('fake.png', "\x89PNG\r\n\x1a\n" . '<?php echo 1;')->assertUnprocessable();
        $this->assertSame([], Storage::disk()->allFiles());
    }

    public function testTheSizeLimitIsEnforced(): void
    {
        config(['ulams_templates_email.images.max_kb' => 1]);
        $big = base64_decode(self::PNG) . str_repeat("\0", 2048);
        $this->upload('big.png', $big)->assertUnprocessable()->assertJsonValidationErrors(['image']);
        $this->assertSame([], Storage::disk()->allFiles());
    }

    public function testTheFileIsRequired(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors(['image']);
    }

    public function testUsersWithoutTheTemplateUpdatePermissionAreRefused(): void
    {
        $file = fn () => ['image' => UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG))];
        $this->actingAs($this->makeStudent(), 'api')->post(self::URL, $file(), ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->makeInstructor(), 'api')->post(self::URL, $file(), ['Accept' => 'application/json'])->assertForbidden();
        $this->assertSame([], Storage::disk()->allFiles());
    }

    public function testAnonymousRequestsAreRefused(): void
    {
        $this->postJson(self::URL, [])->assertUnauthorized();
    }
}
