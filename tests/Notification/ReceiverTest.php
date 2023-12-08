<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Notification;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\NotificationReaderEntity;
use Symplicity\Outlook\Tests\GuzzleHttpTransactionTestTrait;
use Symplicity\Outlook\Tests\resources\OutlookTestHandler;
use Symplicity\Outlook\Tests\resources\ReceiverTestHandler;
use Symplicity\Outlook\Utilities\ChangeType;

class ReceiverTest extends TestCase
{
    use GuzzleHttpTransactionTestTrait;

    private array $container = [];
    private LoggerInterface $logger;
    private ReceiverTestHandler $receiverStub;
    private array $receivedFailedWrites = [];
    private ?TestHandler $logHandler = null;

    protected function setUp(): void
    {
        $this->logHandler = new TestHandler();
        $this->logger = new Logger('outlook_calendar', [$this->logHandler]);
        $this->receiverStub = new ReceiverTestHandler($this->receivedFailedWrites, $this);
    }

    public function testExec()
    {
        $event = OutlookTestHandler::getSingleInstanceInJsonFormat();
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($event)),
            new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($event)),
            new Response(400, [], ''),
        ]);

        $client = $this->getClientWithTransactionHandler($this->container, $mock);

        $clientState = '123-345';
        $calendarStub = new OutlookTestHandler('foo', 'bar', 'token', ['logger' => $this->logger]);

        $data = [
            'value' => [
                "subscriptionId" => "sub_1",
                "subscriptionExpirationDateTime" => "2023-12-10T18:23:45+00:00",
                "changeType" => "updated",
                "resource" => "Users/foo_1/Events/event_1==",
                "resourceData" => [
                    "@odata.type" => "#Microsoft.Graph.Event",
                    "@odata.id" => "Users/foo_1/Events/event_1==",
                    "@odata.etag" => "W/event_1_etag",
                    "id" => "event_1=="
                ],
                "clientState" => $clientState,
                "tenantId" => "tenant_id_1"
            ]
        ];

        $entity = new NotificationReaderEntity($data['value']);

        $this->assertSame('W/event_1_etag', $entity->getEtag());
        $this->assertSame('Users/foo_1/Events/event_1==', $entity->getODataId());
        $this->assertSame('#Microsoft.Graph.Event', $entity->getODataType());
        $this->assertSame('2023-12-10T18:23:45+00:00', $entity->getSubscriptionExpirationDateTime());
        $this->assertSame('tenant_id_1', $entity->getTenantId());

        $this->receiverStub->hydrate([$entity]);
        $this->receiverStub->exec(
            $calendarStub,
            $this->logger,
            ['skipParams' => true],
            ['client' => $client]
        );

        $this->assertTrue($this->logHandler->hasRecordThatMatches('/Getting event by id .../', Logger::INFO));
        $this->assertTrue($this->logHandler->hasRecordThatMatches('/Getting event by id complete .../', Logger::INFO));
        $this->assertEmpty($this->receivedFailedWrites);
        $this->logHandler?->reset();

        unset($data['value']['resource']);
        $entity = new NotificationReaderEntity($data['value']);
        $this->receiverStub->reset();
        $this->receiverStub->hydrate([$entity]);
        $this->receiverStub->exec(
            $calendarStub,
            $this->logger,
            ['skipParams' => true],
            ['client' => $client]
        );

        $this->assertTrue($this->logHandler->hasRecordThatMatches('/Event did not process successfully/', Logger::ERROR));
        $this->assertNotEmpty($this->receivedFailedWrites);
        $this->receivedFailedWrites = [];
    }

    public function testEntity(): void
    {
        $entity = new NotificationReaderEntity();
        $entity->setResource('test.com')
            ->setId('123')
            ->setChangeType(ChangeType::UPDATED->value)
            ->setSubscriptionId('ABC==');

        $json = $entity->jsonSerialize();
        $this->assertArrayHasKey('res', $json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('subId', $json);
        $this->assertArrayHasKey('cT', $json);

        $this->assertTrue($entity->has('subscriptionId'));
        $this->assertFalse($entity->has('test'));
        $this->assertEquals(ChangeType::UPDATED, $entity->getChangeType());
    }

    protected function getOData(): array
    {
        return [
            '@odata.context' => 'https://outlook.office.com/api/v2.0/$metadata#Events/Microsoft.OutlookServices.NotificationBase',
            'value' => [
                [
                    '@odata.type' => '#Microsoft.OutlookServices.Notification',
                    'Id' => null,
                    'SubscriptionId' => 'ABC==',
                    'SubscriptionExpirationDateTime' => '2020-09-23T13:58:53.708556Z',
                    'SequenceNumber' => 1,
                    'ChangeType' => 'Updated',
                    'Resource' => 'https://outlook.office.com/api/v2.0/Users(\'123\')/Events(\'CDE==\')',
                    'ResourceData' =>
                        [
                            '@odata.type' => '#Microsoft.OutlookServices.Event',
                            '@odata.id' => 'https://outlook.office.com/api/v2.0/Users(\'123\')/Events(\'CDE==\')',
                            '@odata.etag' => 'W/"123"',
                            'Id' => 'ACX2nRLAAAAA==',
                        ],
                    ]
                ]
            ];
    }
}
