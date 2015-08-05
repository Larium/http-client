# PSR-7 PHP Http Client

An http client wrapping curl php extension, compatible with [PSR-7](http://www.php-fig.org/psr/psr-7/) Http Message interfaces.

It uses [zend-diactoros](https://github.com/zendframework/zend-diactoros) PSR-7 implementation for creating Response instances.

## Installation
You can install this library using [Composer](http://getcomposer.org)
### Command line
In root directory of your project run through a console:
```bash
$ composer require "larium/http-client":"~1.0"
```
### Composer.json
Include require line in your ```composer.json``` file
```json
{
	require: {
    	"larium/http-client": "~1.0"
    }
}
```
and run from console in the root directory of your project:
```bash
$ composer update
```

After this you must require autoload file from composer.
```php
<?php
require_once 'vendor/autoload.php';
```

## Basic usage
You can use any Request class that implements [PSR-7](http://www.php-fig.org/psr/psr-7/) HTTP Message interfaces, to create the Request instance.
```php
<?php
use Larium\Http\Client;
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