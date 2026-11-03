<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// the .env file is not committed (api/.gitignore ignores .env*): the unit tests need nothing from it
if (is_file(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
$_SERVER['APP_ENV'] ??= 'test';
$_SERVER['APP_DEBUG'] ??= '0';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
