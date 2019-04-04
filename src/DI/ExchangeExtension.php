<?php declare(strict_types=1);

namespace h4kuna\Exchange\DI;

use h4kuna\Exchange;
use h4kuna\Number;
use Nette\DI as NDI;
use Nette\Schema\Expect;

final class ExchangeExtension extends NDI\CompilerExtension
{

	public function getConfigSchema(): \Nette\Schema\Schema
	{
		return Expect::structure([
			'vat'              => Expect::int(),
			'strict'           => Expect::bool(true),
			'defaultFormat'    => Expect::array(),
			'currencies'       => Expect::array()->default([
				'czk' => ['unit' => 'Kč'],
				'eur' => ['unit' => '€', 'mask' => 'U 1'],
				'usd' => ['unit' => '$', 'mask' => 'U1']
			]),
			'tempDir'          => Expect::string()->default($this->getContainerBuilder()->parameters['tempDir']
				. DIRECTORY_SEPARATOR . 'currencies'),
			'session'          => Expect::bool(false),
			'managerParameter' => Expect::string('currency'),
			'filters'          => Expect::array()->default([
				'currency' => 'currency',
				'vat'      => 'vat'
			])
		]);
	}

	public function loadConfiguration()
	{
		$config = $this->config;
		$builder = $this->getContainerBuilder();

		$this->buildExchangeManager($builder, $config->managerParameter, $config->session);
		$nff = $this->buildNumberFormatFactory($builder);

		[$currencies, $formats] = $this->buildFormats($builder, $nff, $config->defaultFormat, $config->currencies);

		$cache = $this->buildCache($builder, $config->strict ? $currencies : [], $config->tempDir);

		$exchange = $this->buildExchange($builder, $cache);

		$filters = $this->buildFilters($builder, $exchange, $formats);

		$this->buildVat($builder, $filters, (float) $config->vat);
	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();

		$definitions = $builder->findByType(Number\NumberFormatFactory::class);
		unset($definitions[$this->prefix('numberFormatFactory')]);
		if ($definitions !== []) {
			$definition = key($definitions);
			$builder->getDefinition($this->prefix('numberFormatFactory'))
				->setFactory('@' . $definition);
		}

		if ($builder->hasDefinition('application.application')) {
			$application = $builder->getDefinition('application.application');
			$application->addSetup(new NDI\Statement('$service->onPresenter[] = function($application, $presenter) {?->init($presenter);}', [$this->prefix('@exchangeManager')]));
		}

		if ($builder->hasDefinition('latte.latteFactory')) {
			$latte = $builder->getDefinition('latte.latteFactory')
				->getResultDefinition()->addSetup('addFilter', [$this->config->filters['currency'], [$this->prefix('@filters'), 'format']]);
			if ($this->config->vat) {
				$latte->addSetup('addFilter', [
					$this->config['filters']['vat'],
					[$this->prefix('@filters'), 'formatVat']
				]);
			}
		}
	}


	private function buildExchangeManager(NDI\ContainerBuilder $builder, string $parameter, bool $session): void
	{
		$exchangeManager = $builder->addDefinition($this->prefix('exchangeManager'))
			->setFactory(Exchange\ExchangeManager::class)
			->addSetup('setParameter', [$parameter])
			->setAutowired(false);

		if ($session) {
			$exchangeManager->addSetup('setSession', [new NDI\Statement('?->getSection(\'h4kuna.exchange\')', ['@session.session'])]);
		}
	}


	private function buildNumberFormatFactory(NDI\ContainerBuilder $builder): NDI\ServiceDefinition
	{
		return $builder->addDefinition($this->prefix('numberFormatFactory'))
			->setFactory(Number\NumberFormatFactory::class)
			->setAutowired(false);
	}


	private function buildFormats(NDI\ContainerBuilder $builder, NDI\ServiceDefinition $numberFormatFactory, array $defaultFormat, array $currencies): array
	{
		$formats = $builder->addDefinition($this->prefix('formats'))
			->setFactory(Exchange\Currency\Formats::class, [$numberFormatFactory])
			->setAutowired(false);

		if ($defaultFormat !== []) {
			$formats->addSetup('setDefaultFormat', [$defaultFormat]);
		}

		$allowedCurrencies = [];
		foreach ($currencies as $code => $setup) {
			$code = strtoupper($code);
			$allowedCurrencies[] = $code;
			if ($setup) {
				$formats->addSetup('addFormat', [$code, $setup]);
			}
		}
		return [$allowedCurrencies, $formats];
	}


	private function buildCache(NDI\ContainerBuilder $builder, array $allowedCurrencies, string $tempDir): NDI\ServiceDefinition
	{
		$cache = $builder->addDefinition($this->prefix('cache'))
			->setFactory(Exchange\Caching\Cache::class, [$tempDir])
			->setAutowired(false);

		if ($allowedCurrencies) {
			$cache->addSetup('setAllowedCurrencies', [$allowedCurrencies]);
		}
		return $cache;
	}


	private function buildExchange(NDI\ContainerBuilder $builder, NDI\ServiceDefinition $cache): NDI\ServiceDefinition
	{
		return $builder->addDefinition($this->prefix('exchange'))
			->setFactory(Exchange\Exchange::class, [$cache]);
	}


	private function buildFilters(NDI\ContainerBuilder $builder, NDI\ServiceDefinition $exchange, NDI\ServiceDefinition $formats): NDI\ServiceDefinition
	{
		return $builder->addDefinition($this->prefix('filters'))
			->setFactory(Exchange\Filters::class, [$exchange, $formats])
			->setAutowired(false);
	}


	private function buildVat(NDI\ContainerBuilder $builder, NDI\ServiceDefinition $filters, float $vat): void
	{
		if ($vat === 0.0) {
			return;
		}
		$vatDefinition = $builder->addDefinition($this->prefix('vat'))
			->setFactory(Number\Tax::class, [$vat]);
		$filters->addSetup('setVat', [$vatDefinition]);
	}

}
