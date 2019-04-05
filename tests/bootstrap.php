<?php declare(strict_types=1);

include __DIR__ . '/../vendor/autoload.php';

define('TEMP_DIR', __DIR__ . '/temp');

\Nette\Utils\FileSystem::createDir(TEMP_DIR);

Tester\Environment::setup();

Tracy\Debugger::enable(false, TEMP_DIR);
