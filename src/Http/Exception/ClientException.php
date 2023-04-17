<?php

declare(strict_types=1);

namespace Larium\Http\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class ClientException extends RuntimeException implements ClientExceptionInterface
{
}
