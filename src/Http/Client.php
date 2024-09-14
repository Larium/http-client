<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/*
 * This file is part of the Larium Http Client package.
 *
 * (c) Andreas Kollaros <andreas@larium.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Larium\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Larium\Http\Exception\ClientException;
use Larium\Http\Exception\NetworkException;
use Larium\Http\Exception\RequestException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

use function array_filter;
use function array_walk;
use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function explode;
use function http_build_query;
use function strlen;
use function strpos;
use function trim;

class Client implements ClientInterface
{
    public const METHOD_GET        = 'GET';
    public const METHOD_POST       = 'POST';
    public const METHOD_PUT        = 'PUT';
    public const METHOD_DELETE     = 'DELETE';
    public const METHOD_HEAD       = 'HEAD';
    public const METHOD_PATCH      = 'PATCH';
    public const METHOD_CONNECT    = 'CONNECT';
    public const METHOD_OPTIONS    = 'OPTIONS';

    private const UNIX_NEWLINE      = "\n";

    private const WINDOWS_NEWLINE   = "\r\n";

    # Expose some common curl options as Client constants.
    public const CONNECT_TIMEOUT   = CURLOPT_CONNECTTIMEOUT;
    public const TIMEOUT           = CURLOPT_TIMEOUT;
    public const SSL_VERIFY_PEER   = CURLOPT_SSL_VERIFYPEER;
    public const SSL_VERIFY_HOST   = CURLOPT_SSL_VERIFYHOST;
    public const USER_AGENT        = CURLOPT_USERAGENT;

    private $options = [
        CURLOPT_HEADER          => 1,
        CURLINFO_HEADER_OUT     => 1,
        CURLOPT_RETURNTRANSFER  => 1,
        //close connection when it has finished, not pooled for reuse
        CURLOPT_FORBID_REUSE    => 1,
        // Do not use cached connection
        CURLOPT_FRESH_CONNECT   => 1,
    ];

    private array $info = [];

    private ResponseFactoryInterface $responseFactory;

    private StreamFactoryInterface $streamFactory;

    public function __construct(array $options = [])
    {
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $this->options = array_replace($this->options, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->resolveUrl($request);
        $this->resolveHeaders($request);
        $this->resolveMethod($request);

        $handler = curl_init();
        curl_setopt_array($handler, $this->options);

        $result = curl_exec($handler);
        $this->info = curl_getinfo($handler);

        $curlError = curl_error($handler);
        $curlErrno = curl_errno($handler);
        curl_close($handler);

        if (in_array($curlErrno, [
            CURLE_COULDNT_RESOLVE_PROXY,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_OPERATION_TIMEOUTED,
            CURLE_SSL_CONNECT_ERROR
        ])) {
            throw new NetworkException($request, $curlError, 500);
        }

        if ($result === false) {
            throw new ClientException($curlError, $curlErrno);
        }

        return $this->resolveResponse($result);
    }

    /**
     * Gets curl info regardless of success or failed transaction.
     * @deprecated 2.0.0
     *
     * @return array
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Set an option value for curl client.
     *
     * @deprecated 2.0.0
     */
    public function setOption(int $option, mixed $value): void
    {
        $this->options[$option] = $value;
    }

    /**
     * @deprecated 2.0.0
     */
    public function getOption($option): mixed
    {
        return array_key_exists($option, $this->options)
            ? $this->options[$option]
            : false;
    }

    /**
     * @deprecated 2.0.0
     */
    public function setOptions(array $options = []): void
    {
        $this->options = array_replace($this->options, $options);
    }

    /**
     * @deprecated 2.0.0
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Allow setting a basic authentication direct to client instead from
     * Uri.
     * @deprecated 2.0.0
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    public function setBasicAuthentication($username, $password): void
    {
        $this->options[CURLOPT_USERPWD] = "{$username}:{$password}";
    }

    /**
     * Helper method to create a string stream from given array.
     *
     * @deprecated 2.0.0 Use stream factory
     *
     * @param array $params
     * @return StreamInterface
     */
    public function createStreamFromArray(array $params): StreamInterface
    {
        $string = http_build_query($params);

        return $this->createStream($string);
    }

    /**
     * Helper method to create a string stream.
     *
     * @deprecated 2.0.0 Use stream factory
     *
     * @param string $string
     * @return StreamInterface
     */
    public function createStream(string $string): StreamInterface
    {
        $stream = $this->streamFactory->createStream($string);

        return $stream;
    }

    private function resolveResponse(string $result): ResponseInterface
    {
        $info = $this->info;

        $statusCode = $info['http_code'];
        $headerSize = intval($info['header_size']);
        $headersString = substr($result, 0, $headerSize);
        $headers = $this->resolveResponseHeaders($headersString);
        $bodySize = intval($info['size_download']);
        $body = $bodySize === 0 ? '' : substr($result, $bodySize * -1);
        $stream = $this->streamFactory->createStream($body);

        $response = $this->responseFactory->createResponse($statusCode)
            ->withBody($stream);

        foreach ($headers as $header => $values) {
            $response = $response->withHeader($header, $values);
        }

        return $response;
    }

    private function resolveResponseHeaders(string $headers): array
    {
        $newLine = self::UNIX_NEWLINE;

        if (strpos($headers, self::WINDOWS_NEWLINE)) {
            $newLine = self::WINDOWS_NEWLINE;
        }

        $headerArray = [];
        $parts = explode($newLine, $headers);
        array_walk($parts, function (&$part) {
            $part = trim($part);
        });
        $headers = array_filter($parts, function ($v, $k) {
            return strlen($v) && false !== strpos($v, ':');
        }, ARRAY_FILTER_USE_BOTH);

        foreach ($headers as $header) {
            $info = explode(':', $header, 2);
            $headerArray[$info[0]] = explode(', ', trim($info[1] ?? ''));
        }

        return $headerArray;
    }

    private function resolveHeaders(RequestInterface $request): void
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(", ", $values);
        }

        $this->options[CURLOPT_HTTPHEADER] = $headers;
    }

    private function resolveMethod(RequestInterface $request): void
    {
        unset($this->options[CURLOPT_CUSTOMREQUEST]);
        unset($this->options[CURLOPT_POSTFIELDS]);
        unset($this->options[CURLOPT_POST]);
        unset($this->options[CURLOPT_HTTPGET]);

        switch ($request->getMethod()) {
            case static::METHOD_POST:
                if ($request->getBody()->isSeekable() === false) {
                    throw new RequestException($request, 'Request body is not seekable', 400);
                }
                $this->options[CURLOPT_POST] = 1;
                $this->options[CURLOPT_POSTFIELDS] = $request->getBody()->__toString();
                break;
            case static::METHOD_GET:
                $this->options[CURLOPT_HTTPGET] = 1;
                break;
            case static::METHOD_PUT:
                if ($request->getBody()->isSeekable() === false) {
                    throw new RequestException($request, 'Request body is not seekable', 400);
                }
                $this->options[CURLOPT_POST] = 1;
                $this->options[CURLOPT_CUSTOMREQUEST] = static::METHOD_PUT;
                $this->options[CURLOPT_POSTFIELDS] = $request->getBody()->__toString();
                break;
            case static::METHOD_DELETE:
                $this->options[CURLOPT_CUSTOMREQUEST] = static::METHOD_DELETE;
                $this->options[CURLOPT_POSTFIELDS] = $request->getBody()->__toString();
                break;
            case static::METHOD_PATCH:
                $this->options[CURLOPT_CUSTOMREQUEST] = static::METHOD_PATCH;
                break;
            case static::METHOD_HEAD:
                $this->options[CURLOPT_CUSTOMREQUEST] = static::METHOD_HEAD;
                $this->options[CURLOPT_NOBODY] = true;
                break;
            default:
                throw new RequestException($request, 'Invalid request method', 400);
        }
    }

    private function resolveUrl(RequestInterface $request): void
    {
        $uri = $request->getUri();

        if (!empty($uri->getUserInfo())) {
            $this->options[CURLOPT_USERPWD] = $uri->getUserInfo();
        }

        $port = $uri->getPort() ?: 80;
        $port = 'https' == $uri->getScheme() ? 443 : $port;

        $this->options[CURLOPT_PORT] = $port;
        $this->options[CURLOPT_URL]  = $uri->__toString();
    }
}
