<?php

namespace Ulams\LivingCourse\Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Ulams\LivingCourse\Tests\TestCase;

/**
 * Architecture guards: LMS entities are changed only through domain services, no model name is
 * hardcoded outside config, and every outbound request goes through the SSRF-safe client.
 */
class GuardsTest extends TestCase
{
    /** @return array<string,string> */
    private function sources(): array
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src', RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $files[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    public function testNoDirectWritesToLmsTables(): void
    {
        $offenders = [];
        foreach ($this->sources() as $path => $code) {
            // this package's own tables are written through its models, never the query builder on LMS tables
            if (preg_match('/DB::(insert|update|delete|statement|unprepared)\s*\(/', $code)) {
                $offenders[] = basename($path) . ': raw statement';
            }
            // reads of LMS tables are fine (progress rules); a write chain on one is not
            if (preg_match_all('/DB::table\(\s*\'([a-z_]+)\'\s*\)[^;]*?->(insert|insertGetId|insertOrIgnore|update|updateOrInsert|upsert|delete|truncate|increment|decrement)\s*\(/s', $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    if (!str_starts_with($hit[1], 'living_course_')) {
                        $offenders[] = basename($path) . ": {$hit[2]} on {$hit[1]}";
                    }
                }
            }
            if (preg_match('/\\\\?Ulams\\\\(Courses|TopicTypes|TopicTypeGift|Pages)\\\\Models\\\\[A-Za-z\\\\]+::(create|insert|forceCreate|updateOrCreate|firstOrCreate|destroy)\s*\(/', $code)
                || preg_match('/\\\\?Ulams\\\\(Courses|TopicTypes|TopicTypeGift|Pages)\\\\Models\\\\[A-Za-z\\\\]+::query\(\)[^;]*->(update|delete|insert|forceDelete)\s*\(/', $code)) {
                $offenders[] = basename($path) . ': Eloquent write on an LMS model';
            }
        }

        $this->assertSame([], $offenders);
    }

    public function testNoModelNameIsHardcoded(): void
    {
        $offenders = [];
        foreach ($this->sources() as $path => $code) {
            if (preg_match('/[\'"](claude-[a-z0-9.-]+|gpt-[a-z0-9.-]+|gemini-[a-z0-9.-]+)[\'"]/i', $code)) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders);
    }

    public function testNoOutboundRequestBypassesTheSafeClient(): void
    {
        $offenders = [];
        foreach ($this->sources() as $path => $code) {
            if (preg_match('/Http::(get|post|put|withHeaders|withToken|send)\s*\(|curl_init\s*\(|file_get_contents\(\s*\$?(url|uri)|new Client\s*\(/i', $code)) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders);
    }
}
