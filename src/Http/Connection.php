<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\MultipartStream;
use Symplicity\Outlook\Batch\InputFormatter;
use Symplicity\Outlook\Calendar;
use Symplicity\Outlook\Exception\BatchBoundaryMissingException;
use Symplicity\Outlook\Exception\BatchLimitExceededException;
use Symplicity\Outlook\Exception\BatchRequestEmptyException;
use Symplicity\Outlook\Interfaces\Batch\FormatterInterface;
use Symplicity\Outlook\Utilities\BatchResponseHandler\UpsertBatchResponseHandler;
use Symplicity\Outlook\Utilities\BatchResponseHandler\UpsertResponseHandler;
use Symplicity\Outlook\Utilities\UpsertBatchResponse;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions as GuzzleRequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Exception\ConnectionException;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Interfaces\Http\ConnectionInterface;
use Symplicity\Outlook\Interfaces\Http\RequestOptionsInterface;
use Symplicity\Outlook\Utilities\BatchResponse;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Batch\Response as BatchResponseHandler;

class Connection implements ConnectionInterface
{
    public const MAX_RETRIES = 3;
    public const MAX_UPSERT_RETRIES = 10;

    private $logger;
    private $clientOptions;

    protected $responses;

    protected static $eventInfo = [];

    public function __construct(?LoggerInterface $logger, array $clientOptions = [])
    {
        $this->logger = $logger;
        $this->clientOptions = $clientOptions;
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

    public function batch(RequestOptionsInterface $requestOptions, array $args = []): ?BatchResponseHandler
    {
        $boundary = $this->getBatchBoundary($requestOptions);
        $body = $this->getBatchBody($requestOptions);
        $upsertInputFormatter = $this->getFormatter($args);
        $batchContent = [];
        $responses = null;

        foreach ($body as $writer) {
            switch (true) {
                case $writer instanceof DeleteInterface:
                    $batchContent[] = $this->prepareBatchDelete($writer, $upsertInputFormatter);
                    break;
                default:
                    $batchContent[] = $this->prepareBatchWrite($writer, $upsertInputFormatter);
            }
        }

        if (count($batchContent) == 0) {
            throw new BatchRequestEmptyException('Batch request is empty');
        }

        $outlookResponse = $this->execBatch($requestOptions, $batchContent, $boundary);
        if ($outlookResponse instanceof Response) {
            $responses = new BatchResponseHandler($outlookResponse, ['eventInfo' => static::$eventInfo]);
        }

        return $responses;
    }

    private function prepareBatchWrite(WriterInterface $writer, FormatterInterface $upsertInputFormatter): array
    {
        $contentToWrite = [];
        $formattedContent = $upsertInputFormatter->format($writer);
        if (count($formattedContent)) {
            $contentToWrite = $formattedContent;
            static::$eventInfo[$writer->getId()] = [
                'guid' => $writer->getGuid() ?? null,
                'method' => $writer->getMethod(),
                'eventType' => $writer->getInternalEventType(),
                'Sensitivity' => $writer->getSensitivity()
            ];
        }

        return $contentToWrite;
    }

    private function prepareBatchDelete(DeleteInterface $delete, FormatterInterface $upsertInputFormatter): array
    {
        $contentToWrite = [];
        $formattedContent = $upsertInputFormatter->format($delete);
        if (count($formattedContent)) {
            $contentToWrite = $formattedContent;
            static::$eventInfo[$delete->getId()] = [
                'guid' => $delete->getGuid(),
                'method' => RequestType::Delete,
                'eventType' => $delete->getInternalEventType(),
                'delete' => true
            ];
        }

        return $contentToWrite;
    }

    /**
     * @deprecated
     * @param array $responses
     */
    public function setResponses(array $responses)
    {
        foreach ($responses as $key => $response) {
            $this->responses[$key] = [
                'response' => new BatchResponse($response),
                'item' => static::$eventInfo[$key] ?? []
            ];
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
        $logger = $this->logger;
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) use ($logger) {
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
                    'total' => $isGet ? static::MAX_RETRIES : static::MAX_UPSERT_RETRIES,
                    'responseCode' => $statusCode,
                    'message' => $reasonPhrase
                ]);
            }
            return true;
        };
    }

    public function retryDelay() : callable
    {
        return function ($numberOfRetries) {
            return 1000 * $numberOfRetries;
        };
    }

    public function upsertRetryDelay(): callable
    {
        return function ($numberOfRetries, $response) {
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

    protected function getFormatter(array $args = []): FormatterInterface
    {
        if (isset($args['batchInputFormatter']) && $args['batchInputFormatter'] instanceof FormatterInterface) {
            $upsertInputFormatter = $args['batchInputFormatter'];
        } else {
            $upsertInputFormatter = new InputFormatter($this->logger);
        }

        return $upsertInputFormatter;
    }

    protected function execBatch(RequestOptionsInterface $requestOptions, array $batchContent, string $boundary): ?Response
    {
        try {
            /** @var Client $client */
            $client = $this->createClientWithRetryHandler($this->upsertRetryDelay());
            $responses = $client->request(RequestType::Post, \Symplicity\Outlook\Http\Request::getBatchApi(), [
                'headers' => $requestOptions->getHeaders(),
                'body' => new MultipartStream($batchContent, $boundary)
            ]);
        } catch (\Exception $e) {}

        return $responses;
    }

    // Mark: Private
    // Batch Methods
    private function getBatchBoundary(RequestOptionsInterface $requestOptions): string
    {
        if (($boundary = $requestOptions->getBatchBoundary()) === null) {
            throw new BatchBoundaryMissingException('batch boundary id is missing');
        }

        return $boundary;
    }

    private function getBatchBody(RequestOptionsInterface $requestOptions): array
    {
        $body = $requestOptions->getBody();
        if (count($body) > Calendar::BATCH_BY) {
            throw new BatchLimitExceededException('batch maximum limit of 20 items was exceeded');
        }

        return $body;
    }
}
