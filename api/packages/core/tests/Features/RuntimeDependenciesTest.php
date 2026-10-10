<?php

namespace Ulams\Core\Tests\Features;

use PHPUnit\Framework\TestCase;

/**
 * Production, MyDevil and the demo stack run `composer install --no-dev`: runtime code must not use model
 * factories or any require-dev package (scripts/check-runtime-deps.php; CI also boots a no-dev container).
 */
class RuntimeDependenciesTest extends TestCase
{
    private function run_guard(string $root): array
    {
        $script = escapeshellarg(dirname(__DIR__, 4) . '/scripts/check-runtime-deps.php');
        exec('RUNTIME_DEPS_ROOT=' . escapeshellarg($root) . ' ' . escapeshellarg(PHP_BINARY) . " $script 2>&1", $out, $code);

        return [$code, implode("\n", $out)];
    }

    public function test_runtime_code_has_no_dev_only_dependencies(): void
    {
        [$code, $output] = $this->run_guard(dirname(__DIR__, 4));

        $this->assertSame(0, $code, $output);
    }

    public function test_the_guard_flags_factories_and_dev_packages_in_src(): void
    {
        $root = sys_get_temp_dir() . '/runtime-deps-' . uniqid();
        mkdir("$root/packages/demo/src", 0777, true);
        file_put_contents("$root/composer.json", json_encode(['require' => ['fakerphp/faker' => '^1']]));
        file_put_contents("$root/packages/demo/src/Bad.php", <<<'PHP'
            <?php
            class Bad {
                public function make() { return Page::factory()->newModel([]); }
                public function mock() { return \Mockery::mock('x'); }
                protected static function newFactory() { return \Demo\Database\Factories\BadFactory::new(); }
            }
            PHP);

        [$code, $output] = $this->run_guard($root);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Bad.php:3 calls Model::factory()', $output);
        $this->assertStringContainsString('Bad.php:4 references Mockery\\', $output);
        $this->assertStringNotContainsString('Bad.php:5', $output, 'newFactory() hooks are lazy and allowed');
    }
}
