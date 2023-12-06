<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Notification;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Generated\Models\Subscription as MsSubscription;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Exception\SubscribeFailedException;
use Symplicity\Outlook\Notification\Subscription;
use Symplicity\Outlook\Tests\GuzzleHttpTransactionTestTrait;
use Symplicity\Outlook\Utilities\ChangeType;

class SubscriptionTest extends TestCase
{
    use GuzzleHttpTransactionTestTrait;

    private array $container;
    private LoggerInterface $logger;

    public function setUp(): void
    {
        $this->container = [];
        $this->logger = new Logger('outlook_calendar');
        $this->logger->pushHandler(new NullHandler());
    }

    public function testSubscribe()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor(json_encode($this->getSubscriptionResponse()))),
            new Response(200, ['Content-Type' => 'application/json'], ''),
            new RequestException('Error Communicating with Server', new Request('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $client = $this->getClientWithTransactionHandler($this->container, $mock);

        $subscriptionEntity = new MsSubscription();
        $subscriptionEntity->setClientState('123-333');
        $subscriptionEntity->setNotificationUrl('https://test12.symplicity.com/api/v1/outlook');
        $subscriptionEntity->setResource('/me/events');
        $subscriptionEntity->setChangeType(sprintf('%s,%s,%s', ChangeType::CREATED->value, ChangeType::UPDATED->value, ChangeType::DELETED->value));

        $subscriber = new Subscription('foo', 'bar', 'token_foo', ['logger' => $this->logger]);
        $subscriptionResponse = $subscriber->subscribe($subscriptionEntity, ['client' => $client]);

        $this->assertInstanceOf(MsSubscription::class, $subscriptionResponse);
        $this->assertEquals('ABC==', $subscriptionResponse->getId());
        $this->assertNotEmpty($subscriptionResponse->getClientState());
        $this->assertInstanceOf(\DateTime::class, $subscriptionResponse->getExpirationDateTime());

        $this->assertCount(1, $this->container);

        /** @var Request $request */
        $request = $this->container[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1.0/subscriptions', $request->getUri()->getPath());
        $this->assertJsonStringEqualsJsonString('{"changeType":"created,updated,deleted","clientState":"123-333","notificationUrl":"https://test12.symplicity.com/api/v1/outlook","resource":"/me/events"}', $request->getBody()->getContents());

        $this->container = [];

        $this->expectException(SubscribeFailedException::class);
        $subscriber->subscribe($subscriptionEntity, ['client' => $client]);

        $this->expectException(RequestException::class);
        $subscriber->subscribe($subscriptionEntity, ['client' => $client]);
    }

    public function testRenewSubscription()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor(json_encode($this->getSubscriptionResponse()))),
            new Response(200, ['Content-Type' => 'application/json'], ''),
            new RequestException('Error Communicating with Server', new Request('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $client = $this->getClientWithTransactionHandler($this->container, $mock);

        $expiration = new \DateTime('2025-04-10');
        $subscriber = new Subscription('foo', 'bar', 'token_foo', ['logger' => $this->logger]);
        $subscriptionResponse = $subscriber->renew('ABC==', $expiration, ['client' => $client]);
        $this->assertInstanceOf(MsSubscription::class, $subscriptionResponse);
        $this->assertEquals('ABC==', $subscriptionResponse->getId());
        $this->assertNotEmpty($subscriptionResponse->getClientState());

        $this->assertCount(1, $this->container);

        /** @var Request $request */
        $request = $this->container[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/v1.0/subscriptions/ABC%3D%3D', $request->getUri()->getPath());
        $this->assertJsonStringEqualsJsonString('{"expirationDateTime": "2025-04-10T00:00:00-04:00"}', $request->getBody()->getContents());

        $this->container = [];

        $this->expectException(SubscribeFailedException::class);
        $subscriber->renew('ABC==', $expiration, ['client' => $client]);

        $this->expectException(RequestException::class);
        $subscriber->renew('ABC==', $expiration, ['client' => $client]);
    }

    public function testDeleteSubscription()
    {
        $mock = new MockHandler([
            new Response(204, ['Content-Type' => 'application/json'], '{}'),
            new Response(404, [], ''),
        ]);

        $client = $this->getClientWithTransactionHandler($this->container, $mock);
        $subscriber = new Subscription('foo', 'bar', 'token_foo', ['logger' => $this->logger]);
        $subscriber->delete('ABC==', ['client' => $client]);

        /** @var Request $request */
        $request = $this->container[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/v1.0/subscriptions/ABC%3D%3D', $request->getUri()->getPath());

        $this->container = [];

        $this->expectException(SubscribeFailedException::class);
        $subscriber->delete('ABC==', ['client' => $client]);
    }

    public function getSubscriptionResponse(): array
    {
        return  [
            '@odata.context' => 'https://graph.microsoft.com/v1.0/$metadata#subscriptions/$entity',
            'id' => 'ABC==',
            'resource' => 'me/events',
            'applicationId' => 'Foo==',
            'changeType' => 'created,updated,deleted',
            'clientState' => '123-333',
            'notificationUrl' => 'https://test12.symplicity.com/api/v1/outlook',
            'notificationQueryOptions' => null,
            'lifecycleNotificationUrl' => 'https://test12.symplicity.com/api/v1/outlook-cycle',
            'expirationDateTime' => '2023-12-10T18:23:45Z',
            'creatorId' => 'creator==',
            'includeResourceData' => null,
            'latestSupportedTlsVersion' => 'v1_2',
            'encryptionCertificate' => null,
            'encryptionCertificateId' => null,
            'notificationUrlAppId' => null,
        ];
    }
}
