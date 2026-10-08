<?php

namespace EscolaLms\Files\Tests\Rules;

use EscolaLms\Files\Rules\FileOrStringRule;
use EscolaLms\Files\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileOrStringRuleTest extends TestCase
{
    public function testFileRule(): void
    {
        $rule = new FileOrStringRule(['image']);
        $this->assertTrue($rule->passes('image', UploadedFile::fake()->image('test.jpg')));
        $this->assertFalse($rule->passes('image', UploadedFile::fake()->image('test.mpg')));
    }

    public function testPathRule(): void
    {
        Storage::fake();
        $path = 'images';
        $file = 'test.jpg';
        $fullPath = $path . DIRECTORY_SEPARATOR . $file;
        Storage::makeDirectory($path);
        copy(__DIR__ . '/../mocks/test.jpg', Storage::path($fullPath));
        Storage::assertExists($fullPath);

        $rule = new FileOrStringRule(['image'], $path);
        $this->assertTrue($rule->passes('image', $fullPath));
        $this->assertFalse($rule->passes('image', $path . DIRECTORY_SEPARATOR . 'test2.png'));

        $rule = new FileOrStringRule(['image'], 'pathPrefix');
        $this->assertFalse($rule->passes('image', $fullPath));
    }
}
