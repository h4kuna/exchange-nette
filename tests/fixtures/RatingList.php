<?php declare(strict_types=1);

namespace h4kuna\Exchange\Tests;

use h4kuna\Exchange\Currency\Property;

final class RatingList extends \h4kuna\Exchange\RatingList\RatingList
{
	public function __construct()
	{
		parent::__construct(new \DateTimeImmutable());
		$this->addProperty(new Property(1, 25.0, 'EUR'));
		$this->addProperty(new Property(1, 1.0, 'CZK'));
		$this->addProperty(new Property(1, 20.0, 'USD'));
	}

}
