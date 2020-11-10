<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Notification;

use \Symplicity\Outlook\Entities\Subscription as SubscriptionEntity;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symplicity\Outlook\Entities\SubscriptionResponse;
use Symplicity\Outlook\Exception\SubscribeFailedException;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\Entity\SubscriptionEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\SubscriptionResponseEntityInterface;
use Symplicity\Outlook\Interfaces\Http\ConnectionInterface;
use Symplicity\Outlook\Interfaces\Http\RequestOptionsInterface;
use Symplicity\Outlook\Interfaces\Notification\SubscriptionInterface;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Utilities\ResponseHandler;

class Subscription implements SubscriptionInterface
{
    protected const VERSION = 'v2.0';
    protected const AUTHORITY = 'https://outlook.office.com';
    protected const COMMON = 'api';
    protected const SUBSCRIPTION_URL = 'me/subscriptions';

    /** @var LoggerInterface $loggerInterface */
    private $logger;

    /** @var ConnectionInterface|null $connection */
    private $connection;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function subscribe(SubscriptionEntityInterface $subscriptionEntity, string $accessToken): SubscriptionResponseEntityInterface
    {
        $url = static::getUri();
        $requestOptions = new RequestOptions($url, RequestType::Post(), ['token' => $accessToken]);
        $this->addDefaultHeaders($requestOptions);

        $subscriptionEntityJson = $subscriptionEntity->jsonSerialize();

        $response = $this->getConnection()
            ->createClient()
            ->request($requestOptions->getMethod(), $url, [
                'headers' => $requestOptions->getHeaders(),
                'json' => $subscriptionEntityJson
            ]);

        $responseArray = ResponseHandler::toArray($response);
        if (count($responseArray)) {
            return new SubscriptionResponse($responseArray);
        }

        throw new SubscribeFailedException('Unable to subscribe to push notification at this time.');
    }

    public function renew(string $subscriptionId, string $accessToken, array $args = []): SubscriptionResponseEntityInterface
    {
        $url = static::getUri() . DIRECTORY_SEPARATOR . $subscriptionId;
        $requestOptions = new RequestOptions($url, RequestType::Patch(), ['token' => $accessToken]);
        $this->addDefaultHeaders($requestOptions);

        $response = $this->getConnection()
            ->createClient()
            ->request($requestOptions->getMethod(), $url, [
                'headers' => $requestOptions->getHeaders(),
                'json' => [
                    '@odata.type' => $args['type'] ?? SubscriptionEntity::DEFAULT_DATA_TYPE
                ]
            ]);

        $responseArray = ResponseHandler::toArray($response);
        if (count($responseArray)) {
            return new SubscriptionResponse($responseArray);
        }

        throw new SubscribeFailedException('Unable to renew subscription to push notification at this time.');
    }

    public function delete(string $subscriptionId, string $accessToken): bool
    {
        $url = static::getUri() . DIRECTORY_SEPARATOR . $subscriptionId;
        $requestOptions = new RequestOptions($url, RequestType::Delete(), ['token' => $accessToken]);
        $this->addDefaultHeaders($requestOptions);

        $response = $this->getConnection()
            ->createClient()
            ->request($requestOptions->getMethod(), $url, [
                'headers' => $requestOptions->getHeaders()
            ]);

        if ($response->getStatusCode() === 204) {
            return true;
        }

        return false;
    }

    public function getConnection(): ConnectionInterface
    {
        if (!$this->connection instanceof ConnectionInterface) {
            $this->connection = new Connection($this->logger);
        }

        return $this->connection;
    }

    public function setConnection(ConnectionInterface $connection): SubscriptionInterface
    {
        $this->connection = $connection;
        return $this;
    }

    // Mark Protected
    protected function addDefaultHeaders(RequestOptionsInterface $requestOptions)
    {
        $requestOptions->addHeader('Content-Type', 'application/json');
        $requestOptions->addHeader('client-request-id', (Uuid::uuid4())->toString());
        $requestOptions->addHeader('Authorization', $requestOptions->getAccessToken());
    }

    // Mark Static
    protected static function getUri()
    {
        return static::AUTHORITY . DIRECTORY_SEPARATOR . static::COMMON . DIRECTORY_SEPARATOR . static::VERSION . DIRECTORY_SEPARATOR . static::SUBSCRIPTION_URL;
    }
}
