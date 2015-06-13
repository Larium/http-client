<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

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

        $this->assertEquals('www.httpbin.org', $data['headers']['Host']);
    }

    public function testPostRequest()
    {
        $uri = 'http://www.httpbin.org/post';
        $payload = ['foo' => 'bar'];

        $stream = $this->client->createStreamFromArray($payload);

        $request = new Request(new Uri($uri), Client::METHOD_POST, $stream);

        $data = $this->unserializeResponse($this->client->send($request));

        $this->assertArrayHasKey('foo', $data['form']);
        $this->assertEquals('bar', $data['form']['foo']);
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
