<?php

namespace EscolaLms\Settings\Tests;

use EscolaLms\Settings\Tests\TestCase;
use EscolaLms\Settings\Repositories\Contracts\SettingsRepositoryContract;
use EscolaLms\Settings\Models\Setting;
use EscolaLms\Settings\Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

class RepositoryTest extends TestCase
{
    public function test_search_repository()
    {
        $this->seed(DatabaseSeeder::class);

        $repository = app()->make(SettingsRepositoryContract::class);
        $settings = $repository->allQuery(['group' => 'currencies'])->get();
        $this->assertGreaterThan(0, $settings->count());
    }

    public function test_model_cast()
    {

        $this->seed(DatabaseSeeder::class);
        $setting = Setting::first();

        $setting->update([
            'type' => 'file',
            'value' => 'format_c',
        ]);


        $this->assertEquals($setting->data, Storage::url('format_c'));

        $arr = ['a', 'b', 'c'];

        $setting->update([
            'type' => 'json',
            'value' => json_encode($arr),
        ]);

        $this->assertEquals($setting->data, $arr);
    }

    /**
     * @dataProvider settingDataProvider
     */
    public function test_setting_data_cast(string $type, $value, $expected)
    {
        Storage::shouldReceive('url')
            ->andReturnUsing(fn($path) => 'http://example.storage/' . ltrim($path, '/'));

        $setting = Setting::create([
                'group' => 'example',
                'key' => 'test_key_' . uniqid(),
                'type' => $type,
                'value' => $value,
            ]);

        $this->assertEquals($expected, $setting->data);
    }

    public static function settingDataProvider(): array
    {
        return [
            'file' => [
                'type' => 'file',
                'value' => 'example.jpg',
                'expected' => 'http://example.storage/example.jpg',
            ],
            'image' => [
                'type' => 'image',
                'value' => '//image.jpg',
                'expected' => 'http://example.storage/image.jpg',
            ],
            'image as url' => [
                'type' => 'image',
                'value' => 'http://example.com/image.jpg',
                'expected' => 'http://example.com/image.jpg',
            ],
            'boolean true' => [
                'type' => 'boolean',
                'value' => 'true',
                'expected' => true,
            ],
            'boolean false' => [
                'type' => 'boolean',
                'value' => 'false',
                'expected' => false,
            ],
            'number' => [
                'type' => 'number',
                'value' => '42.5',
                'expected' => 42.5,
            ],
            'default' => [
                'type' => 'text',
                'value' => 'hello wellms',
                'expected' => 'hello wellms',
            ],
        ];
    }
}
