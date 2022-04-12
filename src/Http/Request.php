<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Symplicity\Outlook\Batch\Response;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Interfaces\Http\BatchConnectionInterface;
use Symplicity\Outlook\Interfaces\Http\ConnectionInterface;
use Symplicity\Outlook\Interfaces\Http\ResponseIteratorInterface;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Token;
use Symplicity\Outlook\Interfaces\Http\RequestInterface;

class Request implements RequestInterface
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
    protected $batchConnectionHandler;

    public function __construct(string $accessToken, array $args = [])
    {
        $this->rootUrl = static::OUTLOOK_ROOT_URL . static::OUTLOOK_VERSION;
        $this->accessToken = $accessToken;
        $this->args = $args;
        $this->setRequestOptions($args['requestOptions']);
        $this->setConnection($args['connection']);
        $this->setBatchConnectionHandler($args['batchConnectionHandler'] ?? null);
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

        $args = [
            'skipQueryParams' => $params['skipQueryParams'] ?? true,
            'token' => $params['token'] ?? [],
        ];
        $this->connection->setRequestHandler($this);
        return $this->connection->get($url, $requestOptions, $args);
    }

    public function getEventIterator(string $url, array $params = []): ResponseIteratorInterface
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

        $responseIterator = new ResponseIterator($this->connection);
        $args = [
            'skipQueryParams' => $params['skipQueryParams'] ?? true,
            'token' => $params['token'] ?? [],
        ];

        return $responseIterator->setItems($url, $requestOptions, $args);
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
        return $this->getBatchConnectionHandler()->post($requestOptions);
    }

    public function getRequestOptions() : Closure
    {
        return $this->requestOptions;
    }

    public function getResponseIterator() : ResponseIterator
    {
        return $this->responseIterator;
    }

    // Mark: Protected
    protected function getBatchConnectionHandler(): BatchConnectionInterface
    {
        if ($this->batchConnectionHandler instanceof Closure) {
            $this->batchConnectionHandler = $this->batchConnectionHandler->call($this);
        }

        if (!$this->batchConnectionHandler instanceof BatchConnectionInterface) {
            throw new \InvalidArgumentException('Batch requested but handler is not set');
        }

        return $this->batchConnectionHandler;
    }

    // Mark: Private
    private function setRequestOptions(?Closure $requestOptions): void
    {
        $this->requestOptions = $requestOptions;
    }

    private function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    private function setBatchConnectionHandler(?Closure $batchConnectionHandler): void
    {
        $this->batchConnectionHandler = $batchConnectionHandler;
    }

    // Mark Static
    public static function getRootApi()
    {
        return static::OUTLOOK_ROOT_URL . static::OUTLOOK_VERSION;
    }

    public static function getBatchApi()
    {
        return static::OUTLOOK_ROOT_URL . static::OUTLOOK_VERSION . DIRECTORY_SEPARATOR . self::OUTLOOK_BATCH_ENDPOINT;
    }

    public function getHeadersWithToken(string $url, array $params = []): array
    {
        $token = isset($params['token']) ? $params['token'] : [];
        if (isset($token['clientID'], $token['clientSecret'], $token['outlookProxyUrl'])) {
            $tokenObj = new Token($token['clientID'], $token['clientSecret'], ['logger' => $params['logger']]);
            $tokenEntity = $tokenObj->refresh($token['refreshToken'], $token['outlookProxyUrl']);
            $accessToken = $tokenEntity->getAccessToken();
            if ($accessToken) {
                return $this->getHeaders($url, [
                    'headers' => [],
                    'timezone' => RequestOptions::DEFAULT_TIMEZONE,
                    'preferenceHeaders' => [],
                    'token' => $accessToken
                ]);
            }
        }

        return [];
    }

    public function getHeaders(string $url, array $options): array
    {
        /** @var RequestOptions $requestOptions */
        $requestOptions = $this->requestOptions->call($this, $url, RequestType::Get(), $options);
        $requestOptions->addDefaultHeaders(true);
        $requestOptions->addPreferenceHeaders(array_merge($requestOptions->getDefaultPreferenceHeaders(), [
            'outlook.timezone="' . $requestOptions->getPreferredTimezone() . '"'
        ]));

        return $requestOptions->getHeaders();
    }
}
