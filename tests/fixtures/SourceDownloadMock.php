<?php declare(strict_types=1);

namespace h4kuna\Exchange\Tests;

use DateTimeInterface;
use h4kuna\Exchange\Currency\Property;
use h4kuna\Exchange\Download\SourceDownloadInterface;
use h4kuna\Exchange\Driver\Source;
use h4kuna\Exchange\RatingList\RatingList;

final class SourceDownloadMock implements SourceDownloadInterface
{
	public function execute(
		Source $sourceExchange,
		?DateTimeInterface $date
	): RatingList
	{
		$list = [
			'EUR' => new Property(1, 25.0, 'EUR'),
			'CZK' => new Property(1, 1.0, 'CZK'),
			'USD' => new Property(1, 20.0, 'USD'),
		];

		return new RatingList(new \DateTimeImmutable(), null, null, $list);
	}

}
