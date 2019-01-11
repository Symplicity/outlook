<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Interfaces\ConnectionInterface;
use Symplicity\Outlook\Interfaces\RequestOptionsInterface;
use Symplicity\Outlook\Utilities\RequestType;

class Connection implements ConnectionInterface
{
    public const MAX_RETRIES = 3;

    private $logger;

    public function __construct(?LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function get(string $url, RequestOptionsInterface $requestOptions)
    {
        $client = $this->createClient();
        try {
            return $client->request(RequestType::Get, $url, [
                'headers' => $requestOptions->getHeaders(),
                'query' => $requestOptions->getQueryParams()
            ]);
        } catch (\Exception $e) {
        }
    }

    protected function pool()
    {
        $poolConfig = [
            'fulfilled' => function (Response $response, $index) {
            },
            'rejected' => function ($reason, $index) {
            }
        ];

        $pool = new Pool($this->createClient(), $requests(), $poolConfig);
        $promise = $pool->promise();
        $promise->wait();
    }

    protected function createClient() : ClientInterface
    {
        $stack = HandlerStack::create(new CurlHandler());
        $stack->push(Middleware::retry($this->createRetryHandler($this->logger)));
        $client = new Client([
            'handler' => $stack
        ]);
        return $client;
    }

    public function createRetryHandler(LoggerInterface $logger)
    {
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) use ($logger) {
            if ($retries >= MAX_RETRIES) {
                return false;
            }

            if ($response->getStatusCode() >= 500
                || $exception instanceof ConnectException) {
                return false;
            }

            $logger->warning(sprintf(
                'Retrying %s %s %s/%s, %s',
                $request->getMethod(),
                $request->getUri(),
                $retries + 1,
                MAX_RETRIES,
                $response ? 'status code: ' . $response->getStatusCode() : $exception->getMessage()
            ), [$request->getHeader('Host')[0]]);
            return true;
        };
    }
}
