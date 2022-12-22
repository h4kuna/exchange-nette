<?php declare(strict_types=1);

namespace h4kuna\Exchange\DI;


use h4kuna\Exchange\Driver\Cnb\Day;

/**
 * @phpstan-type currency array{unit?: string, mask?: string, showUnit?: bool, nbsp?: bool, decimal?: int, decimalPoint?: string, thousandsSeparator?: string, zeroIsEmpty?: bool, emptyValue?: ?string, zeroClear?: bool, intOnly?: int, round?: int,}
 */
final class Config
{
	public bool $strict = true;

	public float|int $vat = 21;

	public string $driver = Day::class;

	/**
	 * @var ?currency
	 */
	public $defaultFormat = [];

	/**
	 * @var array<string, currency>
	 */
	public array $currencies = [
		'czk' => ['unit' => 'Kč'],
		'eur' => ['unit' => '€', 'mask' => '⎵ 1'],
		'usd' => ['unit' => '$', 'mask' => '⎵1'],
	];

	public string $tempDir;

	public bool $session = false;

	public string $managerParameter = 'currency';

	/**
	 * @var array<string, string>
	 */
	public array $filters = [
		'currency' => 'currency',
		'currencyTo' => 'currencyTo',
		'vat' => 'vat',
		'vatTo' => 'vatTo',
	];

}
