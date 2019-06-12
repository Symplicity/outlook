<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Exception\ConnectionException;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Interfaces\Http\ConnectionInterface;
use Symplicity\Outlook\Interfaces\Http\RequestOptionsInterface;
use Symplicity\Outlook\Utilities\BatchResponse;
use Symplicity\Outlook\Utilities\RequestType;

class Connection implements ConnectionInterface
{
    public const MAX_RETRIES = 3;

    private $logger;
    protected $responses;

    protected static $eventInfo = [];

    public function __construct(?LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function get(string $url, RequestOptionsInterface $requestOptions, array $args = []) : ResponseInterface
    {
        $client = $this->createClientWithRetryHandler();
        $options = [
            'headers' => $requestOptions->getHeaders()
        ];

        if (empty($args['skipQueryParams'])) {
            $options['query'] = $requestOptions->getQueryParams();
        }

        try {
            return $client->request(RequestType::Get, $url, $options);
        } catch (\Exception $e) {
            throw new ConnectionException(sprintf('Unable to GET for URL %s', $url), $e->getCode());
        }
    }

    public function post(string $url, RequestOptionsInterface $requestOptions) : ResponseInterface
    {
        $client = $this->createClient();

        try {
            return $client->request(RequestType::Post, $url, [
                'headers' => $requestOptions->getHeaders(),
                'query' => $requestOptions->getQueryParams(),
                'json' => $requestOptions->getBody()
            ]);
        } catch (\Exception $e) {
            throw new ConnectionException(sprintf('Unable to POST for URL %s', $url));
        }
    }

    public function batch(RequestOptionsInterface $requestOptions)
    {
        /** @var Client $client */
        $client = $this->createClient();
        $promises = [];
        $rootUrl = \Symplicity\Outlook\Http\Request::getRootApi();

        /** @var WriterInterface $writer */
        foreach ($requestOptions->getBody() as $writer) {
            $id = $writer->getId();
            $json = $writer->jsonSerialize();

            static::$eventInfo[$id] = $json + ['eventType' => $writer->getInternalEventType()];

            // Prepare promises
            $promises[$id] = $client->requestAsync(
                $writer->getMethod(),
                $rootUrl . $writer->getUrl(),
                [
                    'headers' => $requestOptions->getHeaders(),
                    'json' => $json,
                    'delay' => 0.9 * 1000
                ]
            );
        }

        $responses = \GuzzleHttp\Promise\settle($promises)->wait();
        $this->setResponses($responses);
        return $this->responses;
    }

    public function batchDelete(RequestOptionsInterface $requestOptions)
    {
        $client = $this->createClient();
        $promises = [];
        $rootUrl = \Symplicity\Outlook\Http\Request::getRootApi();

        /** @var DeleteInterface $delete */
        foreach ($requestOptions->getBody() as $delete) {
            $id = $delete->getInternalId();

            // prepare for response handling
            static::$eventInfo[$id] = [
                'guid' => $delete->getGuid(),
                'eventType' => $delete->getInternalEventType(),
                'delete' => true
            ];

            // Prepare promises
            $promises[$id] = $client->requestAsync(
                RequestType::Delete,
                $rootUrl . $delete->getUrl(),
                [
                    'headers' => $requestOptions->getHeaders(),
                    'delay' => 0.9 * 1000
                ]
            );
        }

        $responses = \GuzzleHttp\Promise\settle($promises)->wait();
        $this->setResponses($responses);
        return $this->responses;
    }

    public function setResponses(array $responses)
    {
        foreach ($responses as $key => $response) {
            $this->responses[$key] = [
                'response' => new BatchResponse($response),
                'item' => static::$eventInfo[$key] ?? []
            ];
        }
    }

    public function createClientWithRetryHandler() : ClientInterface
    {
        $stack = $this->getRetryHandler();
        $client = new Client([
            'handler' => $stack
        ]);

        return $client;
    }

    protected function getRetryHandler() : HandlerStack
    {
        $stack = HandlerStack::create(new CurlMultiHandler());
        $stack->push(Middleware::retry($this->createRetryHandler(), $this->retryDelay()));
        return $stack;
    }

    public function createClient() : ClientInterface
    {
        $client = new Client();
        return $client;
    }

    public function createRetryHandler() : callable
    {
        $logger = $this->logger;
        return function(
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) use ($logger) {
            if ($retries >= static::MAX_RETRIES) {
                return false;
            }

            if (!$response instanceof ResponseInterface
                || !$this->shouldRetry($response->getStatusCode())) {
                return false;
            }

            $statusCode = 0;
            $reasonPhrase = '';

            if ($response instanceof Response) {
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
            } elseif ($exception instanceof RequestException) {
                $statusCode = $exception->getCode();
                $reasonPhrase = $exception->getMessage();
            }

            if ($logger instanceof LoggerInterface) {
                $logger->warning('Retrying', [
                    'method' => $request->getMethod(),
                    'uri' => $request->getUri(),
                    'retries' => $retries + 1,
                    'total' => static::MAX_RETRIES,
                    'responseCode' => $statusCode,
                    'message' => $reasonPhrase
                ]);
            }
            return true;
        };
    }

    public function retryDelay() : callable
    {
        return function($numberOfRetries) {
            return 1000 * $numberOfRetries;
        };
    }

    protected function shouldRetry(int $statusCode) : bool
    {
        return in_array($statusCode, [401, 403, 408, 429]) || $statusCode >= 500;
    }
}
