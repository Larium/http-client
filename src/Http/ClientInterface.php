<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Http;

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
}
