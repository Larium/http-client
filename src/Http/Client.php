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

use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\Response;
use Larium\Http\Exception\CurlException;

class Client implements ClientInterface
{
    const UNIX_NEWLINE      = "\n";

    const WINDOWS_NEWLINE   = "\r\n";

    # Expose some common curl options as Client constants.
    const CONNECT_TIMEOUT   = CURLOPT_CONNECTTIMEOUT;
    const TIMEOUT           = CURLOPT_TIMEOUT;
    const SSL_VERIFY_PEER   = CURLOPT_SSL_VERIFYPEER;
    const SSL_VERIFY_HOST   = CURLOPT_SSL_VERIFYHOST;
    const USER_AGENT        = CURLOPT_USERAGENT;

    private $options = array(
        CURLOPT_HEADER          => 1,
        CURLINFO_HEADER_OUT     => 1,
        CURLOPT_RETURNTRANSFER  => 1,
        //close connection when it has finished, not pooled for reuse
        CURLOPT_FORBID_REUSE    => 1,
        // Do not use cached connection
        CURLOPT_FRESH_CONNECT   => 1,
        CURLOPT_CONNECTTIMEOUT  => 5,
        CURLOPT_TIMEOUT         => 7,
    );

    private $info;

    /**
     * {@inheritdoc}
     */
    public function send(RequestInterface $request)
    {
        $this->resolveUrl($request);
        $this->resolveHeaders($request);
        $this->resolveMethod($request);

        $handler = curl_init();
        if (false === curl_setopt_array($handler, $this->options)) {
            throw new CurlException('Invalid options for cUrl client');
        }
        $result     = curl_exec($handler);
        $this->info = curl_getinfo($handler);

        if (false === $result) {
            $curlError = curl_error($handler);
            $curlErrno = curl_errno($handler);
            curl_close($handler);
            throw new CurlException($curlError, $curlErrno);
        }

        curl_close($handler);

        return $this->resolveResponse($result);
    }

    /**
     * Gets curl info regardless of success or failed transaction.
     *
     * @return array
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * Set an option value for curl client.
     * {@inheritdoc}
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($option)
    {
        return array_key_exists($option, $this->options)
            ? $this->options[$option]
            : false;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options = array())
    {
        $this->options = array_replace($this->options, $options);
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Allow setting a basic authentication direct to client instead from
     * Uri.
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    public function setBasicAuthentication($username, $password)
    {
        $this->options[CURLOPT_USERPWD] = "{$username}:{$password}";
    }

    protected function resolveResponse($result)
    {
        $info = $this->info;

        $statusCode     = $info['http_code'];
        $headersString  = substr($result, 0, $info['header_size']);
        $headers        = $this->resolveResponseHeaders($headersString);
        $body           = substr($result, -$info['size_download']);
        $stream         = $this->createStream($body);

        $response = new Response($stream);
        $response = $response->withStatus($statusCode);
        foreach ($headers as $header => $values) {
            $response = $response->withHeader($header, $values);
        }

        return $response;
    }

    /**
     * Helper method to create a string stream from given array.
     *
     * @param array $params
     * @return stream resource
     */
    public function createStreamFromArray(array $params)
    {
        $string = "";
        foreach ($params as $key => $value) {
            $string .= $key . '=' . urlencode(trim($value)) . '&';
        }

        $string = rtrim($string, "&");

        return $this->createStream($string);
    }

    protected function createStream($string)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $string);
        rewind($stream);

        return $stream;
    }

    private function resolveResponseHeaders($headers)
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
        $headers = array_filter($parts, 'strlen');
        array_shift($headers); # remove status header.
        foreach ($headers as $header) {
            $info = explode(': ', $header, 2);
            $headerArray[$info[0]] = explode(', ', $info[1]);
        }

        return $headerArray;
    }

    private function resolveHeaders(RequestInterface $request)
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(", ", $values);
        }

        $this->options[CURLOPT_HTTPHEADER] = $headers;
    }

    private function resolveMethod(RequestInterface $request)
    {
        unset($this->options[CURLOPT_CUSTOMREQUEST]);
        unset($this->options[CURLOPT_POSTFIELDS]);
        unset($this->options[CURLOPT_POST]);
        unset($this->options[CURLOPT_HTTPGET]);

        switch ($request->getMethod()) {
            case static::METHOD_POST:
                $this->options[CURLOPT_POST]       = 1;
                $this->options[CURLOPT_POSTFIELDS] = $request->getBody()->__toString();
                break;
            case static::METHOD_GET:
                $this->options[CURLOPT_HTTPGET]    = 1;
                break;
            case static::METHOD_PUT:
                $this->options[CURLOPT_POST]          = 1;
                $this->options[CURLOPT_CUSTOMREQUEST] = static::METHOD_PUT;
                $this->options[CURLOPT_POSTFIELDS]    = $request->getBody()->__toString();
                break;
            case static::METHOD_DELETE:
                $this->options[CURLOPT_CUSTOMREQUEST] = static::METHOD_DELETE;
                break;
            case static::METHOD_PATCH:
                $this->options[CURLOPT_CUSTOMREQUEST] = static::METHOD_PATCH;
                break;
            case static::METHOD_HEAD:
                $this->options[CURLOPT_CUSTOMREQUEST] = static::METHOD_HEAD;
                $this->options[CURLOPT_NOBODY]        = true;
                break;
        }
    }

    private function resolveUrl(RequestInterface $request)
    {
        $uri = $request->getUri();

        if (!empty($uri->getUserInfo())) {
            $this->options[CURLOPT_USERPWD] = $uri->getUserInfo();
        }

        $port = $uri->getPort() ?: 80;

        $port = 'https' == $uri->getScheme() ? 443 : $port;

        $uri = $uri->getScheme()
            . '://'
            . $uri->getHost()
            . $uri->getPath()
            . ($uri->getQuery() ? '?' . $uri->getQuery() : null)
            . ($uri->getFragment() ? '#' . $uri->getFragment() : null);

        $this->options[CURLOPT_PORT] = $port;
        $this->options[CURLOPT_URL]  = $uri;
    }
}
