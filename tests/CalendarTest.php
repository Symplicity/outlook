<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Generated\Models\DateTimeTimeZone;
use Microsoft\Graph\Generated\Models\Event as MsEvent;
use Microsoft\Graph\Generated\Models\OpenTypeExtension;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderGetQueryParameters;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Exception\ReadError;
use Symplicity\Outlook\Models\Event as Event;
use Symplicity\Outlook\Tests\resources\OutlookTestHandler;
use Symplicity\Outlook\Utilities\CalendarView\CalendarViewParams;

class CalendarTest extends TestCase
{
    use GuzzleHttpTransactionTestTrait;

    protected OutlookTestHandler $stub;
    protected LoggerInterface $logger;

    public function setUp(): void
    {
        $logger = new Logger('outlook_calendar');
        $logger->pushHandler(new NullHandler());

        $this->stub = new OutlookTestHandler(
            clientId: 'foo',
            clientSecret: 'bar',
            token: 'fooToken',
            args: ['logger' => $logger]
        );

        $this->stub->setTestCase($this);
        $this->logger = $logger;
    }

    public function testGetEvent()
    {
        $singleEventStream = OutlookTestHandler::getSingleInstanceInJsonFormat();

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($singleEventStream))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $filters = "Id eq 'com.symplicity.test'";
        $extensions = 'Extensions($filter=' . $filters . ')';

        $that = $this;
        $params = new EventItemRequestBuilderGetQueryParameters();
        $params->expand = [$extensions];
        $entity = $this->stub->getEventBy(
            'AAA==',
            params: $params,
            beforeReturn: fn (Reader $entity, MsEvent $event) => $that->assertSame('AAA==', $event->getId()),
            args: ['client' => $client]
        );

        $this->assertInstanceOf(Reader::class, $entity);
        $this->assertSame('AAA==', $entity->getId());

        $this->expectExceptionCode(0);
        $this->stub->getEventBy(
            'ACC==',
            params: $params,
            args: ['client' => $client]
        );
    }

    public function testGetEventInstances()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor(OutlookTestHandler::getEventInstancesInJsonFormat())),
            new Response(200, [], Utils::streamFor('{}'))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->stub->setIsInstancesCall()
            ->setSeriesMasterId('foo==')
            ->getEventInstances('foo==', args: ['client' => $client]);

        $this->stub->reset();
    }

    public function testExceptionOnEventInstances()
    {
        $errorMsg = 'Error Communicating with Server';
        $mock = new MockHandler([
            new RequestException($errorMsg, new GuzzleRequest('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $this->expectExceptionObject(new ReadError($errorMsg, 500));
        $this->stub->getEventInstances('foo==', args: ['client' => $client]);
    }

    public function testUpsertEvent()
    {
        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], '{}'),
            new RequestException('Error Communicating with Server', new GuzzleRequest('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $container = [];
        $client = self::getClientWithTransactionHandler($container, $mock);

        $start = new DateTimeTimeZone();
        $start->setTimeZone('Eastern Standard Time');
        $start->setDateTime('2023-12-05 13:00:00');

        $end = new DateTimeTimeZone();
        $end->setTimeZone('Eastern Standard Time');
        $end->setDateTime('2023-12-05 14:00:00');

        $event = new Event();
        $event->setSubject('test');
        $event->setStart($start);
        $event->setEnd($end);

        $this->stub->upsert($event, ['client' => $client]);

        /** @var GuzzleRequest  $request */
        $request = $container[0]['request'] ?? null;
        $this->assertSame('POST', $request->getMethod());
        $this->assertNotEmpty($request->getHeader('authorization'));

        $contents = $request->getBody()->getContents();
        $this->assertJsonStringEqualsJsonString('{"@odata.type":"#microsoft.graph.event","end":{"dateTime":"2023-12-05 14:00:00","timeZone":"Eastern Standard Time"},"start":{"dateTime":"2023-12-05 13:00:00","timeZone":"Eastern Standard Time"},"subject":"test"}', $contents);

        $this->expectException(ReadError::class);
        $this->stub->upsert($event, ['client' => $client]);
    }

    public function testDeleteEvent()
    {
        $mock = new MockHandler([
            new Response(204, ['Content-Type' => 'application/json'], '{}'),
            new RequestException('Error Communicating with Server', new Request('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $container = [];
        $client = self::getClientWithTransactionHandler($container, $mock);

        $this->stub->delete('ABC==', ['client' => $client]);

        /** @var GuzzleRequest  $request */
        $request = $container[0]['request'] ?? null;
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertNotEmpty($request->getHeader('authorization'));

        $uri = $request->getUri()->getPath();
        $this->assertSame('/v1.0/users/me-token-to-replace/events/ABC%3D%3D', $uri);
    }

    public function testPullEvents()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($this->getEvents()[0])),
            new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($this->getEvents()[1])),
        ]);

        $container = [];
        $client = self::getClientWithTransactionHandler($container, $mock);

        $params = new CalendarViewParams();
        $params->setStartDateTime('2023-11-30T00:00:00-05:00')
            ->setPreferHeaders('odata.maxpagesize=1')
            ->setEndDateTime('2023-12-06T23:59:59-05:00');

        $that = $this;
        $this->stub->pull(
            $params,
            fn (string $link) => $that->assertMatchesRegularExpression('/deltatoken/', $link),
            args: ['client' => $client]
        );

        /** @var GuzzleRequest  $request */
        $request = $container[0]['request'] ?? null;
        $this->assertSame('GET', $request->getMethod());
        $this->assertNotEmpty($request->getHeader('authorization'));

        $query = $request->getUri()->getQuery();
        $this->assertSame('startDateTime=2023-11-30T00%3A00%3A00-05%3A00&endDateTime=2023-12-06T23%3A59%3A59-05%3A00', $query);

        /** @var GuzzleRequest  $request */
        $request = $container[1]['request'] ?? null;
        $this->assertSame('GET', $request->getMethod());
        $this->assertNotEmpty($request->getHeader('authorization'));

        $query = $request->getUri()->getQuery();
        $this->assertSame('$skiptoken=foo_skipToken', $query);
    }

    public function testPullEventsError()
    {
        $mock = new MockHandler([
            new RequestException('Error Communicating with Server', new Request('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $params = new CalendarViewParams();
        $params->setStartDateTime('2023-11-30T00:00:00-05:00')
            ->setPreferHeaders('odata.maxpagesize=1')
            ->setEndDateTime('2023-12-06T23:59:59-05:00');

        $this->expectExceptionMessage('Error Communicating with Server');
        $this->stub->pull(
            $params,
            args: ['client' => $client]
        );
    }

    public function testPushEvents()
    {
        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], Utils::streamFor(json_encode($this->getBatchResponse()))),
        ]);

        $container = [];
        $client = self::getClientWithTransactionHandler($container, $mock);

        $this->stub->push(args: ['client' => $client]);
    }

    public function testPatchExtension()
    {
        $jsonBody = '{"@odata.context":"https://graph.microsoft.com/v1.0/$metadata#users(\'foo\')/events(\'abc==\')/extensions/$entity","@odata.type":"#microsoft.graph.openTypeExtension","id":"Microsoft.OutlookServices.OpenTypeExtension.foo.bar","extensionName":"foo.bar","internalId":"123"}';

        $stream = Utils::streamFor($jsonBody);

        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], $stream),
        ]);

        $container = [];
        $client = self::getClientWithTransactionHandler($container, $mock);

        $extension = new OpenTypeExtension();
        $extension->setExtensionName('foo.bar');
        $extension->setAdditionalData([
            'id' => 'foo'
        ]);

        $actual = $this->stub->patchExtensionForEvent('abc==', $extension, ['client' => $client]);
        $this->assertTrue($actual);
    }

    public function testGetExtensionBy()
    {
        $jsonBody = '{"@odata.context":"https://graph.microsoft.com/v1.0/$metadata#users(\'foo_bar\')/events(\'abc==\')/extensions/$entity","@odata.type":"#microsoft.graph.openTypeExtension","id":"Microsoft.OutlookServices.OpenTypeExtension.symplicity.test","extensionName":"symplicity.test","id":"123","series_master":true}';

        $stream = Utils::streamFor($jsonBody);

        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], $stream),
        ]);

        $container = [];
        $client = self::getClientWithTransactionHandler($container, $mock);

        $extension = $this->stub->getExtensionBy('symplicity.test', 'abc==', args: ['client' => $client]);
        $this->assertInstanceOf(OpenTypeExtension::class, $extension);
        $this->assertTrue($extension->getAdditionalData()['series_master']);
        $this->assertSame('123', $extension->getId());
    }

    protected function getEvents(): array
    {
        return [
            '{"@odata.context":"https:\/\/graph.microsoft.com\/v1.0\/$metadata#Collection(event)","value":[{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==\"","id":"1==","createdDateTime":"2023-12-05T06:17:55.551725Z","lastModifiedDateTime":"2023-12-05T06:17:56.9028469Z","changeKey":"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==","categories":[],"transactionId":"eea2822c-5583-8a5a-a074-2f3f0d75f042","originalStartTimeZone":"Eastern Standard Time","originalEndTimeZone":"Eastern Standard Time","iCalUId":"040000008200E00074C5B7101A82E00800000000695B10C94227DA0100000000000000001000000098F5720C81F7EF4EA03A9B578D28E7DF","reminderMinutesBeforeStart":15,"isReminderOn":true,"hasAttachments":false,"subject":"R - 1","bodyPreview":"test","importance":"normal","sensitivity":"normal","isAllDay":false,"isCancelled":false,"isOrganizer":true,"responseRequested":true,"seriesMasterId":null,"showAs":"busy","type":"seriesMaster","webLink":"https:\/\/outlook.office365.com\/owa\/?itemid=1==&exvsurl=1&path=\/calendar\/item","onlineMeetingUrl":null,"isOnlineMeeting":false,"onlineMeetingProvider":"unknown","allowNewTimeProposals":true,"occurrenceId":null,"isDraft":false,"hideAttendees":false,"responseStatus":{"response":"organizer","time":"0001-01-01T00:00:00Z"},"start":{"dateTime":"2023-12-05T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-05T07:30:00.0000000","timeZone":"UTC"},"location":{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"},"locations":[{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"}],"recurrence":{"pattern":{"type":"daily","interval":1,"month":0,"dayOfMonth":0,"firstDayOfWeek":"sunday","index":"first"},"range":{"type":"endDate","startDate":"2023-12-05","endDate":"2023-12-07","recurrenceTimeZone":"Eastern Standard Time","numberOfOccurrences":0}},"attendees":[],"organizer":{"emailAddress":{"name":"Foo Test","address":"foo@symplicity.com"}},"onlineMeeting":null},{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"DwAAABYAAADsMG1Lfqh6SqUVUv+VbespAAALd6vn\"","id":"2==","seriesMasterId":"1==","type":"occurrence","start":{"dateTime":"2023-12-05T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-05T07:30:00.0000000","timeZone":"UTC"}},{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"DwAAABYAAADsMG1Lfqh6SqUVUv+VbespAAALd6vn\"","id":"3==","seriesMasterId":"1==","type":"occurrence","start":{"dateTime":"2023-12-06T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-06T07:30:00.0000000","timeZone":"UTC"}}],"@odata.nextLink":"https:\/\/graph.microsoft.com\/v1.0\/me\/calendarView\/delta?$skiptoken=foo_skipToken"}',
            '{"@odata.context":"https:\/\/graph.microsoft.com\/v1.0\/$metadata#Collection(event)","value":[],"@odata.deltaLink":"https:\/\/graph.microsoft.com\/v1.0\/me\/calendarView\/delta?$deltatoken=foo_deltaToken"}'
        ];
    }

    protected function getBatchResponse(): array
    {
        return  [
            'responses' => [
                '12345' => [
                    'id' => '123',
                    'status' => 201,
                    'headers' => [
                        'Cache-Control' => 'private',
                        'Content-Type' => 'application/json; charset=utf-8',
                    ],
                    'body' => \json_decode(OutlookTestHandler::getSingleInstanceInJsonFormat(), true)
               ],
                '123-del' => [
                    'id' => '123-del',
                    'status' => 204,
                    'headers' => [
                        'Cache-Control' => 'private',
                        'Content-Type' => 'application/json; charset=utf-8',
                    ],
                    'body' => []
                ]
            ]
        ];
    }
}
