<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

trait GuzzleHttpTransactionTestTrait
{
    public static function getClientWithTransactionHandler(array &$container, MockHandler $mock): Client
    {
        $history = Middleware::history($container);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        return new Client(['handler' => $handler]);
    }
}
