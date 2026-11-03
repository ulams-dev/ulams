<?php

namespace Ulams\TopicTypes\Tests\Models;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Ulams\TopicTypes\Models\TopicContent\Image;
use Ulams\TopicTypes\Tests\TestCase;

class ImageDimensionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function testTheSizeIsReadThroughTheDiskAndNeverOverItsPublicUrl(): void
    {
        // a public URL that cannot be reached (storage.localhost inside a container resolves to the container itself)
        Config::set('filesystems.disks.local.url', 'http://storage.invalid/ulams');
        Storage::put('course/1/topic/1/image.png', $this->png(40, 30));

        $image = new Image(['value' => 'course/1/topic/1/image.png']);
        $image->processMetadataInfo();

        $this->assertSame(40, $image->width);
        $this->assertSame(30, $image->height);
    }

    public function testAMissingOrUnreadableFileLeavesTheSizeUnset(): void
    {
        Storage::put('notes.txt', 'not an image');

        $missing = new Image(['value' => 'course/1/topic/1/missing.png']);
        $missing->processMetadataInfo();
        $text = new Image(['value' => 'notes.txt']);
        $text->processMetadataInfo();

        $this->assertNull($missing->width);
        $this->assertNull($text->width);
    }
}
