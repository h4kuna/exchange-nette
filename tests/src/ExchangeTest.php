<?php declare(strict_types = 1);

namespace h4kuna\Exchange\Tests;

use h4kuna\CriticalCache\PSR16\Locking\CacheLock;
use h4kuna\CriticalCache\PSR16\Locking\CacheLockingFactory;
use h4kuna\Exchange\DI\ExchangeExtension;
use h4kuna\Exchange\Exchange;
use h4kuna\Exchange\ExchangeManager;
use h4kuna\Exchange\Filters;
use h4kuna\Format\Number\Formats;
use h4kuna\Format\Number\Percentage;
use Nette\Bridges\ApplicationDI\ApplicationExtension;
use Nette\Bridges\ApplicationDI\LatteExtension;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Bridges\HttpDI\HttpExtension;
use Nette\Bridges\HttpDI\SessionExtension;
use Nette\DI\Compiler;
use Nette\DI\ContainerLoader;
use Nette\Routing\SimpleRouter;
use Tester\Assert;
use function assert;

require_once __DIR__ . '/../bootstrap.php';

$loader = new ContainerLoader(TEMP_DIR, true);
$class = $loader->load(static function (Compiler $compiler): null {
	$compiler->addExtension('exchange', new ExchangeExtension());
	$compiler->addExtension('http', new HttpExtension());
	$compiler->addExtension('latte', new LatteExtension(TEMP_DIR));
	$compiler->addExtension('session', new SessionExtension());
	$compiler->addExtension('application', new ApplicationExtension());

	$compiler->addConfig([
		'exchange' => [
			'tempDir' => TEMP_DIR,
		],
		'services' => [
			SimpleRouter::class,
		],
	]);

	return null;
}, __FILE__);

$container = new $class();

$exchange = $container->getByType(Exchange::class);

Assert::type(Exchange::class, $exchange);

Assert::type(ExchangeManager::class, $container->getService('exchange.exchange.manager'));

Assert::type(Formats::class, $container->getService('exchange.formats'));

Assert::type(CacheLock::class, $container->getService('exchange.cache'));

Assert::type(CacheLockingFactory::class, $container->getService('exchange.cache.locking.factory'));

Assert::type(Exchange::class, $container->getService('exchange.exchange'));

Assert::type(Percentage::class, $container->getService('exchange.vat'));

Assert::type(Filters::class, $container->getService('exchange.filters'));

$latteFactory = $container->getService('latte.latteFactory');
Assert::type(LatteFactory::class, $latteFactory);
assert($latteFactory instanceof LatteFactory);
Assert::same($latteFactory->create()->invokeFilter('currencyTo', [
	30,
	'EUR',
]), $latteFactory->create()->invokeFilter('currency', [30, null, 'EUR']));
