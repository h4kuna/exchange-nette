Exchange extension for Nette framework
-------
[![Build Status](https://travis-ci.org/h4kuna/exchange-nette.svg?branch=master)](https://travis-ci.org/h4kuna/exchange-nette)
[![Latest stable](https://img.shields.io/packagist/v/h4kuna/exchange-nette.svg)](https://packagist.org/packages/h4kuna/exchange-nette)
[![Downloads this Month](https://img.shields.io/packagist/dm/h4kuna/exchange-nette.svg)](https://packagist.org/packages/h4kuna/exchange-nette)

This library is extension for Nette Framework and for this [Exchange](//github.com/h4kuna/exchange).

## Instalation
Simple via composer 
```sh
$ composer require h4kuna/exchange-nette
```

## Registration
First step is registration extension and set tempDir.
```neon
extensions:
    exchangeExtension: h4kuna\Exchange\DI\ExchangeExtension

exchangeExtension:
    tempDir: %tempDir%/currencies   
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
                zeroIsEmpty: TRUE
                emptyValue: '-'
                zeroClear: TRUE
                mask: '1U'
                showUnit: FALSE 
                nbsp: TRUE
                unit: Kč
                intOnly: -1 # -1 is default value
                
            usd:
                unit: '$'
            gbp: [] # use default format 
    
    session: [FALSE] # save info about currencies to session, default is only to cookie
    vat: [0] # add number like percent, example: 21
    strict: [FALSE] # default enabled, download only defined currencies, example: ['CZK', 'EUR']
    defaultFormat: [NULL] # how format currency if format is not defined, value is array like above "currencies.czk" 
    managerParameter: [currency] # is parameter for query, cookie and session if is available
    filters: # extension define two filter for latte, you can rename
        currency: currency
        vat: vat # if is set above via "vat"
```

## Latte
Now we have two new filters.
```latte
{=100|currency}
{=100|vat}
```

## Request
Create url with parameter currency and change value and check cookie.
```url
/?currency=USD
```
