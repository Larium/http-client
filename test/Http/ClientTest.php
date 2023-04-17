<?php

declare(strict_types=1);

namespace Larium\Http;

use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr17FactoryDiscovery;
use Larium\Http\Exception\ClientException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;

use function http_build_query;
use function json_decode;

class ClientTest extends TestCase
{
    protected ClientInterface $client;

    public function setUp(): void
    {
        $this->client = new Client();
    }

    public function testGetRequest()
    {
        $uri = 'http://www.httpbin.org/get';

        $request = $this->createRequest($uri);
        $response = $this->client->sendRequest($request);
        $data = $this->unserializeResponse($response);

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals('www.httpbin.org', $data['headers']['Host']);
    }

    public function testPostRequest()
    {
        $uri = 'http://www.httpbin.org/post';
        $payload = ['foo' => 'bar'];

        $stream = $this->createStreamFromArray($payload);

        $request = $this->createRequestWithStream(Client::METHOD_POST, $uri, $stream);

        $response = $this->client->sendRequest($request);
        $data = $this->unserializeResponse($response);

        self::assertEquals(200, $response->getStatusCode());
        self::assertArrayHasKey('foo', $data['form']);
        self::assertEquals('bar', $data['form']['foo']);
    }

    public function testHeadRequest()
    {
        $uri = 'http://www.httpbin.org/get';
        $request = $this->createRequest($uri, Client::METHOD_HEAD);

        $response = $this->client->sendRequest($request);

        self::assertEquals(200, $response->getStatusCode());
    }

    public function testPutRequest()
    {
        $uri = 'http://www.httpbin.org/put';
        $payload = ['foo' => 'bar'];
        $stream = $this->createStreamFromArray($payload);

        $request = $this->createRequestWithStream(Client::METHOD_PUT, $uri, $stream);

        $response = $this->client->sendRequest($request);

        self::assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteRequest()
    {
        $uri = 'http://www.httpbin.org/delete';
        $request = $this->createRequest($uri, Client::METHOD_DELETE);

        $response = $this->client->sendRequest($request);

        self::assertEquals(200, $response->getStatusCode());
    }

    public function testPatchRequest()
    {
        $uri = 'http://www.httpbin.org/patch';
        $request = $this->createRequest($uri, Client::METHOD_PATCH);

        $response = $this->client->sendRequest($request);

        self::assertEquals(200, $response->getStatusCode());
    }

    public function testHeaders()
    {
        $request = $this->createRequest('http://www.httpbin.org/get');
        $request = $request
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'Larium http client');

        $this->client->sendRequest($request);
        $refl = new ReflectionClass($this->client);
        $refl->getProperty('info')->setAccessible(true);
        $info = $refl->getProperty('info')->getValue($this->client);

        $request_header = $info['request_header'];

        self::assertStringContainsString('User-Agent', $request_header);
        self::assertStringContainsString('Larium', $request_header);
        self::assertStringContainsString('application/json', $request_header);
    }

    public function testBasicAuthentication()
    {
        $request = $this->createRequest('http://www.httpbin.org/basic-auth/john/s3cr3t');
        $request = $request->withHeader('Authorization', 'Basic ' . (base64_encode('john:s3cr3t')));

        $response = $this->client->sendRequest($request);

        self::assertEquals(200, $response->getStatusCode());
    }

    /**
     * @expectedException Larium\Http\Exception\ClientException
     */
    public function testErrorStatusCode()
    {
        $this->expectException(ClientException::class);

        $request = $this->createRequest('https://notexistingsubdomain.larium.net');
        $this->client->sendRequest($request);
    }

    public function testSetGetOptions()
    {
        $this->client = new Client([CURLOPT_FORBID_REUSE => 0]);
        self::assertEquals(0, $this->client->getOption(CURLOPT_FORBID_REUSE));
        $options = $this->client->getOptions();
        self::assertArrayHasKey(CURLOPT_FORBID_REUSE, $options);
    }

    public function testSetArrayOptions()
    {
        $this->client = new Client([
            CURLOPT_FORBID_REUSE => 0,
            CURLOPT_FRESH_CONNECT => 0
        ]);

        self::assertEquals(0, $this->client->getOption(CURLOPT_FORBID_REUSE));
        self::assertEquals(0, $this->client->getOption(CURLOPT_FRESH_CONNECT));
        $options = $this->client->getOptions();
        self::assertArrayHasKey(CURLOPT_FORBID_REUSE, $options);
        self::assertArrayHasKey(CURLOPT_FRESH_CONNECT, $options);
    }

    public function testShouldParseEmptyResponse(): void
    {
        $request = $this->createRequest('http://www.httpbin.org/status/204');

        $response = $this->client->sendRequest($request);

        self::assertEmpty($response->getBody()->__toString(), $response->getBody()->__toString());

    }

    private function createRequest($uri, $method = Client::METHOD_GET)
    {
        return (new Psr17Factory())->createRequest($method, $uri);
    }

    private function createRequestWithStream(string $method, string $uri, StreamInterface $stream): RequestInterface
    {
        $request = (new Psr17Factory())->createRequest($method, $uri);

        return $request->withBody($stream);
    }

    private function unserializeResponse($response)
    {
        $string = $response->getBody()->__toString();

        return json_decode($string, true);
    }

    private function createStreamFromArray(array $payload): StreamInterface
    {
        $content = http_build_query($payload);

        return Psr17FactoryDiscovery::findStreamFactory()->createStream($content);
    }
}
