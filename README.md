Exchange extension for Nette framework
-------
[![Downloads this Month](https://img.shields.io/packagist/dm/h4kuna/exchange-nette.svg)](https://packagist.org/packages/h4kuna/exchange-nette)
[![Latest Stable Version](https://poser.pugx.org/h4kuna/exchange-nette/v/stable?format=flat)](https://packagist.org/packages/h4kuna/exchange-nette)
[![Coverage Status](https://coveralls.io/repos/github/h4kuna/exchange-nette/badge.svg?branch=main)](https://coveralls.io/github/h4kuna/exchange-nette?branch=main)
[![Total Downloads](https://poser.pugx.org/h4kuna/exchange-nette/downloads?format=flat)](https://packagist.org/packages/h4kuna/exchange-nette)
[![License](https://poser.pugx.org/h4kuna/exchange-nette/license?format=flat)](https://packagist.org/packages/h4kuna/exchange-nette)

Part of the [h4kuna PHP libraries](https://github.com/h4kuna/library), see the overview of all packages.

This library is an extension of the Nette Framework for [h4kuna/exchange](//github.com/h4kuna/exchange).

## Installation
Simply via composer, requires PHP 8.2 or newer.
```sh
composer require h4kuna/exchange-nette
```

Optional dependencies:
```sh
composer require guzzlehttp/guzzle malkusch/lock nette/application nette/caching
```

- `guzzlehttp/guzzle` is the default HTTP client and request factory, if no other is registered in the container.
- `malkusch/lock` and `nette/caching` are required by the default cache.
- `nette/application` is needed to initialize `ExchangeManager` automatically on each presenter, see [Request](#request).

## Registration
The first step is to register the extension.
```neon
extensions:
    exchangeExtension: h4kuna\Exchange\DI\ExchangeExtension
```
The extension is ready to use, the other configuration is optional. By default, three currencies are defined: CZK, EUR and USD. The first currency (CZK by default) is the default currency of `Exchange`. Currencies have a default format provided by [h4kuna/number-format](//github.com/h4kuna/number-format), where you can find the documentation.

## Configuration

For the format options of a currency, read [h4kuna/number-format](//github.com/h4kuna/number-format). Currencies defined in your configuration are merged with the default ones, use `currencies!:` if you want to replace them.

```neon
exchangeExtension:
    currencies:
        czk: # upper / lower case of the currency code does not matter
            decimals: 3
            decimalPoint: '.'
            thousandsSeparator: ','
            zeroIsEmpty: true
            emptyValue: '-'
            zeroClear: h4kuna\Format\Number\Parameters\ZeroClear::DECIMALS
            mask: '1 ⎵' # ⎵ is the placeholder for the unit
            showUnitIfEmpty: false
            nbsp: true
            unit: Kč
            round: h4kuna\Format\Number\Round::BY_CEIL

        usd:
            unit: '$'
        gbp: [] # use the default format
    driver: h4kuna\Exchange\Driver\Cnb\Day # class implementing h4kuna\Exchange\Driver\Source, default is Cnb\Day
    session: false # also save the selected currency to the session, by default only to the cookie
    vat: 21 # VAT in percent
    strict: true # enabled by default, download only the defined currencies, e.g. ['CZK', 'EUR', 'USD']
    defaultFormat: [] # format of a currency that has no format defined, the value is an array like "currencies.czk" above
    managerParameter: 'currency' # name of the parameter for the query, cookie and session
    tempDir: %tempDir% # temporary directory for the cache
    filters: # the extension defines four filters for Latte, you can rename them
        currency: currency
        currencyTo: currencyTo
        vat: vat
        vatTo: vatTo
```

## Latte
Now we have four new filters.
```latte
{=100|currency}            {* format in the default currency, optional arguments: from, to *}
{=100|vat}                 {* add VAT and format, optional arguments: from, to *}
{=100|currencyTo:'EUR'}    {* convert from the default currency to EUR and format *}
{=100|vatTo:'EUR'}         {* add VAT, convert to EUR and format *}
```

## Request
If `nette/application` and the `http` and `session` extensions are registered, `ExchangeManager` is initialized for every presenter. It reads the currency code from the query parameter, the cookie or the session (in this order), checks that the currency exists and saves it to the cookie (and to the session if `session: true`). When the code comes from the query, the `onChangeCurrency($presenter, $code)` event is triggered.
```url
/?currency=USD
```
