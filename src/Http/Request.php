<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use Symplicity\Outlook\Batch\Response;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Interfaces\Http\ConnectionInterface;
use Symplicity\Outlook\Utilities\BatchResponseHandler\UpsertBatchResponseHandler;
use Symplicity\Outlook\Utilities\RequestType;

class Request
{
    public const OUTLOOK_VERSION = 'v2.0';
    public const OUTLOOK_ROOT_URL = 'https://outlook.office.com/api/';
    public const OUTLOOK_BATCH_ENDPOINT = 'me/$batch';

    private $rootUrl;

    protected $accessToken;
    protected $args;
    protected $requestOptions;

    /** @var ConnectionInterface $connection */
    protected $connection;

    /** @var ResponseIterator $responseIterator */
    protected $responseIterator;

    /** @var Response $response */
    protected $response;

    public function __construct(string $accessToken, array $args = [])
    {
        $this->rootUrl = static::OUTLOOK_ROOT_URL . static::OUTLOOK_VERSION;
        $this->accessToken = $accessToken;
        $this->args = $args;
        $this->setRequestOptions($args['requestOptions']);
        $this->setConnection($args['connection']);
    }

    public function getEvents(string $url, array $params = []) : self
    {
        $url = $this->rootUrl . '/' . $url;

        /** @var RequestOptions $requestOptions */
        $requestOptions = $this->requestOptions->call($this, $url, RequestType::Get(), [
            'headers' => $params['headers'] ?? [],
            'queryParams' => $params['queryParams'] ?? [],
            'timezone' => $params['preferredTimezone'] ?? RequestOptions::DEFAULT_TIMEZONE,
            'preferenceHeaders' => $params['preferenceHeaders'] ?? [],
            'token' => $this->accessToken
        ]);

        $requestOptions->addDefaultHeaders();

        $this->responseIterator = new ResponseIterator($this->connection);
        $args = empty($params['skipQueryParams']) ? [] : ['skipQueryParams' => true];
        $this->responseIterator->setItems($url, $requestOptions, $args);
        return $this;
    }

    public function getEvent(string $url, array $params = []): ResponseInterface
    {
        $options = [
            'headers' => $params['headers'] ?? [],
            'timezone' => $params['preferredTimezone'] ?? RequestOptions::DEFAULT_TIMEZONE,
            'preferenceHeaders' => $params['preferenceHeaders'] ?? [],
            'token' => $this->accessToken
        ];

        if (isset($params['queryParams'])) {
            $options['queryParams'] = $params['queryParams'] ?? [];
        }

        /** @var RequestOptions $requestOptions */
        $requestOptions = $this->requestOptions->call($this, $url, RequestType::Get(), $options);

        $requestOptions->addDefaultHeaders(true);
        $requestOptions->addPreferenceHeaders(array_merge($requestOptions->getDefaultPreferenceHeaders(), [
            'outlook.timezone="' . $requestOptions->getPreferredTimezone() . '"'
        ]));

        return $this->connection->get($url, $requestOptions, ['skipQueryParams' => $params['skipQueryParams'] ?? true]);
    }

    public function upsert(WriterInterface $writer, array $params = [])
    {
        /** @var RequestOptions $requestOptions */
        $requestOptions = $this->requestOptions->call($this, '', $writer->getRequestType(), [
            'headers' => $params['headers'] ?? [],
            'queryParams' => $params['queryParams'] ?? [],
            'timezone' => $params['preferredTimezone'] ?? RequestOptions::DEFAULT_TIMEZONE,
            'token' => $this->accessToken,
            'body' => $writer->jsonSerialize()
        ]);

        $requestOptions->addDefaultHeaders();
        $url = Request::getRootApi() . $writer->getUrl();
        return $this->connection->upsert($url, $requestOptions);
    }

    public function delete(DeleteInterface $writer, array $params = [])
    {
        /** @var RequestOptions $requestOptions */
        $requestOptions = $this->requestOptions->call($this, '', new RequestType(RequestType::Delete), [
            'headers' => $params['headers'] ?? [],
            'queryParams' => $params['queryParams'] ?? [],
            'timezone' => $params['preferredTimezone'] ?? RequestOptions::DEFAULT_TIMEZONE,
            'token' => $this->accessToken,
        ]);

        $requestOptions->addDefaultHeaders();
        $url = Request::getRootApi() . $writer->getUrl();
        return $this->connection->delete($url, $requestOptions);
    }

    public function batch(array $events, array $params = []) : ?Response
    {
        /** @var RequestOptions $requestOptions */
        $requestOptions = $this->requestOptions->call($this, '', RequestType::Post(), [
            'headers' => $params['headers'] ?? [],
            'queryParams' => $params['queryParams'] ?? [],
            'timezone' => $params['preferredTimezone'] ?? RequestOptions::DEFAULT_TIMEZONE,
            'token' => $this->accessToken
        ]);

        $requestOptions->addBatchHeaders();
        $requestOptions->addBody($events);
        return $this->connection->batch($requestOptions);
    }

    public function getConnection() : ConnectionInterface
    {
        return $this->connection;
    }

    public function getRequestOptions() : Closure
    {
        return $this->requestOptions;
    }

    public function getResponseIterator() : ResponseIterator
    {
        return $this->responseIterator;
    }

    private function setRequestOptions(?Closure $requestOptions): void
    {
        $this->requestOptions = $requestOptions;
    }

    private function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    public function getResponseFromBatch()
    {
        return $this->response;
    }

    public static function getRootApi()
    {
        return static::OUTLOOK_ROOT_URL . static::OUTLOOK_VERSION;
    }

    public static function getBatchApi()
    {
        return static::OUTLOOK_ROOT_URL . static::OUTLOOK_VERSION . DIRECTORY_SEPARATOR . 'me/$batch';
    }
}
