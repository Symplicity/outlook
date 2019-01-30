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
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Exception\ConnectionException;
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

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function get(string $url, RequestOptionsInterface $requestOptions) : Response
    {
        $client = $this->createClientWithRetryHandler();
        try {
            return $client->request(RequestType::Get, $url, [
                'headers' => $requestOptions->getHeaders(),
                'query' => $requestOptions->getQueryParams()
            ]);
        } catch (\Exception $e) {
            throw new ConnectionException(sprintf('Unable to GET for URL %s', $url));
        }
    }

    public function post(string $url, RequestOptionsInterface $requestOptions) : Response
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

            static::$eventInfo[$id] = $json + ['eventType' => $writer->getEventType()];

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
        $stack = HandlerStack::create(new CurlMultiHandler());
        $stack->push(Middleware::retry($this->createRetryHandler($this->logger), $this->retryDelay()));
        $client = new Client([
            'handler' => $stack
        ]);

        return $client;
    }

    public function createClient() : ClientInterface
    {
        $client = new Client();
        return $client;
    }

    public function createRetryHandler()
    {
        $logger = $this->logger;
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) use ($logger) {
            if ($retries >= static::MAX_RETRIES) {
                return false;
            }

            // Wondering what we should try ??
            if ($response->getStatusCode() < 400) {
                return false;
            }

            $message = \GuzzleHttp\json_decode($response->getBody()->getContents());

            $statusCode = 0;
            $reasonPhrase = '';

            if ($response instanceof Response) {
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
            } elseif ($exception instanceof RequestException) {
                $statusCode = $exception->getCode();
                $reasonPhrase = $exception->getMessage();
            }

            $logger->warning('Retrying', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'retries' => $retries + 1,
                'total' => static::MAX_RETRIES,
                'responseCode' => $statusCode,
                'message' => $reasonPhrase,
                'exceptionMessage' => $retries === 0 ? $message->error->message : ''
            ]);

            return true;
        };
    }

    public function retryDelay()
    {
        return function ($numberOfRetries) {
            return 1000 * $numberOfRetries;
        };
    }
}
