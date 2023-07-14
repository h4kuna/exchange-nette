<?php declare(strict_types=1);

namespace h4kuna\Exchange;

use h4kuna\Exchange;
use h4kuna\Number\NumberFormat;

class Formats
{
	/** @var array<NumberFormat> */
	private array $formats = [];

	/** @var array<string, array<string, bool|int|string|null>> */
	private array $rawFormats = [];

	private ?NumberFormat $default = null;


	/**
	 * @param array{decimals?: int|null}|NumberFormat $setup
	 */
	public function setDefaultFormat(array|NumberFormat $setup): void
	{
		if ($this->default !== null) {
			throw new Exchange\Exceptions\InvalidStateException('Default format could be setup only onetime.');
		}

		if (is_array($setup)) {
			$setup = new NumberFormat(...$setup);
		}

		$this->default = $setup;
	}


	/**
	 * @param array<string, string|bool|int|null> $setup
	 */
	public function addFormat(string $code, array $setup): void
	{
		$code = strtoupper($code);
		$this->rawFormats[$code] = $setup;
		unset($this->formats[$code]);
	}


	public function getFormat(string $code): NumberFormat
	{
		if (isset($this->formats[$code]) === false) {
			if (isset($this->rawFormats[$code])) {
				if (!isset($this->rawFormats[$code]['unit'])) {
					$this->rawFormats[$code]['unit'] = $code;
				}
				$this->formats[$code] = $this->getDefaultFormat()->modify(...$this->rawFormats[$code]);
				unset($this->rawFormats[$code]);
			} else {
				$this->formats[$code] = $this->getDefaultFormat()->modify(unit: $code);
			}
		}

		return $this->formats[$code];
	}


	private function getDefaultFormat(): NumberFormat
	{
		if ($this->default === null) {
			$this->default = new NumberFormat();
		}

		return $this->default;
	}

}
