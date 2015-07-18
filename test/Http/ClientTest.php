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

use Zend\Diactoros\Uri;
use Zend\Diactoros\Request;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    protected $client;

    public function setUp()
    {
        $this->client = new Client();
    }

    public function testGetRequest()
    {
        $uri = 'http://www.httpbin.org/get';

        $request = $this->createRequest($uri);

        $response = $this->client->send($request);

        $data = $this->unserializeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('www.httpbin.org', $data['headers']['Host']);
    }

    public function testPostRequest()
    {
        $uri = 'http://www.httpbin.org/post';
        $payload = ['foo' => 'bar'];

        $stream = $this->client->createStreamFromArray($payload);

        $request = new Request(new Uri($uri), Client::METHOD_POST, $stream);

        $response = $this->client->send($request);
        $data = $this->unserializeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('foo', $data['form']);
        $this->assertEquals('bar', $data['form']['foo']);
    }

    public function testHeadRequest()
    {
        $uri = 'http://www.httpbin.org/get';
        $request = new Request(new Uri($uri), Client::METHOD_HEAD);

        $response = $this->client->send($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPutRequest()
    {
        $uri = 'http://www.httpbin.org/put';
        $payload = ['foo' => 'bar'];
        $stream = $this->client->createStreamFromArray($payload);
        $request = new Request(new Uri($uri), Client::METHOD_PUT, $stream);

        $response = $this->client->send($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteRequest()
    {
        $uri = 'http://www.httpbin.org/delete';
        $request = new Request(new Uri($uri), Client::METHOD_DELETE);

        $response = $this->client->send($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHeaders()
    {
        $request = $this->createRequest('http://www.httpbin.org/get');
        $request = $request
            ->withHeader('Accept', 'application/json');

        $this->client->setOption(Client::USER_AGENT, 'Larium http client');
        $response = $this->client->send($request);

        $info = $this->client->getInfo();
        $request_header = $info['request_header'];

        $this->assertContains('User-Agent', $request_header);
        $this->assertContains('Larium', $request_header);
        $this->assertContains('application/json', $request_header);
    }

    public function testBasicAuthentication()
    {
        $request = $this->createRequest('http://www.httpbin.org//basic-auth/john/s3cr3t');
        $this->client->setBasicAuthentication('john', 's3cr3t');

        $response = $this->client->send($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function createRequest($uri, $method = Client::METHOD_GET)
    {
        return new Request(new Uri($uri), $method);
    }

    private function unserializeResponse($response)
    {
        $string = $response->getBody()->__toString();

        return json_decode($string, true);
    }
}
