# PSR-7 PHP Http Client

An http client wrapping curl php extension, compatible with [PSR-7](http://www.php-fig.org/psr/psr-7/) Http Message interfaces.

* [Installation](#installation)
 * [Composer from command line](#composer-from-command-line)
 * [Composer from composer.json](#composer-from-composer.json)
* [Set Up](#set-up)
* [Basic Usage](#basic-usage)
 * [Using Zend Diactoros](#using-diactoros)
 * [Using message factory](#using-message-factory)

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

## Using Diactoros

You can use any Request class that implements [PSR-7](http://www.php-fig.org/psr/psr-7/) HTTP Message interfaces, to create the Request instance.
In this example, Zend Diactoros will be used.
```php
<?php

use Zend\Diactoros\Uri;
use Larium\Http\Client;
use Zend\Diactoros\Request;
use Larium\Http\Exception\ClientException;

$uri = (new Uri())->withScheme('http')->withHost('www.example.com');
$request = (new Request())->withUri($uri);
$client = new Client();
try {
	$response = $client->send($request);
    # Response is a Psr\Http\Message\ResponseInterface instance implementation.
} catch (ClientException $e) {
	//Resolve exception from client.
}
```

## Using message factory

```php
<?php

use Larium\Http\Client;
use Larium\Http\Exception\ClientException;
use Http\Discovery\MessageFactoryDiscovery;

$messageFactory = MessageFactoryDiscovery::find();

$request = $messageFactory->createRequest('get', 'http://example.com');

$client = new Client();
try {
	$response = $client->send($request);
    # Response is a Psr\Http\Message\ResponseInterface instance implementation.
} catch (ClientException $e) {
	//Resolve exception from client.
}
```