<?php declare(strict_types=1);

namespace h4kuna\Exchange;

use h4kuna\Exchange\RatingList\Accessor;
use Nette;
use Nette\Http;

class ExchangeManager
{
	use Nette\SmartObject;

	private const EMPTY_CODE = '';

	/** @var array<callable> */
	public array $onChangeCurrency;

	protected ?Http\SessionSection $session = null;

	protected string $parameter = 'currency';


	public function __construct(
		private Accessor $ratingList,
		private Http\Request $request,
		private Http\Response $response,
	)
	{
	}


	public function setSession(Http\SessionSection $session): void
	{
		$this->session = $session;
	}


	public function setParameter(string $parameter): void
	{
		$this->parameter = $parameter;
	}


	/**
	 * @param mixed $presenter
	 */
	public function init($presenter): void
	{
		$code = $this->setCurrency($this->getQuery());
		if ($code === self::EMPTY_CODE) {
			if ($this->initCookie() === self::EMPTY_CODE) {
				$this->initSession();
			}
		} else {
			$this->onChangeCurrency($presenter, $code);
		}
	}


	public function setCurrency(string $code): string
	{
		if ($code === self::EMPTY_CODE) {
			return self::EMPTY_CODE;
		}
		$code = strtoupper($code);

		if (!$this->ratingList->get()->offsetExists($code)) {
			return self::EMPTY_CODE;
		}

		$this->saveCookie($code);
		$this->saveSession($code);

		return $code;
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
		return strval($this->request->getQuery($this->parameter));
	}


	protected function getCookie(): string
	{
		return strval($this->request->getCookie($this->parameter));
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
