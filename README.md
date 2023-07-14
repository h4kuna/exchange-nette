Exchange extension for Nette framework
-------
[![Latest stable](https://img.shields.io/packagist/v/h4kuna/exchange-nette.svg)](https://packagist.org/packages/h4kuna/exchange-nette)
[![Downloads this Month](https://img.shields.io/packagist/dm/h4kuna/exchange-nette.svg)](https://packagist.org/packages/h4kuna/exchange-nette)

This library is extension for Nette Framework and for this [Exchange](//github.com/h4kuna/exchange).

## Installation
Simple via composer 
```sh
$ composer require h4kuna/exchange-nette
```

## Registration
First step is registration extension and set tempDir.
```neon
extensions:
    exchangeExtension: h4kuna\Exchange\DI\ExchangeExtension 
```
Extension is ready to use other configuration are optionally. Default is defined three currencies CZK, EUR and USD. Currencies has default format by [h4kuna/number-format](//github.com/h4kuna/number-format), where is documentation.

## Configuration

Format options for currency read [h4kuna/number-format](//github.com/h4kuna/number-format)

```neon
exchangeExtension:
    currencies:
            czk: # upper / lower code of currency is not important
                decimals: 3
                decimalPoint: '.'
                thousandsSeparator: ','
                zeroIsEmpty: true
                emptyValue: '-'
                zeroClear: true
                mask: '1U'
                showUnitIfEmpty: false 
                nbsp: true
                unit: Kč
                intOnly: -1 # -1 is default value
                
            usd:
                unit: '$'
            gbp: [] # use default format 
            foo: null # disable
    driver: # string class of h4kuna\Exchange\Driver\Driver default is Cnb\Day
    session: false # save info about currencies to session, default is only to cookie 
    vat: 21 # add number like percent
    strict: true # default enabled, download only defined currencies, example: ['CZK', 'EUR']
    defaultFormat: null # how format currency if format is not defined, value is array like above "currencies.czk" 
    managerParameter: 'currency' # is parameter for query, cookie and session if is available
    tempDir: %tempDir% # temporary directory for cache
    filters: # extension define frour filter for latte, you can rename
        currency: currency
        currencyTo: currencyTo
        vat: vat
        vatTo: vatTo
```

## Latte
Now we have four new filters.
```latte
{=100|currency}
{=100|vat}
{=100|currencyTo}
{=100|vatTo}
```

## Request
Create url with parameter currency and change value and check cookie.
```url
/?currency=USD
```
