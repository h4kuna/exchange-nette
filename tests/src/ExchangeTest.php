<?php declare(strict_types=1);

namespace h4kuna\Exchange\Tests;

use h4kuna\CriticalCache\Cache;
use h4kuna\CriticalCache\CacheFactory;
use h4kuna\Exchange\DI\ExchangeExtension;
use h4kuna\Exchange\Exchange;
use h4kuna\Exchange\ExchangeManager;
use h4kuna\Exchange\Filters;
use h4kuna\Format\Number\Formats;
use h4kuna\Format\Number\Tax;
use Nette\Bridges\ApplicationDI\ApplicationExtension;
use Nette\Bridges\ApplicationDI\LatteExtension;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Bridges\HttpDI;
use Nette\DI;
use Nette\Routing\SimpleRouter;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

$loader = new DI\ContainerLoader(TEMP_DIR, true);
$class = $loader->load(function (DI\Compiler $compiler): void {
	$compiler->addExtension('exchange', new ExchangeExtension());
	$compiler->addExtension('http', new HttpDI\HttpExtension());
	$compiler->addExtension('latte', new LatteExtension(TEMP_DIR));
	$compiler->addExtension('session', new HttpDI\SessionExtension());
	$compiler->addExtension('application', new ApplicationExtension());

	$compiler->addConfig([
		'exchange' => [
			'tempDir' => TEMP_DIR,
		],
		'services' => [
			SimpleRouter::class,
		],
	],
	);
}, __FILE__);

$container = new $class();
assert($container instanceof DI\Container);

$exchange = $container->getByType(Exchange::class);

Assert::type(Exchange::class, $exchange);

Assert::type(ExchangeManager::class, $container->getService('exchange.exchange.manager'));

Assert::type(Formats::class, $container->getService('exchange.formats'));

Assert::type(Cache::class, $container->getService('exchange.cache'));

Assert::type(CacheFactory::class, $container->getService('exchange.cache.factory'));

Assert::type(Exchange::class, $container->getService('exchange.exchange'));

Assert::type(Tax::class, $container->getService('exchange.vat'));

Assert::type(Filters::class, $container->getService('exchange.filters'));

$latteFactory = $container->getService('latte.latteFactory');
Assert::type(LatteFactory::class, $latteFactory);
assert($latteFactory instanceof LatteFactory);
Assert::same($latteFactory->create()->invokeFilter('currencyTo', [
	30,
	'EUR',
]), $latteFactory->create()->invokeFilter('currency', [30, null, 'EUR']));
