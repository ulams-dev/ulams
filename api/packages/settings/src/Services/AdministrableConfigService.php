<?php

namespace EscolaLms\Settings\Services;

use EscolaLms\Settings\Models\Config as ModelsConfig;
use EscolaLms\Settings\Services\Contracts\AdministrableConfigServiceContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdministrableConfigService implements AdministrableConfigServiceContract
{
    const CACHE_KEY = 'escolalms_config_cache';

    private array $administrableConfig = [];

    public function registerConfig(string $key, array $rules = [], bool $public = true, bool $readonly = false): bool
    {
        $this->administrableConfig[$key] = [
            'full_key' => $key,
            'key' => Str::after($key, '.'),
            'rules' => $rules,
            'public' => $public,
            'readonly' => $readonly,
        ];
        return true;
    }

    public function storeConfig(): bool
    {
        $this->storeConfigInCache();

        if (Config::get('escola_settings.use_database')) {
            return $this->saveConfigToDatabase();
        }

        return $this->saveConfigToFiles();
    }

    /**
     * This is a naive implementation of saving config files, because it strips all comments and evalues things like env() calls to current value.
     * We probably should create a parser/writer that only overwrites variables which we want it to, and leaves everything else intact, but this has to be enough for now.
     */
    private function saveConfigToFiles(): bool
    {
        $keys = $this->getNotReadonlyKeys();
        $config = $this->mapKeysToConfigValues($keys);

        foreach ($config as $key => $value) {
            $path = App::configPath(Str::before($key, '.') . '.php');
            if (file_exists($path)) {
                Config::write($key, $value);
                unset($config[$key]);
            }
        }

        // this is needed if config file was not published, or in case of tests - if we are testing dynamicaly created config keys not based on existing config file
        $config_undotted = $this->undot($config);
        foreach ($config_undotted as $master_key => $value) {
            $path = App::configPath($master_key . '.php');
            $config_array = Config::get($master_key);
            $config_file_content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($config_array, true) . ';';
            file_put_contents($path, $config_file_content);
        }

        return true;
    }

    private function saveConfigToDatabase(): bool
    {
        $configModel = ModelsConfig::query()->updateOrCreate(['id' => 1], ['value' => $this->getWritableConfig()]);

        return $configModel->exists;
    }

    public function loadConfigFromCache(bool $forced = false): bool
    {
        if (Config::get('escola_settings.use_database', false) || $forced) {
            $cache = $this->getConfigFromCache();
            if (!empty($cache)) {
                Config::set($cache);
                return true;
            }
        }
        return false;
    }

    public function loadConfigFromDatabase(bool $forced = false): bool
    {
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            return false;
        }
        if ((Config::get('escola_settings.use_database', false) || $forced) && Schema::hasTable('config')) {
            $configModel = ModelsConfig::query()->find(1);
            if (!is_null($configModel)) {
                $config = $configModel->value;
                foreach ($config as $key => $value) {
                    if (array_key_exists($key, $this->administrableConfig) && !$this->administrableConfig[$key]['readonly']) {
                        Config::set($key, $value);
                    }
                }
                return true;
            }
        }

        return false;
    }

    public function setConfig(array $config): void
    {
        $data = [];
        $rules = [];
        foreach ($config as $key => $value) {
            if (is_numeric($key) && is_array($value) && Arr::has($value, ['key', 'value'])) {
                // $config has structure of [['key' => 'foo', 'value' => 'bar'], ['key' => 'foo2', 'value' => 'bar2']] instead of more straightforward ['foo' => 'bar', 'foo2' => 'bar2']
                $key = $value['key'];
                $value = $value['value'];
            }
            if (array_key_exists($key, $this->administrableConfig) && !$this->administrableConfig[$key]['readonly']) {
                $key_without_dot = str_replace('.', '__', $key);
                $data[$key_without_dot] = $value;
                $rules[$key_without_dot] = $this->administrableConfig[$key]['rules'];
            }
        }
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        foreach ($validator->validated() as $key => $value) {
            $key_with_dot = str_replace('__', '.', $key);
            Config::set($key_with_dot, $value);
        }

        $this->storeConfigInCache();
    }

    public function getConfig(string $key = null): array
    {
        if (empty($key)) {
            $result = [];
            foreach ($this->administrableConfig as $index => $value) {
                $result[$index] = array_merge($value, ['value' => Config::get($index)]);
            }
            return $this->undot($result);
        }

        if (!array_key_exists($key, $this->administrableConfig)) {
            return [];
        }

        return $this->undot(array_merge($this->administrableConfig[$key], ['value' => Config::get($key)]));
    }

    private function getConfigFromCache(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    private function storeConfigInCache(): void
    {
        Cache::forever(self::CACHE_KEY, $this->getWritableConfig());
    }

    public function getPublicConfig(): array
    {
        return $this->undot($this->mapKeysToConfigValues($this->getPublicKeys()));
    }

    private function getPublicKeys(): array
    {
        return array_keys(array_filter($this->administrableConfig, fn ($config) => $config['public']));
    }

    private function getWritableConfig(): array
    {
        return $this->mapKeysToConfigValues($this->getNotReadonlyKeys());
    }

    private function getNotReadonlyKeys(): array
    {
        return array_keys(array_filter($this->administrableConfig, fn ($config) => !$config['readonly']));
    }

    private function mapKeysToConfigValues(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = Config::get($key);
        }
        return $result;
    }

    private function undot(array $dotNotationArray)
    {
        $array = [];
        foreach ($dotNotationArray as $key => $value) {
            Arr::set($array, $key, $value);
        }
        return $array;
    }
}
