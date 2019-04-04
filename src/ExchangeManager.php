<?php declare(strict_types=1);

namespace h4kuna\Exchange;

use h4kuna\Exchange\Exceptions\UnknownCurrency;
use Nette;
use Nette\Http;

class ExchangeManager
{
	use Nette\SmartObject;

	private const EMPTY_CODE = '';

	/** @var callable[] */
	public $onChangeCurrency;

	/** @var Exchange */
	private $exchange;

	/** @var Http\Request */
	protected $request;

	/** @var Http\Response */
	protected $response;

	/** @var Http\SessionSection */
	protected $session;

	protected $parameter = 'currency';


	public function __construct(Exchange $exchange, Http\Request $request, Http\Response $response)
	{
		$this->exchange = $exchange;
		$this->request = $request;
		$this->response = $response;
	}


	public function setSession(Http\SessionSection $session): void
	{
		$this->session = $session;
	}


	public function setParameter(string $parameter): void
	{
		$this->parameter = $parameter;
	}


	public function init(Nette\Application\IPresenter $presenter): void
	{
		$code = $this->setCurrency($this->getQuery());
		if ($code === self::EMPTY_CODE) {
			if ($this->initCookie() === self::EMPTY_CODE) {
				$this->initSession();
			}
		} else {
			$this->onChangeCurrency($presenter, $code, $this->exchange);
		}
	}


	public function setCurrency(string $code): string
	{
		if ($code === self::EMPTY_CODE) {
			return self::EMPTY_CODE;
		}
		try {
			$newCode = $this->exchange->setOutput($code)->code;
		} catch (UnknownCurrency $e) {
			return self::EMPTY_CODE;
		}

		$this->saveCookie($newCode);
		$this->saveSession($newCode);

		return $newCode;
	}


	private function initCookie(): string
	{
		$code = $this->setCurrency($this->getCookie());
		if ($code === self::EMPTY_CODE) {
			$this->deleteCookie();
		}
		return $code;
	}


	private function initSession(): void
	{
		if ($this->session === null) {
			return;
		}
		$this->setCurrency($this->getSession());
	}


	protected function getSession(): string
	{
		return (string) $this->session->{$this->parameter};
	}


	protected function getQuery(): string
	{
		return $this->request->getQuery($this->parameter) ?? '';
	}


	protected function getCookie(): string
	{
		return $this->request->getCookie($this->parameter) ?? '';
	}


	protected function saveCookie(string $code): void
	{
		$this->response->setCookie($this->parameter, $code, '+6 month');
	}


	protected function saveSession(string $code): void
	{
		if ($this->session === null) {
			return;
		}
		$this->session->{$this->parameter} = $code;
		$this->session->setExpiration('+1 days');
	}


	protected function deleteCookie(): void
	{
		$this->response->deleteCookie($this->parameter);
	}

}
