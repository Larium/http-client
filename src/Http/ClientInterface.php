<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Http;

use Psr\Http\Message\RequestInterface;

interface ClientInterface
{
    const METHOD_GET        = 'GET';

    const METHOD_POST       = 'POST';

    const METHOD_PUT        = 'PUT';

    const METHOD_DELETE     = 'DELETE';

    const METHOD_HEAD       = 'HEAD';

    const METHOD_PATCH      = 'PATCH';

    const METHOD_CONNECT    = 'CONNECT';

    const METHOD_OPTIONS    = 'OPTIONS';

    /**
     * Sends the given request to server.
     *
     * @param Psr\Http\Message\RequestInterface $request
     * @return Psr\Http\Message\ResponseInterface
     */
    public function send(RequestInterface $request);

    /**
     * @param integer $option
     * @param string|integer $value
     * @return void
     */
    public function setOption($option, $value);

    /**
     * Get the value of given option name, that has been applied to current
     * client.
     *
     * @param integer $option
     * @return mixed|false
     */
    public function getOption($option);

    /**
     * Mass assign options to client.
     *
     * @param array $options
     * @return void
     */
    public function setOptions(array $options = array());

    /**
     * Gets raw info.
     *
     * @return mixed|array
     */
    public function getInfo();
}
