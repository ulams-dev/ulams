<?php

namespace Tests\Feature;

use EscolaLms\Settings\ConfigRewriter\FileRewriter;
use EscolaLms\Settings\Facades\AdministrableConfig;
use EscolaLms\Settings\Models\Config as ModelsConfig;
use EscolaLms\Settings\Services\AdministrableConfigService;
use EscolaLms\Settings\Tests\TestCase;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

class ConfigServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        Cache::forever(AdministrableConfigService::CACHE_KEY, []);
    }

    public function test_register_and_update_config()
    {
        Config::set('test_config_file.test_key', 'test_value');
        Config::set('test_config_file.test_key2', 'test_value');

        AdministrableConfig::registerConfig('test_config_file.test_key', ['required', 'string'], false, true);
        AdministrableConfig::registerConfig('test_config_file.test_key2', ['required', 'string'], true, false);

        $config = AdministrableConfig::getConfig();
        $this->assertEquals('test_value', $config['test_config_file']['test_key']['value']);
        $this->assertEquals('test_value', $config['test_config_file']['test_key2']['value']);

        AdministrableConfig::setConfig([
            'test_config_file.test_key' => 'foobar',
            'test_config_file.test_key2' => 'foobar'
        ]);

        $config = AdministrableConfig::getConfig();
        $this->assertEquals('test_value', $config['test_config_file']['test_key']['value']);
        $this->assertEquals(false, $config['test_config_file']['test_key']['public']);
        $this->assertEquals(true, $config['test_config_file']['test_key']['readonly']);
        $this->assertEquals('foobar', $config['test_config_file']['test_key2']['value']);
        $this->assertEquals(true, $config['test_config_file']['test_key2']['public']);
        $this->assertEquals(false, $config['test_config_file']['test_key2']['readonly']);

        $publicConfig = AdministrableConfig::getPublicConfig();
        $this->assertEquals('foobar', $publicConfig['test_config_file']['test_key2']);
        $this->assertArrayNotHasKey('test_config_file.test_key', $publicConfig);

        try {
            AdministrableConfig::setConfig([
                'test_config_file.test_key2' => false
            ]);
        } catch (ValidationException $ex) {
            // Before Validator is executed keys have dots '.' replaced with __ because '.' is restricted character for array validation rules
            $this->assertArrayHasKey('test_config_file__test_key2', $ex->errors());
        }
    }

    /**
     * Commented out lines make ConfigRewriter & FileRewriter explode
     */
    public function test_store_to_files()
    {
        $path = App::configPath('test_config_file.php');

        if (file_exists($path)) {
            unlink($path);
            Config::set('test_config_file', []);
        }

        Config::set('escola_settings.use_database', false);

        Config::set('test_config_file.test_key', 'test_value');
        Config::set('test_config_file.test_key2', 'test_value');
        Config::set('test_config_file.test_key3', 'test"value');
        //Config::set('test_config_file.test_key4', "test'value");
        //Config::set('test_config_file.test_key5', 'test"value' . "test'value");
        Config::set('test_config_file.test_key_bool', false);
        Config::set('test_config_file.test_key_array', [21, 37, 69, 420]);
        Config::set('test_config_file.test_key_array2', ["foo'bar", 'bar"foo']);
        //Config::set('test_config_file.test_key_array3', [['id' => 1], ['id' => 2]]);
        Config::set('test_config_file.test_key_object.id', 1);
        Config::set('test_config_file.test_key_object.tags', ['foo', 'bar']);
        Config::set('test_config_file.test_key_null', null);
        Config::set('test_config_file.test_key_integer', 1337);

        AdministrableConfig::registerConfig('test_config_file.test_key', ['required', 'string'], false, true);
        AdministrableConfig::registerConfig('test_config_file.test_key2', ['required', 'string'], false);
        AdministrableConfig::registerConfig('test_config_file.test_key3', ['required', 'string']);
        //AdministrableConfig::registerConfig('test_config_file.test_key4', ['required', 'string']);
        //AdministrableConfig::registerConfig('test_config_file.test_key5', ['required', 'string']);
        AdministrableConfig::registerConfig('test_config_file.test_key_bool', ['required', 'boolean']);
        AdministrableConfig::registerConfig('test_config_file.test_key_array', ['required', 'array']);
        AdministrableConfig::registerConfig('test_config_file.test_key_array2', ['required', 'array']);
        //AdministrableConfig::registerConfig('test_config_file.test_key_array3', ['required', 'array']);
        AdministrableConfig::registerConfig('test_config_file.test_key_object.id', ['required', 'integer']);
        AdministrableConfig::registerConfig('test_config_file.test_key_object.tags', ['required', 'array']);
        AdministrableConfig::registerConfig('test_config_file.test_key_null', ['required', 'nullable', 'integer']);
        AdministrableConfig::registerConfig('test_config_file.test_key_integer', ['required', 'integer']);

        AdministrableConfig::setConfig([
            'test_config_file.test_key' => 'foobar',
            'test_config_file.test_key2' => 'foobar'
        ]);
        AdministrableConfig::storeConfig();

        $this->assertTrue(file_exists($path));

        $file_content = file_get_contents($path);
        $vars = eval('?>' . $file_content);
        $this->assertEquals('test_value', $vars['test_key']);
        $this->assertEquals('foobar', $vars['test_key2']);
        $this->assertEquals('test"value', $vars['test_key3']);
        $this->assertEquals(false, $vars['test_key_bool']);
        $this->assertEquals([21, 37, 69, 420], $vars['test_key_array']);
        $this->assertEquals(["foo'bar", 'bar"foo'], $vars['test_key_array2']);
        $this->assertEquals(['id' => 1, 'tags' => ['foo', 'bar']], $vars['test_key_object']);
        $this->assertEquals(null, $vars['test_key_null']);
        $this->assertEquals(1337, $vars['test_key_integer']);

        AdministrableConfig::setConfig([
            'test_config_file.test_key' => 'foobar2',
            'test_config_file.test_key2' => 'foobar2'
        ]);
        AdministrableConfig::storeConfig();

        $file_content = file_get_contents($path);
        $vars = eval('?>' . $file_content);
        $this->assertEquals('test_value', $vars['test_key']);
        $this->assertEquals('foobar2', $vars['test_key2']);
        $this->assertEquals('test"value', $vars['test_key3']);
        $this->assertEquals(false, $vars['test_key_bool']);
        $this->assertEquals([21, 37, 69, 420], $vars['test_key_array']);
        $this->assertEquals(["foo'bar", 'bar"foo'], $vars['test_key_array2']);
        $this->assertEquals(['id' => 1, 'tags' => ['foo', 'bar']], $vars['test_key_object']);
        $this->assertEquals(null, $vars['test_key_null']);
        $this->assertEquals(1337, $vars['test_key_integer']);
    }

    public function test_store_to_database()
    {
        Config::set('escola_settings.use_database', true);

        Config::set('test_config_file.test_key', 'test_value');
        Config::set('test_config_file.test_key2', 'test_value');

        AdministrableConfig::registerConfig('test_config_file.test_key', ['required', 'string'], false, true);
        AdministrableConfig::registerConfig('test_config_file.test_key2', ['required', 'string'], false);
        AdministrableConfig::setConfig([
            'test_config_file.test_key' => 'foobar',
            'test_config_file.test_key2' => 'foobar'
        ]);
        AdministrableConfig::storeConfig();

        $model = ModelsConfig::find(1);
        $this->assertNotNull($model);
        $this->assertEquals('foobar', $model->value['test_config_file.test_key2']);
        $this->assertArrayNotHasKey('test_config_file.test_key', $model->value);
    }

    public function test_load_from_database()
    {
        Config::set('escola_settings.use_database', true);

        Config::set('test_config_file.test_key', 'test_value');
        AdministrableConfig::registerConfig('test_config_file.test_key', ['required', 'string']);

        $config = AdministrableConfig::getPublicConfig();
        $this->assertEquals('test_value', $config['test_config_file']['test_key']);
        $this->assertEquals('test_value', Config::get('test_config_file.test_key'));

        $model = ModelsConfig::create(['id' => 1, 'value' => ['test_config_file.test_key' => 'foobar']]);

        $this->assertTrue(AdministrableConfig::loadConfigFromDatabase(true));

        $config = AdministrableConfig::getPublicConfig();
        $this->assertEquals('foobar', $config['test_config_file']['test_key']);
        $this->assertEquals('foobar', Config::get('test_config_file.test_key'));
    }

    public function test_load_from_cache()
    {
        Config::set('escola_settings.use_database', true);

        Config::set('test_config_file.test_key', 'test_value');
        AdministrableConfig::registerConfig('test_config_file.test_key', ['required', 'string']);

        $config = AdministrableConfig::getPublicConfig();
        $this->assertEquals('test_value', $config['test_config_file']['test_key']);
        $this->assertEquals('test_value', Config::get('test_config_file.test_key'));

        Cache::forever(AdministrableConfigService::CACHE_KEY, ['test_config_file.test_key' => 'foobar']);

        $this->assertTrue(AdministrableConfig::loadConfigFromCache(true));

        $config = AdministrableConfig::getPublicConfig();
        $this->assertEquals('foobar', $config['test_config_file']['test_key']);
        $this->assertEquals('foobar', Config::get('test_config_file.test_key'));
    }

    public function test_load_from_database_fails_if_not_enabled_or_forced()
    {
        Config::set('escola_settings.use_database', false);

        Config::set('test_config_file.test_key', 'test_value');
        AdministrableConfig::registerConfig('test_config_file.test_key', ['required', 'string']);

        $config = AdministrableConfig::getPublicConfig();
        $this->assertEquals('test_value', $config['test_config_file']['test_key']);
        $this->assertEquals('test_value', Config::get('test_config_file.test_key'));

        $model = ModelsConfig::create(['id' => 1, 'value' => ['test_config_file.test_key' => 'foobar']]);

        $this->assertFalse(AdministrableConfig::loadConfigFromDatabase());

        $config = AdministrableConfig::getPublicConfig();
        $this->assertEquals('test_value', $config['test_config_file']['test_key']);
        $this->assertEquals('test_value', Config::get('test_config_file.test_key'));
    }

    public function test_get_single_config_key()
    {
        Config::set('test_config_file.test_key', 'test_value');
        AdministrableConfig::registerConfig('test_config_file.test_key', ['required', 'string']);
        $config = AdministrableConfig::getConfig('test_config_file.test_key');
        $this->assertEquals('test_value', $config['value']);
        $this->assertEquals(true, $config['public']);
        $this->assertEquals(false, $config['readonly']);

        $this->assertEmpty(AdministrableConfig::getConfig('test_config_file.key_does_not_exist'));
    }

    public function test_config_rewriter_exception_on_missing_key()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Config file 'escola_settings' does not exist or doesn't have key 'test_key'");
        Config::write('escola_settings.test_key', 'test_value');
    }

    public function test_file_rewriter_exception_on_missing_key()
    {
        $this->expectException(Exception::class);
        $fr = new FileRewriter();
        $fr->toContent("<?php ['bar'=>'foo'];", ["foo" => "bar"]);
    }
}
