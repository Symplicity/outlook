<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Notification;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\NotificationReaderEntity;
use Symplicity\Outlook\Interfaces\CalendarInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Utilities\ChangeType;
use function GuzzleHttp\Psr7\stream_for;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Calendar;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\Request;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Notification\Receiver;
use Symplicity\Outlook\Utilities\RequestType;

class ReceiverTest extends TestCase
{
    private $logger;
    private $receiverStub;
    private $connection;

    protected function setUp()
    {
        $this->logger = new Logger('outlook_calendar');
        $this->logger->pushHandler(new NullHandler());
        $this->connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([$this->logger])
            ->setMethods(['createClient', 'createClientWithRetryHandler'])
            ->getMock();

        $this->receiverStub = $this->getMockForAbstractClass(Receiver::class, [], '', true, true, true, ['validate', 'didWrite', 'willWrite', 'eventWriteFailed']);
    }

    public function testExec()
    {
        $clientState = '123-345';
        $mock = new MockHandler([
            new Response(200, ['Clientstate' => $clientState], stream_for($this->getStream())),
            new Response(200, ['Clientstate' => $clientState], stream_for($this->getStream())),
            new Response(400, [], ''),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->exactly(4))->method('createClientWithRetryHandler')->willReturn($client);

        $calendarStub = $this->getMockForAbstractClass(Calendar::class, [
            'fooToken',
            [
                'logger' => $this->logger,
                'request' => new Request('fooTest', [
                    'requestOptions' => function (string $url, RequestType $methodType, array $args = []) {
                        return new RequestOptions($url, $methodType, $args);
                    },
                    'connection' => $this->connection
                ])
            ]
        ], '', true, true, true, ['handlePoolResponses', 'saveEventLocal']);

        $reader = (new Reader())
            ->hydrate(json_decode($this->getStream(), true));

        $oData = $this->getOData() + ['state' => $clientState];
        $oData['value'] = array_merge($oData['value'], [new NotificationReaderEntity([
            '@odata.type' => '#Microsoft.OutlookServices.Notification',
            'Id' => null,
            'SubscriptionId' => 'ABC==',
            'SubscriptionExpirationDateTime' => '2020-09-23T13:58:53.708556Z',
            'SequenceNumber' => 1,
            'ChangeType' => 'Updated',
            'Resource' => 'https://outlook.office.com/api/v2.0/Users(\'123\')/Events(\'CDE==\')',
            'ResourceData' => [
                '@odata.type' => '#Microsoft.OutlookServices.Event',
                '@odata.id' => 'https://outlook.office.com/api/v2.0/Users(\'123\')/Events(\'CDE==\')',
                '@odata.etag' => 'W/"123"',
                'Id' => 'ACX2nRLAAAAA=='
            ]
        ])]);

        $this->receiverStub->hydrate($oData);
        $this->receiverStub->expects($this->exactly(4))->method('validate');

        $this->receiverStub->expects($this->exactly(4))
            ->method('willWrite')
            ->withConsecutive([$calendarStub, $this->logger, $this->receiverStub->getEntities()[0], ['skipParams' => true]], [$calendarStub, $this->logger, $this->receiverStub->getEntities()[1]]);

        $this->receiverStub->expects($this->exactly(2))
            ->method('didWrite')
            ->withConsecutive([$calendarStub, $this->logger, $reader, $this->receiverStub->getEntities()[0]], [$calendarStub, $this->logger, $reader, $this->receiverStub->getEntities()[1]]);

        $this->checkEntity($this->receiverStub->getEntities()[0]);
        $this->checkEntity($this->receiverStub->getEntities()[1]);
        $this->receiverStub->exec($calendarStub, $this->logger, ['skipParams' => true]);
        $this->assertEquals($clientState, $this->receiverStub->getState());

        $this->receiverStub->expects($this->exactly(2))->method('eventWriteFailed');
        $this->receiverStub->exec($calendarStub, $this->logger, ['skipParams' => true]);
    }

    public function testValidateException()
    {
        $calendarStub = $this->getMockForAbstractClass(Calendar::class, [], '', false);
        $oData['value'] = [new NotificationReaderEntity([
            '@odata.type' => '#Microsoft.OutlookServices.Notification',
            'Id' => null,
            'SubscriptionExpirationDateTime' => '2020-09-23T13:58:53.708556Z',
            'SequenceNumber' => 1,
            'ChangeType' => 'Updated',
            'Resource' => 'https://outlook.office.com/api/v2.0/Users(\'123\')/Events(\'CDE==\')',
            'ResourceData' => [
                '@odata.type' => '#Microsoft.OutlookServices.Event',
                '@odata.id' => 'https://outlook.office.com/api/v2.0/Users(\'123\')/Events(\'CDE==\')',
                '@odata.etag' => 'W/"123"',
                'Id' => 'ACX2nRLAAAAA=='
            ]
        ])];

        $this->connection->expects($this->never())->method('createClientWithRetryHandler');
        $receiverStub = $this->getMockForAbstractClass(Receiver::class, [], '', true, true, true, []);
        $receiverStub->hydrate($oData);
        $receiverStub->expects($this->exactly(1))->method('eventWriteFailed');
        $receiverStub->exec($calendarStub, $this->logger, ['skipParams' => true]);
    }

    public function testExecExceptions()
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([$this->logger])
            ->setMethods(['createClient', 'createClientWithRetryHandler', 'get'])
            ->getMock();

        $calendarStub = $this->getMockForAbstractClass(Calendar::class, [
            'fooToken',
            [
                'logger' => $this->logger,
                'request' => new Request('fooTest', [
                    'requestOptions' => function (string $url, RequestType $methodType, array $args = []) {
                        return new RequestOptions($url, $methodType, $args);
                    },
                    'connection' => $connection
                ])
            ]
        ], '', true, true, true, ['handlePoolResponses', 'saveEventLocal']);

        $receivedFailedWrites = [];
        $logHandler = new TestHandler();
        $logger = new Logger('outlook_sync', [$logHandler]);
        $receiverStub = $this->getReceiverClass($receivedFailedWrites);
        $receiverStub->hydrate($this->getOData());

        $expectedUrl = 'https://outlook.office.com/api/v2.0/Users(\'123\')/Events(\'CDE==\')?$expand=Extensions($filter=Id%20eq%20\'Microsoft.OutlookServices.OpenTypeExtension.symplicitytest\')';
        $connection->expects($this->exactly(2))
            ->method('get')
            ->with($expectedUrl)
            ->willReturnOnConsecutiveCalls(new Response(200, [], $this->getStream()), new Response(200, [], function () {
            return [];
        }));

        $receiverStub->exec($calendarStub, $logger, ['skipParams' => true]);
        $receiverStub = $this->getReceiverClass($receivedFailedWrites);
        $receiverStub->hydrate($this->getOData());
        $receiverStub->exec($calendarStub, $logger, ['skipParams' => true]);

        $receiverStub->exec($calendarStub, $logger, ['skipParams' => true, 'setResourceToNull' => true]);
        $this->assertTrue($logHandler->hasRecordThatMatches('/Event did not process successfully/', Logger::WARNING));
        $this->assertNotEmpty($receivedFailedWrites);
    }

    protected function checkEntity(NotificationReaderEntity $entity)
    {
        $entity
            ->setSequenceNumber(2)
            ->setResource('test.com')
            ->setId('123')
            ->setSubscriptionId('ABC==');

        $json = $entity->jsonSerialize();
        $this->assertArrayHasKey('res', $json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('subId', $json);
        $this->assertArrayHasKey('cT', $json);
        $this->assertArrayHasKey('seq', $json);

        $this->assertTrue($entity->has('subscriptionId'));
        $this->assertFalse($entity->has('test'));

        $this->assertEquals(2, $entity->getSequenceNumber());
        $this->assertEquals($entity->getSubscriptionId(), $json['subId']);
        $this->assertEquals(ChangeType::updated, $json['cT']);

        $this->assertEquals('#Microsoft.OutlookServices.Notification', $entity->getType());
        $this->assertEquals('ABC==', $entity->getSubscriptionId());
        $this->assertEquals('2020-09-23T13:58:53.708556Z', $entity->getSubscriptionExpirationDateTime());
        $this->assertEquals('https://outlook.office.com/api/v2.0/Users(\'123\')/Events(\'CDE==\')', $entity->getODataId());
        $this->assertNull($entity->getOutlookId());
        $this->assertEquals('#Microsoft.OutlookServices.Event', $entity->getODataType());
        $this->assertEquals('W/"123"', $entity->getEtag());
        $this->assertEquals('123', $entity->getId());
        $this->assertEquals(ChangeType::updated, $entity->getChangeType());
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

    public function getStream(): string
    {
        return '{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'foo\')\/Events(\'x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=\')","@odata.etag":"W\/\"ghc\/foo\/\/pA==\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","CreatedDateTime":"2019-02-01T18:05:03.7354577-05:00","LastModifiedDateTime":"2019-02-04T23:58:49.478552-05:00","ChangeKey":"foo\/\/pA==","Categories":[],"OriginalStartTimeZone":"Eastern Standard Time","OriginalEndTimeZone":"Eastern Standard Time","iCalUId":"foo","ReminderMinutesBeforeStart":15,"IsReminderOn":true,"HasAttachments":false,"Subject":"FooBar","BodyPreview":"CCCCCCC","Importance":"Normal","Sensitivity":"Normal","IsAllDay":true,"IsCancelled":false,"IsOrganizer":false,"ResponseRequested":true,"SeriesMasterId":null,"ShowAs":"Free","Type":"SeriesMaster","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=foo%3D&exvsurl=1&path=\/calendar\/item","OnlineMeetingUrl":null,"ResponseStatus":{"Response":"Accepted","Time":"2019-02-01T18:05:25.680242-05:00"},"Body":{"ContentType":"HTML","Content":"test"},"Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"India","PostalCode":""},"Coordinates":{"Latitude":27.6031,"Longitude":88.6468}},"Locations":[{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"US","PostalCode":""},"Coordinates":{"Latitude":32.6031,"Longitude":999.6468}}],"Recurrence":{"Pattern":{"Type":"Daily","Interval":1,"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First"},"Range":{"Type":"EndDate","StartDate":"2019-02-25","EndDate":"2019-02-28","RecurrenceTimeZone":"Eastern Standard Time","NumberOfOccurrences":0}},"Attendees":[{"Type":"Required","Status":{"Response":"None","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}},{"Type":"Required","Status":{"Response":"Accepted","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Insight Test","Address":"test"}}],"Organizer":{"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}}}';
    }

    protected function getReceiverClass(array &$receivedFailedWrites)
    {
        return new class($receivedFailedWrites) extends Receiver {
            public $receivedFailedWrites;

            public function __construct(array &$receivedFailedWrites)
            {
                $this->receivedFailedWrites = &$receivedFailedWrites;
            }

            protected function validateSequenceNumber(
                CalendarInterface $calendar,
                LoggerInterface $logger,
                NotificationReaderEntity $entity
            ): void {
            }

            protected function eventWriteFailed(CalendarInterface $calender, LoggerInterface $logger, array $info): void
            {
                $this->receivedFailedWrites[] = $info;
            }

            protected function willWrite(
                CalendarInterface $calendar,
                LoggerInterface $logger,
                NotificationReaderEntity $notificationReaderEntity,
                array &$params = []
            ): void {
                if (isset($params['setResourceToNull'])) {
                    $notificationReaderEntity->setResource(null);
                    return;
                }
                $originalResource = $notificationReaderEntity->getResource();
                $filters = rawurlencode("Id eq ") . '\'Microsoft.OutlookServices.OpenTypeExtension.symplicitytest\'';
                $originalResource .= '?$expand=Extensions($filter=' . $filters . ')';
                $notificationReaderEntity->setResource($originalResource);
            }

            protected function didWrite(
                CalendarInterface $calendar,
                LoggerInterface $logger,
                ?ReaderEntityInterface $entity,
                NotificationReaderEntity $notificationReaderEntity,
                array $args = []
            ): void {
                // TODO: Implement didWrite() method.
            }
        };
    }
}
