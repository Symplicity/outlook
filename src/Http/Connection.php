<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions as GuzzleRequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Exception\ConnectionException;
use Symplicity\Outlook\Interfaces\Http\ConnectionInterface;
use Symplicity\Outlook\Interfaces\Http\RequestOptionsInterface;
use Symplicity\Outlook\Utilities\RequestType;

class Connection implements ConnectionInterface
{
    public const MAX_RETRIES = 3;
    public const MAX_UPSERT_RETRIES = 5;
    public $requestArgs;

    protected $clientOptions;
    protected $responses;
    protected $logger;

    protected static $eventInfo = [];

    private $requestHandler;

    public function __construct(?LoggerInterface $logger, array $clientOptions = [])
    {
        $this->logger = $logger;
        $this->clientOptions = $clientOptions;
    }

    public function get(string $url, RequestOptionsInterface $requestOptions, array $args = []) : ResponseInterface
    {
        $this->requestArgs = $args;
        $this->requestArgs['url'] = $url;

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
            if ($this->logger instanceof LoggerInterface) {
                $this->logger->warning('Get Request Failed', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
            }
        }

        return $this->retryConnection($client, $url);
    }

    public function retryConnection(ClientInterface $client, string $url): ResponseInterface
    {
        try {
            $newHeader = $this->tryRefreshHeaderToken();
            return $client->request(RequestType::Get, $url, ['headers' => $newHeader]);
        } catch (\Exception $e) {
            if ($this->logger instanceof LoggerInterface) {
                $this->logger->warning('Get Request Failed', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
            }
            throw new ConnectionException(sprintf('Unable to GET for URL %s', $url), $e->getCode());
        }
    }

    public function upsert(string $url, RequestOptionsInterface $requestOptions) : ResponseInterface
    {
        $client = $this->createClient();

        try {
            return $client->request($requestOptions->getMethod(), $url, [
                'headers' => $requestOptions->getHeaders(),
                'query' => $requestOptions->getQueryParams(),
                'json' => $requestOptions->getBody()
            ]);
        } catch (\Exception $e) {
            if ($this->logger instanceof LoggerInterface) {
                $this->logger->warning('Post Request Failed', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
            }
            throw new ConnectionException(sprintf('Unable to POST for URL %s', $url));
        }
    }

    public function delete(string $url, RequestOptionsInterface $requestOptions) : ResponseInterface
    {
        $client = $this->createClient();

        try {
            return $client->request($requestOptions->getMethod(), $url, [
                'headers' => $requestOptions->getHeaders()
            ]);
        } catch (\Exception $e) {
            if ($this->logger instanceof LoggerInterface) {
                $this->logger->warning('Delete Request Failed', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
            }
            throw new ConnectionException(sprintf('Unable to Delete for URL %s', $url));
        }
    }

    public function createClientWithRetryHandler(?callable $customRetryDelay = null) : ClientInterface
    {
        $stack = $this->getRetryHandler($customRetryDelay);
        $options = $this->getClientOptions() + ['handler' => $stack];
        return new Client($options);
    }

    protected function getRetryHandler(?callable $customRetryDelay = null) : HandlerStack
    {
        $retryHandler = $customRetryDelay ?? $this->retryDelay();
        $stack = HandlerStack::create(new CurlMultiHandler());
        $stack->push(Middleware::retry($this->createRetryHandler(), $retryHandler));
        return $stack;
    }

    public function createClient() : ClientInterface
    {
        return new Client($this->getClientOptions());
    }

    protected function getClientOptions(): array
    {
        return [
            GuzzleRequestOptions::CONNECT_TIMEOUT => $this->clientOptions['connect_timeout'] ?? 0,
            GuzzleRequestOptions::TIMEOUT => $this->clientOptions['timeout'] ?? 0,
            GuzzleRequestOptions::VERIFY => $this->clientOptions['verify'] ?? true,
            GuzzleRequestOptions::HTTP_ERRORS => $this->clientOptions['http_errors'] ?? true
        ];
    }

    public function createRetryHandler() : callable
    {
        $connection = $this;
        return function(
            $retries,
            GuzzleRequest $request,
            ?Response $response = null,
            /** @scrutinizer ignore-unused */ ?RequestException $exception = null
        ) use ($connection) {
            $isGet = $request->getMethod() === RequestType::Get;
            if ($isGet && $retries >= static::MAX_RETRIES) {
                return false;
            }

            if (!$response instanceof ResponseInterface
                || !$this->shouldRetry($response->getStatusCode())) {
                return false;
            }

            if (in_array($request->getMethod(), [RequestType::Post, RequestType::Put, RequestType::Delete])) {
                if ($response->getStatusCode() !== 429) {
                    return false;
                }

                if ($retries >= static::MAX_UPSERT_RETRIES) {
                    return false;
                }
            }

            $connection->logRetry($request, $response, [
                'retries' => $retries,
            ]);

            return true;
        };
    }

    public function logRetry(GuzzleRequest $request, ?Response $response = null, array $args = []): void
    {
        $statusCode = 0;
        $reasonPhrase = '';

        if ($response instanceof Response) {
            $statusCode = $response->getStatusCode();
            $reasonPhrase = $response->getReasonPhrase();
        }

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->warning('Retrying', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'retries' => isset($args['retries']) ? $args['retries'] + 1 : 0,
                'total' => $request->getMethod() === RequestType::Get ? static::MAX_RETRIES : static::MAX_UPSERT_RETRIES,
                'responseCode' => $statusCode,
                'message' => $reasonPhrase
            ]);
        }
    }

    public function retryDelay() : callable
    {
        return function($numberOfRetries) {
            return 1000 * $numberOfRetries;
        };
    }

    public function upsertRetryDelay(): callable
    {
        return function($numberOfRetries, $response) {
            $retryAfter = $response->getHeaderLine('Retry-After');
            if (!empty($retryAfter) && $retryAfter < 20) {
                return 1000 * $retryAfter;
            }

            return 1000 * $numberOfRetries;
        };
    }

    // Mark: Protected
    protected function shouldRetry(int $statusCode) : bool
    {
        return in_array($statusCode, [401, 403, 408, 429]) || $statusCode >= 500;
    }

    public function tryRefreshHeaderToken(): array
    {
        if (is_object($this->requestHandler) && isset($this->requestArgs['url']) && isset($this->requestArgs['token'])) {
            return $this->requestHandler->getHeadersWithToken($this->requestArgs['url'], [
                'token' => $this->requestArgs['token'],
                'logger' => $this->logger
            ]);
        }

        return [];
    }

    public function setRequestHandler($requestHandler) : void
    {
        $this->requestHandler = $requestHandler;
    }
}
