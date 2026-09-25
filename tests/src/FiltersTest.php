<?php declare(strict_types = 1);

namespace h4kuna\Exchange\Tests;

use h4kuna\Exchange\DI\ExchangeExtension;
use h4kuna\Exchange\Exchange;
use h4kuna\Exchange\Filters;
use h4kuna\Format\Number\Formats;
use Nette\DI\Compiler;
use Nette\DI\ContainerLoader;
use Tester\Assert;
use function assert;

require __DIR__ . '/../bootstrap.php';

$loader = new ContainerLoader(TEMP_DIR, true);
$class = $loader->load(static function (Compiler $compiler): null {
	$compiler->addExtension('exchange', new ExchangeExtension());

	$compiler->addConfig([
		'exchange' => [
			'tempDir' => TEMP_DIR,
		],
	]);

	$compiler->loadConfig(__DIR__ . '/../fixtures/filter.neon');

	return null;
}, __FILE__);

$container = new $class();

$formats = $container->getService('exchange.formats');
assert($formats instanceof Formats);
$filters = $container->getService('exchange.filters');
assert($filters instanceof Filters);
$exchange = $container->getByType(Exchange::class);

Assert::same('EUR', $exchange->getFrom()->getCode());

Assert::same(80.0, $filters->change(100, 'USD', 'EUR'));

Assert::same(125.0, $filters->changeTo(100, 'USD'));

Assert::same(121.0, $filters->vat(100));
Assert::same('96.80 €', $filters->formatVat(100, 'USD', 'EUR'));
Assert::same('151,25 USD', $filters->formatVatTo(100, 'USD'));
Assert::same('125,00 USD', $filters->formatTo(100, 'USD'));
