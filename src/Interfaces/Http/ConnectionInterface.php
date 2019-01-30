<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Http;

use GuzzleHttp\ClientInterface;

interface ConnectionInterface
{
    public function createClient() : ClientInterface;
    public function createClientWithRetryHandler() : ClientInterface;
    public function get(string $url, RequestOptionsInterface $requestOptions);
    public function post(string $url, RequestOptionsInterface $requestOptions);
    public function batch(RequestOptionsInterface $requestOptions);
}
