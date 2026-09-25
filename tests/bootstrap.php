<?php declare(strict_types = 1);

use Nette\Utils\FileSystem;
use Tester\Environment;
use Tracy\Debugger;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/fixtures/SourceDownloadMock.php';

define('TEMP_DIR', __DIR__ . '/temp');

FileSystem::createDir(TEMP_DIR);

if (defined('__PHPSTAN_RUNNING__') === false) {
	Environment::setup();
}


Debugger::enable(false, TEMP_DIR);
