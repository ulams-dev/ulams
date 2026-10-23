<?php

namespace Tests\Integrations;

use Tests\TestCase;

/**
 * Seeded demo content must hold plain public storage URLs for images, never /api/images/img or imgcache/ URLs, and
 * the images a demo tenant shows must stay few and stable enough for the Cloudflare Image Transformations free plan
 * (5,000 unique source + option-set pairs per month; docs/plans/images-cloudflare.md, section 6.5).
 *
 * The seed code reaches storage through Storage::url() only (DemoExperience::publicUrl), course ids and file names
 * come out the same after every hourly `migrate:fresh` and reseed, so the URLs, and the transformations they cause,
 * repeat from reset to reset. The checks below read the seed sources, so they run without generating any media.
 */
class DemoImageUrlsTest extends TestCase
{
    /** The widest preset (`content`: 360, 640, 960, 1280, 1600). */
    private const WIDTHS_PER_IMAGE = 5;
    /** Avatars of tutors and students plus the tenant logo, per experience, at the `avatar` preset's widths. */
    private const PEOPLE_AND_LOGO = 12;
    /** Plan 6.5: one tenant, all of its images at every width. */
    private const BUDGET_PER_TENANT = 300;
    /** Plan 6.5: the expected total is 2,000-3,000 of the 5,000; the rest is the landing (about 380) and headroom. */
    private const BUDGET_ALL_TENANTS = 3000;

    private function seedDir(): string
    {
        return dirname(__DIR__, 2) . '/database/seeds/Demo';
    }

    /** @return array<string, string> relative path => contents, for every PHP and Markdown seed source */
    private function sources(): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->seedDir(), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (in_array($file->getExtension(), ['php', 'md', 'json'], true)) {
                $found[substr($file->getPathname(), strlen($this->seedDir()) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($found);

        return $found;
    }

    public function testSeedSourcesNeverWriteImagesApiOrCacheUrls(): void
    {
        $sources = $this->sources();
        $this->assertNotEmpty($sources);

        foreach ($sources as $name => $text) {
            $this->assertStringNotContainsString('api/images/img', $text, "$name stores an images API URL");
            $this->assertStringNotContainsString('imgcache/', $text, "$name stores an imgcache URL");
        }
    }

    public function testSeededAssetsAreStoredAsPlainStorageUrls(): void
    {
        $base = (string) file_get_contents($this->seedDir() . '/DemoExperience.php');

        $this->assertMatchesRegularExpression('/function publicUrl\(string \$path\): string\s*\{[^}]*Storage::url\(/s', $base);
        $this->assertStringContainsString("'course/' . \$this->course->getKey() . '/assets'", $base, 'asset paths depend on the course id and the file name only');
    }

    public function testImageNamesAreStaticSoUrlsRepeatAcrossResets(): void
    {
        foreach ($this->sources() as $name => $text) {
            if (!str_ends_with($name, 'Experience.php') && !str_starts_with($name, 'Support/')) {
                continue;
            }
            preg_match_all('/->image\(([^,]+),/', $text, $calls);
            foreach ($calls[1] as $argument) {
                $this->assertDoesNotMatchRegularExpression('/uniqid|random|rand\(|mt_rand|time\(|now\(|Str::uuid|microtime/i', $argument, "$name: image name $argument changes between seeds");
            }
        }
    }

    public function testUniqueTransformationsPerMonthStayUnderTheEstimate(): void
    {
        $total = 0;
        $report = [];

        foreach ($this->sources() as $name => $text) {
            if (!preg_match('~^([A-Za-z]+Experience)\.php$~', $name, $m) || $m[1] === 'DemoExperience') {
                continue;
            }
            preg_match_all('/[\'"]([\w.\-]+\.(?:png|jpe?g|webp))[\'"]/i', $text, $named);
            preg_match_all('/\{\{asset:([\w.\-]+)\}\}/', $text, $placeholders);
            $images = count(array_unique(array_merge($named[1], $placeholders[1]))) + self::PEOPLE_AND_LOGO;
            $perTenant = $images * self::WIDTHS_PER_IMAGE;
            $report[$m[1]] = $perTenant;
            $total += $perTenant;

            $this->assertLessThanOrEqual(self::BUDGET_PER_TENANT, $perTenant, "{$m[1]}: $images images x " . self::WIDTHS_PER_IMAGE . ' widths');
        }

        // Markdown content references assets by placeholder too.
        $byContent = [];
        foreach ($this->sources() as $name => $text) {
            if (preg_match('~^content/([^/]+)/.*\.md$~', $name, $m)) {
                preg_match_all('/\{\{asset:([\w.\-]+)\}\}/', $text, $placeholders);
                $byContent[$m[1]] = array_merge($byContent[$m[1]] ?? [], $placeholders[1]);
            }
        }
        $this->assertNotEmpty($byContent);
        foreach ($byContent as $key => $assets) {
            $this->assertLessThanOrEqual(self::BUDGET_PER_TENANT, count(array_unique($assets)) * self::WIDTHS_PER_IMAGE, "content/$key");
        }

        $this->assertGreaterThan(0, $total, 'the experiences were found');
        $this->assertLessThanOrEqual(self::BUDGET_ALL_TENANTS, $total, 'sum over experiences ' . json_encode($report));
    }
}
