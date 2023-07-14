<?php declare(strict_types=1);

namespace h4kuna\Exchange\Tests\Currency;

use h4kuna\Exchange;
use h4kuna\Number;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

$formats = new Exchange\Formats();

$formats->addFormat('CZK', ['decimals' => 3, 'nbsp' => false]);
$formats->addFormat('USD', ['decimals' => 2, 'unit' => '$', 'nbsp' => false]);
$formats->setDefaultFormat(new Number\NumberFormat(decimals: 0, nbsp: false));

Assert::exception(function () use ($formats) {
	$formats->setDefaultFormat([]);
}, Exchange\Exceptions\InvalidStateException::class);

Assert::same('100 EUR', $formats->getFormat('EUR')->format('100'));
Assert::same($formats->getFormat('EUR'), $formats->getFormat('EUR'));
Assert::same('100,00 $', $formats->getFormat('USD')->format('100'));
Assert::same('100,000 CZK', $formats->getFormat('CZK')->format('100'));
Assert::same('100 CZK', $formats->getFormat('CZK')->modify(decimals: 0)->format('100'));
