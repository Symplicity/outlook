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
use Symplicity\Accommodate\Plugins\CalendarSync\OutlookUserMapping;
use Symplicity\Accommodate\Plugins\CalendarSync\Outlook;
use Symplicity\Accommodate\App;

class Connection implements ConnectionInterface
{
    public const MAX_RETRIES = 3;
    public const MAX_UPSERT_RETRIES = 5;

    protected $clientOptions;
    protected $responses;
    protected $logger;
    protected $requestArgs;

    protected static $eventInfo = [];

    public function __construct(?LoggerInterface $logger, array $clientOptions = [])
    {
        $this->logger = $logger;
        $this->clientOptions = $clientOptions;
    }

    public function get(string $url, RequestOptionsInterface $requestOptions, array $args = []) : ResponseInterface
    {
        $this->requestArgs = [
            'url' => $url,
            'requestOptions' => $requestOptions,
            'args' => $args,
        ];

        $client = $this->createClientWithRetryHandler();
        $options = [
            'headers' => $requestOptions->getHeaders(),
            'http_errors' => false,
        ];

        if (empty($args['skipQueryParams'])) {
            $options['query'] = $requestOptions->getQueryParams();
        }

        try {
            $response = $client->request(RequestType::Get, $url, $options);
            if ($this->shouldRetry($response->getStatusCode())) {
                $newHeader = $this->tryRefreshHeaderToken();
                if (!empty($newHeader)) {
                    $options['headers'] = $newHeader;
                    $options['http_errors'] = true;
                    $response = $client->request(RequestType::Get, $url, $options);
                }
            }

            return $response;
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
        if (!empty($this->requestArgs['args']['accessToken']) 
            && !empty($this->requestArgs['args']['RequestObj'])
            && !empty($this->requestArgs['url'])
            && !empty($this->requestArgs['args']['params'])) {

            if (method_exists(App::class, 'getContainer')
                && method_exists(Outlook::class, 'getToken')
                && method_exists(Outlook::class, 'init')
                && method_exists(OutlookUserMapping::class, 'getUserInfoByAccessToken')) {

                $app = new App();
                $c = $app->getContainer();
                $accessToken = $this->requestArgs['args']['accessToken'];
                $userInfo = OutlookUserMapping::getUserInfoByAccessToken($c, $accessToken);
                if (!empty($userInfo)) {
                    $outlook = Outlook::init($c, $userInfo['user'], $userInfo);
                    if (!empty($outlook)) {
                        $newAccessToken = $outlook->getToken();
                        $requestObj = $this->requestArgs['args']['RequestObj'];
                        $requestObj->setAccessToken($newAccessToken);
                        $params = $this->requestArgs['args']['params'];
                        $newHeader = $requestObj->getNewHeader($this->requestArgs['url'], $params);

                        return $newHeader;
                    }
                }
            }
        }

        return [];
    }
}
