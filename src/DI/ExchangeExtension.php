<?php declare(strict_types=1);

namespace h4kuna\Exchange\DI;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use h4kuna\Exchange;
use h4kuna\Number;
use Nette\DI;
use Nette\Http\Session;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Http\Request;
use Nette\Http\Response;
use NinjaMutex\Lock\FlockLock;
use NinjaMutex\Lock\LockInterface;
use Psr\Http;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * @property-read Config $config
 */
final class ExchangeExtension extends DI\CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		$tempDir = ($this->getContainerBuilder()->parameters['tempDir'] ?? sys_get_temp_dir()) . '/h.exchange';
		!is_dir($tempDir) && mkdir($tempDir, 0777, true);

		$config = new Config;
		$config->tempDir = $tempDir;

		return Expect::from($config);
	}


	public function loadConfiguration(): void
	{
		$this->buildRatingListBuilder();

		$this->buildRatingListCacheBuilder();

		$this->buildDriverAccessor();

		$this->buildConfiguration();

		$this->buildCnb();

		$this->buildEcb();

		$this->buildRatingListAccessor();

		$this->buildExchange();

		$this->buildCache();

		$this->buildFormats();

		$this->buildFilters();

		$this->buildVat();
	}


	public function beforeCompile()
	{
		$this->buildBeforeNumberFormatFactory();
		$this->buildBeforeClient();
		$this->buildBeforeLock();
		$this->buildBeforeRequestFactory();
		$this->buildBeforeExchangeManager();
		$this->addDrivers();

		$builder = $this->getContainerBuilder();
		if ($builder->hasDefinition('application.application') && $builder->hasDefinition($this->prefix('exchange.manager'))) {
			$application = $builder->getDefinition('application.application');
			assert($application instanceof DI\Definitions\ServiceDefinition);
			$application->addSetup(new DI\Definitions\Statement('$service->onPresenter[] = function ($application, $presenter) {?->init($presenter);}', [$this->prefix('@exchange.manager')]));
		}

		if ($builder->hasDefinition('latte.latteFactory')) {
			$this->registerLatteFilters();
		}
	}


	private function buildBeforeExchangeManager(): void
	{
		try {
			$this->getContainerBuilder()->getByType(Request::class, true);
			$this->getContainerBuilder()->getByType(Response::class, true);
			$this->getContainerBuilder()->getByType(Session::class, true);
		} catch (DI\MissingServiceException $e) {
			return;
		}

		$exchangeManager = $this->getContainerBuilder()
			->addDefinition($this->prefix('exchange.manager'))
			->setFactory(Exchange\ExchangeManager::class)
			->setArguments([
				'ratingList' => $this->prefix('@rating.list.accessor'),
				'configuration' => $this->prefix('@configuration'),
			])
			->addSetup('setParameter', [$this->config->managerParameter])
			->setAutowired(false);

		if ($this->config->session) {
			$exchangeManager->addSetup('setSession', [new DI\Definitions\Statement('?->getSection(\'h4kuna.exchange\')', ['@session.session'])]);
		}
	}


	private function buildFormats(): void
	{
		$formats = $this->getContainerBuilder()
			->addDefinition($this->prefix('formats'))
			->setFactory(Exchange\Formats::class, [$this->prefix('@number.format.factory')])
			->setAutowired(false);

		$defaultFormat = $this->config->defaultFormat;
		if ($defaultFormat !== []) {
			$formats->addSetup('setDefaultFormat', [$defaultFormat]);
		}

		foreach ($this->config->currencies as $code => $setup) {
			if (is_array($setup)) {
				$formats->addSetup('addFormat', [$code, $setup]);
			}
		}
	}


	private function buildCache(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('cache'))
			->setFactory(Exchange\Caching\Cache::class, [$this->config->tempDir])
			->setAutowired(false);
	}


	private function buildExchange(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('exchange'))
			->setFactory(Exchange\Exchange::class)
			->setArguments([
				'configuration' => $this->prefix('@configuration'),
				'ratingList' => $this->prefix('@rating.list.accessor'),
			]);
	}


	private function buildFilters(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('filters'))
			->setFactory(Exchange\Filters::class, [
				'exchange' => $this->prefix('@exchange'),
				'formats' => $this->prefix('@formats'),
				'tax' => $this->prefix('@vat'),
			])
			->setAutowired(false);
	}


	private function buildVat(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('vat'))
			->setFactory(Number\Tax::class, [$this->config->vat])
			->setAutowired(false);
	}


	private function buildRatingListAccessor(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('rating.list.accessor'))
			->setFactory(Exchange\RatingList\RatingListRequest::class)
			->setArguments([
				'ratingList' => new DI\Definitions\Statement('?->create(?)', [
					$this->prefix('@rating.list.cache.builder'),
					$this->config->driver,
				]),
			])
			->setAutowired(false);
	}


	private function buildRatingListBuilder(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('rating.list.builder'))
			->setFactory(Exchange\RatingList\RatingListBuilder::class)
			->setArguments([
				'allowedCurrencies' => $this->config->strict ? Exchange\Utils::transformCurrencies(array_keys($this->config->currencies)) : [],
			]);
	}


	private function buildRatingListCacheBuilder(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('rating.list.cache.builder'))
			->setFactory(Exchange\RatingList\RatingListCache::class)
			->setArguments([
				'cache' => $this->prefix('@cache'),
				'lock' => $this->prefix('@lock'),
				'ratingListBuilder' => $this->prefix('@rating.list.builder'),
				'driverAccessor' => $this->prefix('@driver.accessor'),
			]);
	}


	private function buildCnb(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('driver.cnb.day'))
			->setFactory(Exchange\Driver\Cnb\Day::class)
			->setArguments([
				'client' => $this->prefix('@http.client'),
				'requestFactory' => $this->prefix('@http.request.factory'),
			])
			->setAutowired(false);
	}


	private function buildEcb(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('driver.ecb.day'))
			->setFactory(Exchange\Driver\Ecb\Day::class)
			->setArguments([
				'client' => $this->prefix('@http.client'),
				'requestFactory' => $this->prefix('@http.request.factory'),
			])
			->setAutowired(false);
	}


	private function buildDriverAccessor(): void
	{
		$this->getContainerBuilder()
			->addLocatorDefinition($this->prefix('driver.accessor'))
			->setImplement(Exchange\Driver\DriverAccessor::class);
	}


	private function buildConfiguration(): void
	{
		$from = array_key_first($this->config->currencies);
		if ($from === null) {
			throw new Exchange\Exceptions\ExtensionException('Lets\'s define one currency in option currencies.');
		}

		$this->getContainerBuilder()
			->addDefinition($this->prefix('configuration'))
			->setFactory(Exchange\Configuration::class)
			->setArguments([
				'from' => strtoupper($from),
				'to' => strtoupper($from),
			])
			->setAutowired(false);
	}


	private function buildBeforeNumberFormatFactory(): void
	{
		$this->buildAndCheckIfExists('number.format.factory', Number\NumberFormatFactory::class);
	}


	private function buildBeforeClient(): void
	{
		$this->buildAndCheckIfExists('http.client', Client::class, function (): void {
			Exchange\ExchangeFactory::checkGuzzle(Client::class);
		});
	}


	private function buildBeforeLock(): void
	{
		$this->buildAndCheckIfExists('lock', LockInterface::class, function (
			DI\Definitions\ServiceDefinition $serviceDefinition,
		) {
			$serviceDefinition->setFactory(FlockLock::class, [$this->config->tempDir]);
		});
	}


	private function buildAndCheckIfExists(string $serviceName, string $type, callable $factory = null): void
	{
		$builder = $this->getContainerBuilder();

		$serviceName = $this->prefix($serviceName);
		if ($builder->hasDefinition($serviceName)) {
			return;
		}

		$definitions = $builder->findByType($type);
		if ($definitions === []) {
			$definition = $this->getContainerBuilder()
				->addDefinition($serviceName)
				->setFactory($type)
				->setAutowired(false);
			if ($factory !== null) {
				($factory)($definition);
			}
		} else {
			$service = array_key_first($definitions);
			assert(is_string($service));
			$builder->addAlias($serviceName, $service);
		}
	}


	private function buildBeforeRequestFactory(): void
	{
		$this->buildAndCheckIfExists('http.request.factory', RequestFactoryInterface::class, function (
			DI\Definitions\ServiceDefinition $definition,
		) {
			Exchange\ExchangeFactory::checkGuzzle(HttpFactory::class);
			$definition->setFactory(HttpFactory::class);
		});
	}


	private function addDrivers(): void
	{
		$definitions = $this->getContainerBuilder()
			->findByType(Exchange\Driver\Driver::class);

		$drivers = [];
		foreach ($definitions as $definition) {
			$drivers[$definition->getType()] = '@' . $definition->getName();
		}

		$definition = $this->getContainerBuilder()->getDefinition($this->prefix('driver.accessor'));
		assert($definition instanceof DI\Definitions\LocatorDefinition);

		$definition->setReferences($drivers);
	}


	private function registerLatteFilters(): void
	{
		$latte = $this->getContainerBuilder()
			->getDefinition('latte.latteFactory');
		assert($latte instanceof DI\Definitions\FactoryDefinition);
		$latte = $latte->getResultDefinition();

		if ($this->config->filters['currency']) {
			$latte->addSetup('addFilter', [
				$this->config->filters['currency'],
				[$this->prefix('@filters'), 'format'],
			]);
		}
		if ($this->config->filters['currencyTo']) {
			$latte->addSetup('addFilter', [
				$this->config->filters['currencyTo'],
				[$this->prefix('@filters'), 'formatTo'],
			]);
		}
		if ($this->config->filters['vat']) {
			$latte->addSetup('addFilter', [
				$this->config->filters['vat'],
				[$this->prefix('@filters'), 'formatVat'],
			]);
		}
		if ($this->config->filters['vatTo']) {
			$latte->addSetup('addFilter', [
				$this->config->filters['vatTo'],
				[$this->prefix('@filters'), 'formatVatTo'],
			]);
		}
	}

}
