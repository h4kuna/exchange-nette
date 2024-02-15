<?php declare(strict_types=1);

namespace h4kuna\Exchange\Tests;

use ArrayIterator;
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


	public function getExpire(): ?DateTimeImmutable
	{
		return null;
	}


	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->currencies);
	}


	public function offsetExists(mixed $offset): bool
	{
		return isset($this->currencies[$offset]);
	}


	public function offsetGet(mixed $offset): mixed
	{
		return $this->currencies[$offset];
	}


	public function offsetSet(mixed $offset, mixed $value): void
	{
		// TODO: Implement offsetSet() method.
	}


	public function offsetUnset(mixed $offset): void
	{
		// TODO: Implement offsetUnset() method.
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
