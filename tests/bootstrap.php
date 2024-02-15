<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/fixtures/SourceDownloadMock.php';

define('TEMP_DIR', __DIR__ . '/temp');

Nette\Utils\FileSystem::createDir(TEMP_DIR);

if (defined('__PHPSTAN_RUNNING__') === false) {
	Tester\Environment::setup();
}


Tracy\Debugger::enable(false, TEMP_DIR);
