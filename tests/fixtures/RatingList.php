<?php declare(strict_types=1);

namespace h4kuna\Exchange\Tests;

use DateTimeImmutable;
use h4kuna\Exchange\Currency\Property;
use h4kuna\Exchange\RatingList\CacheEntity;
use h4kuna\Exchange\RatingList\RatingListInterface;

final class RatingList implements RatingListInterface
{
	/**
	 * @var array<string, Property>
	 */
	private array $currencies = [];


	public function __construct()
	{
		$this->currencies = [
			'EUR' => new Property(1, 25.0, 'EUR'),
			'CZK' => new Property(1, 1.0, 'CZK'),
			'USD' => new Property(1, 20.0, 'USD'),
		];
	}


	public function modify(CacheEntity $cacheEntity): RatingListInterface
	{
		return clone $this;
	}


	public function get(string $code): Property
	{
		return $this->currencies[$code];
	}


	public function all(): array
	{
		return $this->currencies;
	}


	public function getDate(): DateTimeImmutable
	{
		return new DateTimeImmutable();
	}

}
