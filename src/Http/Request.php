<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use Closure;
use Symplicity\Outlook\Interfaces\ConnectionInterface;
use Symplicity\Outlook\Utilities\RequestType;

class Request
{
    public const OUTLOOK_VERSION = 'v2.0';
    public const OUTLOOK_ROOT_URL = 'https://outlook.office.com/api/';

    private $rootUrl;

    protected $accessToken;
    protected $args;
    protected $requestOptions;

    /** @var Connection $connection */
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
            'token' => $this->accessToken
        ]);

        $requestOptions->addDefaultHeaders();

        $this->responseIterator = new ResponseIterator($this->connection);
        $this->responseIterator->setItems($url, $requestOptions);
        return $this;
    }

    public function batch(array $events, array $params = []) : self
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
        $this->response = $this->connection->batch($requestOptions);
        return $this;
    }

    public function getConnection() : Closure
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

    public function getRootApi()
    {
        return static::OUTLOOK_ROOT_URL . static::OUTLOOK_VERSION;
    }
}
