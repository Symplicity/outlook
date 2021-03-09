<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Symplicity\Outlook\Exception\ConnectionException;
use Symplicity\Outlook\Exception\ReadError;
use Symplicity\Outlook\Http\Batch;
use function GuzzleHttp\Psr7\stream_for;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Calendar;
use Symplicity\Outlook\Entities\Delete;
use Symplicity\Outlook\Entities\ODateTime;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Entities\Writer;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\Request;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Utilities\RequestType;

class CalendarTest extends TestCase
{
    protected $stub;
    protected $connection;
    protected $batchConnection;
    protected $request;
    protected $logger;

    public function setUp() : void
    {
        $logger = new Logger('outlook_calendar');
        $logger->pushHandler(new NullHandler());
        $this->connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([$logger])
            ->onlyMethods(['createClient', 'createClientWithRetryHandler'])
            ->getMock();

        $batchConnection = $this->batchConnection = $this->getMockBuilder(Batch::class)
            ->setConstructorArgs([$logger])
            ->onlyMethods(['createClient', 'createClientWithRetryHandler'])
            ->getMock();

        $this->request = new Request('fooTest', [
            'requestOptions' => function (string $url, RequestType $methodType, array $args = []) {
                return new RequestOptions($url, $methodType, $args);
            },
            'connection' => $this->connection,
            'batchConnectionHandler' => function() use ($batchConnection) {
                return $batchConnection;
            }
        ]);

        $this->stub = $this->getMockForAbstractClass(Calendar::class, [
            'fooToken', [
                'logger' => $logger,
                'request' => $this->request
            ]
        ], '', true, true, true, ['handleBatchResponse', 'saveEventLocal']);

        $this->logger = $logger;
    }

    public function testGetEvent()
    {
        $responses = json_decode($this->getStream(), true);
        $singleEventStream = json_encode($responses['value'][0]);
        $occurrenceStream = json_encode($responses['value'][1]);
        $deleteStream = json_encode($responses['value'][4]);

        $mock = new MockHandler([
            new Response(200, [], stream_for($singleEventStream)),
            new Response(200, [], stream_for($occurrenceStream)),
            new Response(200, [], stream_for($deleteStream)),
            new Response(200, [], stream_for('{}'))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->exactly(4))->method('createClientWithRetryHandler')->willReturn($client);

        $this->stub->expects($this->once())->method('saveEventLocal');
        $this->stub->expects($this->once())->method('deleteEventLocal');

        $this->stub->getEvent('/events/ABC==', ['skipParams' => true, 'skipOccurrences' => true]);
        $this->stub->getEvent('/events/ABC==', ['skipParams' => true, 'skipOccurrences' => true]);
        $this->stub->getEvent('/events/ABC==', ['skipParams' => true, 'skipOccurrences' => true]);

        $this->expectException(ReadError::class);
        $this->stub->getEvent('/events/ABC==', ['skipParams' => true, 'skipOccurrences' => true]);
    }

    public function testGetEventInstances()
    {
        $mock = new MockHandler([
            new Response(200, [], stream_for($this->getEventInstancesStream())),
            new Response(200, [], stream_for('{}'))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->exactly(2))->method('createClientWithRetryHandler')->willReturn($client);

        $this->stub->expects($this->exactly(4))->method('saveEventLocal');
        $this->stub->expects($this->exactly(1))->method('deleteEventLocal');

        $this->stub->getEventInstances('/events/123/instances', ['skipParams' => true]);

        $this->expectException(ReadError::class);
        $this->stub->getEventInstances('/events/123/instances', ['skipParams' => true]);
    }

    public function testExceptionOnEventInstances()
    {
        $mock = new MockHandler([
            new Response(200, [], stream_for($this->getEventInstancesStream())),
            new RequestException('Error Communicating with Server', new \GuzzleHttp\Psr7\Request('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->exactly(2))->method('createClientWithRetryHandler')->willReturn($client);

        $this->stub->expects($this->once())->method('saveEventLocal');
        $this->stub->expects($this->once())->method('deleteEventLocal');

        $this->stub->getEventInstances('/events/123/instances', ['skipParams' => true, 'skipOccurrences' => true]);

        $this->expectException(ReadError::class);
        $this->stub->getEventInstances('/events/123/instances', ['skipParams' => true]);
    }

    public function testUpsertEvent()
    {
        $mock = new MockHandler([
            new Response(200, [], stream_for($this->getStream())),
            new RequestException('Error Communicating with Server', new \GuzzleHttp\Psr7\Request('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->exactly(2))->method('createClient')->willReturn($client);

        $writer = (new Writer())
            ->setGuid('ABC')
            ->setId('foo')
            ->method(new RequestType(RequestType::Patch))
            ->setSubject('test')
            ->setInternalEventType('1')
            ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
            ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
            ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time'))
            ->setIsAllDay(true);


        $response = $this->stub->upsert($writer, ['skipOccurrences' => true]);
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $this->expectException(ConnectionException::class);
        $this->stub->upsert($writer, ['skipOccurrences' => true]);
    }

    public function testDeleteEvent()
    {
        $mock = new MockHandler([
            new Response(204, [], ''),
            new RequestException('Error Communicating with Server', new \GuzzleHttp\Psr7\Request('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->exactly(2))->method('createClient')->willReturn($client);

        $writer = new Delete('ABC==', 'intId123');
        $response = $this->stub->delete($writer);
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $this->expectException(ConnectionException::class);
        $this->stub->delete($writer);
    }

    public function testSync()
    {
        $mock = new MockHandler([
            new Response(200),
            new Response(200, [], stream_for($this->getStream())),
            new Response(200, [], stream_for($this->getStream())),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->batchConnection->expects($this->exactly(1))->method('createClientWithRetryHandler')->willReturn($client);
        $this->batchConnection->expects($this->never())->method('createClient');
        $this->connection->expects($this->exactly(2))->method('createClientWithRetryHandler')->willReturn($client);
        $this->connection->expects($this->never())->method('createClient');

        $this->stub->isBatchRequest();
        $this->stub->expects($this->once())->method('getLocalEvents')->willReturn([
            (new Writer())
                ->setId('bar')
                ->setSubject('test')
                ->method(new RequestType(RequestType::Get))
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time')),
            (new Delete('x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=', 'fooBar')),
            (new Writer())
                ->setId('foo')
                ->setSubject('test')
                ->method(new RequestType(RequestType::Get))
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time')),
            (new \stdClass())
        ]);

        $this->stub->expects($this->exactly(1))->method('handleBatchResponse');
        $this->stub->expects($this->exactly(8))->method('saveEventLocal');
        $this->stub->expects($this->exactly(2))->method('deleteEventLocal');

        $this->stub->sync([
            'endPoint' => 'me/calendarview',
            'queryParams' => [
                'startDateTime' => date("Y-m-d\TH:i:s", strtotime('2019-02-24')),
                'endDateTime' => date("Y-m-d\TH:i:s", strtotime('2019-02-24'))
            ]
        ]);

        $deltaTokenLink = $this->request->getResponseIterator()->getDeltaLink();
        $parsedUrl = parse_url($deltaTokenLink, PHP_URL_QUERY);
        parse_str($parsedUrl, $queryComponents);
        $token = $queryComponents['$deltatoken'] ?? $queryComponents['$deltaToken'] ?? null;
        $this->assertEquals('phpunit123==', $token);
    }

    public function testSyncError()
    {
        $mock = new MockHandler([
            new Response(200, [], stream_for($this->getStream())),
            new RequestException('Error Communicating with Server', new \GuzzleHttp\Psr7\Request('GET', 'test'), new Response(500, ['X-Foo' => 'Bar']))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->exactly(2))->method('createClientWithRetryHandler')->willReturn($client);
        $this->stub->expects($this->never())->method('handleBatchResponse');
        $this->stub->expects($this->once())->method('getLocalEvents')->willReturn([]);
        $this->stub->expects($this->exactly(1))->method('saveEventLocal');
        $this->stub->expects($this->exactly(1))->method('deleteEventLocal');

        $this->expectException(ReadError::class);
        $this->stub->sync([
            'endPoint' => 'me/calendarview',
            'skipOccurrences' => true,
            'queryParams' => [
                'startDateTime' => date("Y-m-d\TH:i:s", strtotime('2019-02-24')),
                'endDateTime' => date("Y-m-d\TH:i:s", strtotime('2019-02-24'))
            ]
        ]);
    }

    public function testBatchExceptionHandler()
    {
        $request = new Request('fooTest', [
            'requestOptions' => function (string $url, RequestType $methodType, array $args = []) {
                return new RequestOptions($url, $methodType, $args);
            },
            'connection' => $this->connection,
            'batchConnectionHandler' => function() {
                return new \stdClass();
            }
        ]);

        $this->stub = $this->getMockForAbstractClass(Calendar::class, [
            'fooToken', [
                'logger' => $this->logger,
                'request' => $request
            ]
        ], '', true, true, true, ['handleBatchResponse', 'saveEventLocal']);

        $this->stub->expects($this->once())->method('getLocalEvents')->willReturn([
            (new Writer())
                ->setId('bar')
                ->setSubject('test')
                ->method(new RequestType(RequestType::Get))
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time'))
        ]);

        $this->expectExceptionObject(new \InvalidArgumentException('Batch requested but handler is not set'));
        $this->stub->push();
    }

    public function getStream() : string
    {
        return '{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/CalendarView","value":[{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'foo\')\/Events(\'x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=\')","@odata.etag":"W\/\"ghc\/foo\/\/pA==\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","CreatedDateTime":"2019-02-01T18:05:03.7354577-05:00","LastModifiedDateTime":"2019-02-04T23:58:49.478552-05:00","ChangeKey":"foo\/\/pA==","Categories":[],"OriginalStartTimeZone":"Eastern Standard Time","OriginalEndTimeZone":"Eastern Standard Time","iCalUId":"foo","ReminderMinutesBeforeStart":15,"IsReminderOn":true,"HasAttachments":false,"Subject":"FooBar","BodyPreview":"CCCCCCC","Importance":"Normal","Sensitivity":"Normal","IsAllDay":true,"IsCancelled":false,"IsOrganizer":false,"ResponseRequested":true,"SeriesMasterId":null,"ShowAs":"Free","Type":"SeriesMaster","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=foo%3D&exvsurl=1&path=\/calendar\/item","OnlineMeetingUrl":null,"ResponseStatus":{"Response":"Accepted","Time":"2019-02-01T18:05:25.680242-05:00"},"Body":{"ContentType":"HTML","Content":"test"},"Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"India","PostalCode":""},"Coordinates":{"Latitude":27.6031,"Longitude":88.6468}},"Locations":[{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"US","PostalCode":""},"Coordinates":{"Latitude":32.6031,"Longitude":999.6468}}],"Recurrence":{"Pattern":{"Type":"Daily","Interval":1,"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First"},"Range":{"Type":"EndDate","StartDate":"2019-02-25","EndDate":"2019-02-28","RecurrenceTimeZone":"Eastern Standard Time","NumberOfOccurrences":0}},"Attendees":[{"Type":"Required","Status":{"Response":"None","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}},{"Type":"Required","Status":{"Response":"Accepted","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Insight Test","Address":"test"}}],"Organizer":{"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1==\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f-\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1pxGhEEAAEYAAAAAQT-FGzVQ0E2D76JKXt4TogcAghc-oDgwvEWAzon06Zf8fQAAAAABDQAAghc-\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLT=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-27T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-28T00:00:00.0000000","TimeZone":"Eastern Standard Time"}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE==\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1p0PrqrAEA==","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-28T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-03-01T00:00:00.0000000","TimeZone":"Eastern Standard Time"}}, {"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/CalendarView\/$deletedEntity","id":"CalendarView(\'bcccdef=\')","reason":"deleted"}],"@odata.deltaLink":"https:\/\/outlook.office.com\/api\/v2.0\/me\/calendarview?startDateTime=2019-02-24T00%3a00%3a00&endDateTime=2019-03-10T00%3a00%3a00&%24deltatoken=phpunit123=="}';
    }

    public function getEventInstancesStream() : string
    {
        return '{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/CalendarView","value":[{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'foo\')\/Events(\'x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=\')","@odata.etag":"W\/\"ghc\/foo\/\/pA==\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","CreatedDateTime":"2019-02-01T18:05:03.7354577-05:00","LastModifiedDateTime":"2019-02-04T23:58:49.478552-05:00","ChangeKey":"foo\/\/pA==","Categories":[],"OriginalStartTimeZone":"Eastern Standard Time","OriginalEndTimeZone":"Eastern Standard Time","iCalUId":"foo","ReminderMinutesBeforeStart":15,"IsReminderOn":true,"HasAttachments":false,"Subject":"FooBar","BodyPreview":"CCCCCCC","Importance":"Normal","Sensitivity":"Normal","IsAllDay":true,"IsCancelled":false,"IsOrganizer":false,"ResponseRequested":true,"SeriesMasterId":null,"ShowAs":"Free","Type":"SeriesMaster","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=foo%3D&exvsurl=1&path=\/calendar\/item","OnlineMeetingUrl":null,"ResponseStatus":{"Response":"Accepted","Time":"2019-02-01T18:05:25.680242-05:00"},"Body":{"ContentType":"HTML","Content":"test"},"Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"India","PostalCode":""},"Coordinates":{"Latitude":27.6031,"Longitude":88.6468}},"Locations":[{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"US","PostalCode":""},"Coordinates":{"Latitude":32.6031,"Longitude":999.6468}}],"Recurrence":{"Pattern":{"Type":"Daily","Interval":1,"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First"},"Range":{"Type":"EndDate","StartDate":"2019-02-25","EndDate":"2019-02-28","RecurrenceTimeZone":"Eastern Standard Time","NumberOfOccurrences":0}},"Attendees":[{"Type":"Required","Status":{"Response":"None","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}},{"Type":"Required","Status":{"Response":"Accepted","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Insight Test","Address":"test"}}],"Organizer":{"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1==\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f-\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1pxGhEEAAEYAAAAAQT-FGzVQ0E2D76JKXt4TogcAghc-oDgwvEWAzon06Zf8fQAAAAABDQAAghc-\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLT=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-27T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-28T00:00:00.0000000","TimeZone":"Eastern Standard Time"}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE==\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1p0PrqrAEA==","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-28T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-03-01T00:00:00.0000000","TimeZone":"Eastern Standard Time"}}, {"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/CalendarView\/$deletedEntity","id":"CalendarView(\'bcccdef=\')","reason":"deleted"}]}';
    }
}
