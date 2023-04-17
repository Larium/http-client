# PSR-7 PHP Http Client

An http client wrapping curl php extension, compatible with [PSR-7](http://www.php-fig.org/psr/psr-7/) Http Message interfaces.

* [Installation](#installation)
  * [Composer from command line](#composer-from-command-line)
  * [Composer from composer.json](#composer-from-composer.json)
* [Set Up](#set-up)
* [Basic Usage](#basic-usage)
  * [Using message factory discovery](#using-message-factory-discovery)

## Installation
You can install this library using [Composer](https://getcomposer.org)
### Composer from command line
In root directory of your project run through a console:
```bash
$ composer require "larium/http-client":"~1.0"
```
### Composer from composer.json
Include require line in your ```composer.json``` file
```json
{
	"require": {
    	"larium/http-client": "~1.0"
    }
}
```
and run from console in the root directory of your project:
```bash
$ composer update
```

## Set up

After installation you must require autoload file from composer in to your boot php script.
```php
<?php
require_once 'vendor/autoload.php';
```

## Basic usage

## Using message factory discovery

You can use factory discovery to find any Request class that implements [PSR-7](http://www.php-fig.org/psr/psr-7/) HTTP Message interfaces, to create the Request instance.

```php
<?php

use Larium\Http\Client;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientExceptionInterface;

$request = Psr17FactoryDiscovery::findRequestFactory()->createRequest('GET', 'http://www.example.com');
$client = new Client();
try {
	$response = $client->sendRequest($request);
	# Response is a Psr\Http\Message\ResponseInterface instance implementation.
} catch (ClientExceptionInterface $e) {
	//Resolve exception from client.
}
```