<?php declare(strict_types=1);

namespace h4kuna\Exchange\DI;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use h4kuna\CriticalCache\PSR16\CacheLockingFactoryInterface;
use h4kuna\CriticalCache\PSR16\Locking\CacheLockingFactory;
use h4kuna\Dir\Dir;
use h4kuna\Dir\TempDir;
use h4kuna\Exchange;
use h4kuna\Format;
use Nette\DI;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Psr\Http;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * @property-read Config $config
 */
final class ExchangeExtension extends DI\CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		$path = $this->getContainerBuilder()->parameters['tempDir'] ?? '';
		assert(is_string($path));

		$tempDir = new TempDir($path);

		$config = new Config;
		$config->tempDir = $tempDir->create()->getDir();

		return Expect::from($config);
	}


	public function loadConfiguration(): void
	{
		$this->buildRatingListCache();

		$this->buildExchange();

		$this->buildFormats();

		$this->buildFilters();

		$this->buildVat();
	}


	private function buildRatingListCache(): void
	{
		$this->buildCache();
		$this->buildSourceDownload();

		$this->getContainerBuilder()
			->addDefinition($this->prefix('rating.list.cache'))
			->setFactory(Exchange\RatingList\RatingListCache::class)
			->setArguments([
				'cache' => $this->prefix('@cache'),
				'sourceDownload' => $this->prefix('@source.download'),
			]);
	}


	private function buildCache(): void
	{
		$this->buildCacheFactory();

		$this->getContainerBuilder()
			->addDefinition($this->prefix('cache'))
			->setFactory([$this->prefix('@cache.locking.factory'), 'create'])
			->setAutowired(false);
	}


	private function buildCacheFactory(): void
	{
		$this->getContainerBuilder()
			->addDefinition($this->prefix('cache.locking.factory'))
			->setType(CacheLockingFactoryInterface::class)
			->setCreator(CacheLockingFactory::class, [new DI\Definitions\Statement(Dir::class, [$this->config->tempDir])])
			->setAutowired(false);
	}


	private function buildSourceDownload(): void
	{
		$allowed = $this->config->strict ? Exchange\Utils::transformCurrencies(array_keys($this->config->currencies)) : [];
		$this->getContainerBuilder()
			->addDefinition($this->prefix('source.download'))
			->setType(Exchange\Download\SourceDownloadInterface::class)
			->setFactory(Exchange\Download\SourceDownload::class)
			->setArguments([
				'client' => $this->prefix('@http.client'),
				'requestFactory' => $this->prefix('@http.request.factory'),
				'allowedCurrencies' => $allowed,
			]);
	}


	private function buildExchange(): void
	{
		$from = array_key_first($this->config->currencies);

		$this->getContainerBuilder()
			->addDefinition($this->prefix('exchange'))
			->setFactory(Exchange\Exchange::class)
			->setArguments([
				'from' => $from,
				'ratingList' => new DI\Definitions\Statement([
					$this->prefix('@rating.list.cache'),
					'build',
				], [
					new DI\Definitions\Statement(Exchange\RatingList\CacheEntity::class, [
						null,
						new DI\Definitions\Statement($this->config->driver),
					]),
				]),
			]);
	}


	private function buildFormats(): void
	{
		$formatsData = [];
		foreach ($this->config->currencies as $code => $entity) {
			$formatsData[strtoupper($code)] = new DI\Definitions\Statement(Format\Number\Formatters\NumberFormatter::class, $entity);
		}

		$formats = $this->getContainerBuilder()
			->addDefinition($this->prefix('formats'))
			->setFactory(Format\Number\Formats::class, [$formatsData])
			->setAutowired(false);

		$defaultFormat = $this->config->defaultFormat;
		if ($defaultFormat !== []) {
			$formats->addSetup('setDefault', [$defaultFormat]);
		}
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
			->setFactory(Format\Number\Percentage::class, [$this->config->vat])
			->setAutowired(false);
	}


	public function beforeCompile()
	{
		$this->buildBeforeClient();
		$this->buildBeforeRequestFactory();
		$this->buildBeforeExchangeManager();

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


	private function buildBeforeClient(): void
	{
		$this->buildAndCheckIfExists('http.client', Client::class, function (): void {
			Exchange\Exceptions\MissingDependencyException::guzzleClient();
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
			Exchange\Exceptions\MissingDependencyException::guzzleFactory();
			$definition->setFactory(HttpFactory::class);
		});
	}


	private function buildBeforeExchangeManager(): void
	{
		$builder = $this->getContainerBuilder();
		try {
			$builder->getByType(Request::class, true);
			$builder->getByType(Response::class, true);
			$builder->getByType(Session::class, true);
		} catch (DI\MissingServiceException $e) {
			return;
		}

		$exchangeManager = $builder
			->addDefinition($this->prefix('exchange.manager'))
			->setFactory(Exchange\ExchangeManager::class)
			->addSetup('setParameter', [$this->config->managerParameter])
			->setAutowired(false);

		if ($this->config->session) {
			$exchangeManager->addSetup('setSession', [new DI\Definitions\Statement('?->getSection(\'h4kuna.exchange\')', ['@session.session'])]);
		}
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
