<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
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

    private $options = [];

    private $port;

    /**
     * Sends the given request to server.
     *
     * @param Psr\Http\Message\RequestInterface $request
     * @return Psr\Http\Message\ResponseInterface
     */
    public function send(RequestInterface $request)
    {
        $url     = $this->resolveUrl($request);
        $headers = $this->resolveHeaders($request);
        $method  = $this->resolveMethod($request);

        $options = array(
            CURLOPT_HEADER          => 1,
            CURLINFO_HEADER_OUT     => 1,
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_URL             => $url,
            //close connection when it has finished, not pooled for reuse
            CURLOPT_FORBID_REUSE    => 1,
            // Do not use cached connection
            CURLOPT_FRESH_CONNECT   => 1,
            CURLOPT_CONNECTTIMEOUT  => 5,
            CURLOPT_TIMEOUT         => 7,
        );

        $options = $options + $this->options + $method;

        $handler = curl_init();
        curl_setopt_array($handler, $options);

        $result = curl_exec($handler);

        $info = curl_getinfo($handler);

        if (false === $result) {
            throw new CurlException(curl_error($handler), curl_errno($handler));
        }

        curl_close($handler);

        return $this->resolveResponse($result, $info);
    }

    /**
     * Set an option value for curl client.
     *
     * @param integer $option
     * @param string|integer $value
     * @return void
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
    }

    /**
     * Get the value of given option name, that has been applied to current
     * client.
     *
     * @param integer $option
     * @return void
     */
    public function getOption($option)
    {
        return array_key_exists($options, $this->options)
            ? $this->options[$option]
            : false;
    }

    /**
     * Mass assign options to curl client.
     *
     * @param array $options
     * @return void
     */
    public function setOptions(array $options = [])
    {
        $this->options = array_replace($this->options, $options);
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

    protected function resolveResponse($result, $info)
    {
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
        if (strpos($headers, self::WINDOWS_NEWLINE)) {
            $newLine = self::WINDOWS_NEWLINE;
        } else {
            $newLine = self::UNIX_NEWLINE;
        }

        $headerArray = [];
        $parts = explode($newLine, $headers);
        array_walk($parts, function (&$part) {
            $part = trim($part);
        });
        $headers = array_filter($parts, 'strlen');
        $statusHeader = array_shift($headers);
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

        return $headers;
    }

    private function resolveMethod(RequestInterface $request)
    {
        $options = [];

        switch ($request->getMethod()) {
            case static::METHOD_POST:
                $options[CURLOPT_POST]       = 1;
                $options[CURLOPT_POSTFIELDS] = $request->getBody()->__toString();
                break;
            case static::METHOD_GET:
                $options[CURLOPT_HTTPGET]    = 1;
                break;
            case static::METHOD_PUT:
                $options[CURLOPT_POST]          = 1;
                $options[CURLOPT_CUSTOMREQUEST] = static::METHOD_PUT;
                $options[CURLOPT_POSTFIELDS]    = $request->getBody()->__toString();
                break;
        }

        return $options;
    }

    private function resolveUrl(RequestInterface $request)
    {
        $uri = $request->getUri();

        if (!empty($uri->getUserInfo())) {
            $this->options[CURLOPT_USERPWD] = $uri->getUserInfo();
        }

        $port = $uri->getPort() ?: 80;

        $port = 'https' == $uri->getScheme() ? 443 : $port;

        $this->options[CURLOPT_PORT] = $port;

        return $uri->getScheme()
            . '://'
            . $uri->getHost()
            . $uri->getPath()
            . ($uri->getQuery() ? '?' . $uri->getQuery() : null)
            . ($uri->getFragment() ? '#' . $uri->getFragment() : null);
    }
}
