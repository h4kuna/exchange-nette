<?php declare(strict_types=1);

namespace h4kuna\Exchange\Tests;

use h4kuna;
use h4kuna\Exchange;
use h4kuna\Exchange\DI\ExchangeExtension;
use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$loader = new DI\ContainerLoader(TEMP_DIR, true);
$class = $loader->load(function (DI\Compiler $compiler): void {
	$compiler->addExtension('exchange', new ExchangeExtension());

	$compiler->addConfig([
		'exchange' => [
			'tempDir' => TEMP_DIR,
		],
	]);

	$compiler->loadConfig(__DIR__ . '/../fixtures/filter.neon');
}, __FILE__);

$container = new $class();
assert($container instanceof DI\Container);

$formats = $container->getService('exchange.formats');
assert($formats instanceof h4kuna\Format\Number\Formats);
$filters = $container->getService('exchange.filters');
assert($filters instanceof Exchange\Filters);
$exchange = $container->getByType(Exchange\Exchange::class);
assert($exchange instanceof Exchange\Exchange);

Assert::same('EUR', $exchange->getFrom()->getCode());

Assert::same(80.0, $filters->change(100, 'USD', 'EUR'));

Assert::same(125.0, $filters->changeTo(100, 'USD'));

Assert::same(121.0, $filters->vat(100));
Assert::same('96.80 €', $filters->formatVat(100, 'USD', 'EUR'));
Assert::same('151,25 USD', $filters->formatVatTo(100, 'USD'));
Assert::same('125,00 USD', $filters->formatTo(100, 'USD'));
