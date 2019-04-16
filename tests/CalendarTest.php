<?php

declare(strict_types=1);


namespace Symplicity\Outlook\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symplicity\Outlook\Calendar;
use Symplicity\Outlook\Entities\Occurrence;
use Symplicity\Outlook\Entities\ODateTime;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Entities\Writer;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\Request;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Utilities\BatchResponse;
use Symplicity\Outlook\Utilities\RequestType;

class CalendarTest extends \PHPUnit_Framework_TestCase
{
    protected $stub;
    protected $connection;

    public function setUp()
    {
        $logger = new Logger('outlook_calendar');
        $logger->pushHandler(new NullHandler());
        $this->connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([$logger])
            ->setMethods(['createClient', 'createClientWithRetryHandler'])
            ->getMock();

        $this->stub = $this->getMockForAbstractClass(Calendar::class, [
            'fooToken',
            [
                'logger' => $logger,
                'request' => new Request('fooTest', [
                    'requestOptions' => function (string $url, RequestType $methodType, array $args = []) {
                        return new RequestOptions($url, $methodType, $args);
                    },
                    'connection' => $this->connection
                ])
            ]
        ], '', true, true, true, ['handlePoolResponses', 'saveEventLocal']);
    }

    public function testSync()
    {
        $mock = new MockHandler([
            new Response(200),
            new RequestException('Error Communicating with Server', new \GuzzleHttp\Psr7\Request('GET', 'test')),
            new Response(200, [], stream_for($this->getStream())),
            new Response(200, [], stream_for($this->getStream()))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->exactly(2))->method('createClientWithRetryHandler')->willReturn($client);
        $this->connection->expects($this->once())->method('createClient')->willReturn($client);

        $this->stub->isBatchRequest();
        $this->stub->expects($this->once())->method('getLocalEvents')->willReturn([
            (new Writer())
                ->setId('bar')
                ->setSubject('test')
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time')),
            (new Writer())
                ->setId('foo')
                ->setSubject('test')
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time'))
        ]);

        $this->stub->expects($this->exactly(1))->method('handlePoolResponses')->with($this->equalTo($this->fulFilledResponse()));
        $this->stub->expects($this->exactly(8))->method('saveEventLocal')->withConsecutive($this->equalTo($this->getExpectedReadEntities()));

        $this->stub->sync([
            'endPoint' => 'me/calendarview',
            'queryParams' => [
                'startDateTime' => date("Y-m-d\TH:i:s", strtotime('2019-02-24')),
                'endDateTime' => date("Y-m-d\TH:i:s", strtotime('2019-02-24'))
            ]
        ]);
    }

    public function fulFilledResponse()
    {
        return [
            'bar' => [
                'response' => new BatchResponse(['state' => PromiseInterface::FULFILLED, 'value' => new Response(200, [])]),
                'item' => \GuzzleHttp\json_decode('{"Subject":"test","Body":{"ContentType":"HTML","Content":"foo"},"Start":{"DateTime":"2019-02-04T16:40:36","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-04T16:50:36","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":null},"Recurrence":null,"eventType":"1"}', true)
            ],
            'foo' => [
                'response' => new BatchResponse(['state' => PromiseInterface::REJECTED, 'reason' => new ServerException('Error Communicating with Server', new \GuzzleHttp\Psr7\Request('POST', 'test'), new Response(0, ['X-Foo' => 'Bar']))]),
                'item' => \GuzzleHttp\json_decode('{"Subject":"test","Body":{"ContentType":"HTML","Content":"foo"},"Start":{"DateTime":"2019-02-04T16:40:36","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-04T16:50:36","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":null},"Recurrence":null,"eventType":"1"}', true)
            ]
        ];
    }

    public function getExpectedReadEntities()
    {
        return [
            new Reader(\GuzzleHttp\json_decode('{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'foo\')\/Events(\'x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=\')","@odata.etag":"W\/\"ghc\/foo\/\/pA==\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","CreatedDateTime":"2019-02-01T18:05:03.7354577-05:00","LastModifiedDateTime":"2019-02-04T23:58:49.478552-05:00","ChangeKey":"foo\/\/pA==","Categories":[],"OriginalStartTimeZone":"Eastern Standard Time","OriginalEndTimeZone":"Eastern Standard Time","iCalUId":"foo","ReminderMinutesBeforeStart":15,"IsReminderOn":true,"HasAttachments":false,"Subject":"FooBar","BodyPreview":"CCCCCCC","Importance":"Normal","Sensitivity":"Normal","IsAllDay":true,"IsCancelled":false,"IsOrganizer":false,"ResponseRequested":true,"SeriesMasterId":null,"ShowAs":"Free","Type":"SeriesMaster","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=foo%3D&exvsurl=1&path=\/calendar\/item","OnlineMeetingUrl":null,"ResponseStatus":{"Response":"Accepted","Time":"2019-02-01T18:05:25.680242-05:00"},"Body":{"ContentType":"HTML","Content":"test"},"Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"India","PostalCode":""},"Coordinates":{"Latitude":27.6031,"Longitude":88.6468}},"Locations":[{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"US","PostalCode":""},"Coordinates":{"Latitude":32.6031,"Longitude":999.6468}}],"Recurrence":{"Pattern":{"Type":"Daily","Interval":1,"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First"},"Range":{"Type":"EndDate","StartDate":"2019-02-25","EndDate":"2019-02-28","RecurrenceTimeZone":"Eastern Standard Time","NumberOfOccurrences":0}},"Attendees":[{"Type":"Required","Status":{"Response":"None","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}},{"Type":"Required","Status":{"Response":"Accepted","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Insight Test","Address":"test"}}],"Organizer":{"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}}}', true)),
            new Occurrence(\GuzzleHttp\json_decode('{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1==\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"}}', true)),
            new Occurrence(\GuzzleHttp\json_decode('{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f-\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1pxGhEEAAEYAAAAAQT-FGzVQ0E2D76JKXt4TogcAghc-oDgwvEWAzon06Zf8fQAAAAABDQAAghc-\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLT=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-27T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-28T00:00:00.0000000","TimeZone":"Eastern Standard Time"}}', true))
        ];
    }

    public function getStream() : string
    {
        return '{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/CalendarView","value":[{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'foo\')\/Events(\'x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=\')","@odata.etag":"W\/\"ghc\/foo\/\/pA==\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","CreatedDateTime":"2019-02-01T18:05:03.7354577-05:00","LastModifiedDateTime":"2019-02-04T23:58:49.478552-05:00","ChangeKey":"foo\/\/pA==","Categories":[],"OriginalStartTimeZone":"Eastern Standard Time","OriginalEndTimeZone":"Eastern Standard Time","iCalUId":"foo","ReminderMinutesBeforeStart":15,"IsReminderOn":true,"HasAttachments":false,"Subject":"FooBar","BodyPreview":"CCCCCCC","Importance":"Normal","Sensitivity":"Normal","IsAllDay":true,"IsCancelled":false,"IsOrganizer":false,"ResponseRequested":true,"SeriesMasterId":null,"ShowAs":"Free","Type":"SeriesMaster","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=foo%3D&exvsurl=1&path=\/calendar\/item","OnlineMeetingUrl":null,"ResponseStatus":{"Response":"Accepted","Time":"2019-02-01T18:05:25.680242-05:00"},"Body":{"ContentType":"HTML","Content":"test"},"Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"India","PostalCode":""},"Coordinates":{"Latitude":27.6031,"Longitude":88.6468}},"Locations":[{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"US","PostalCode":""},"Coordinates":{"Latitude":32.6031,"Longitude":999.6468}}],"Recurrence":{"Pattern":{"Type":"Daily","Interval":1,"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First"},"Range":{"Type":"EndDate","StartDate":"2019-02-25","EndDate":"2019-02-28","RecurrenceTimeZone":"Eastern Standard Time","NumberOfOccurrences":0}},"Attendees":[{"Type":"Required","Status":{"Response":"None","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}},{"Type":"Required","Status":{"Response":"Accepted","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Insight Test","Address":"test"}}],"Organizer":{"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1==\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f-\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1pxGhEEAAEYAAAAAQT-FGzVQ0E2D76JKXt4TogcAghc-oDgwvEWAzon06Zf8fQAAAAABDQAAghc-\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLT=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-27T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-28T00:00:00.0000000","TimeZone":"Eastern Standard Time"}},{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE==\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1p0PrqrAEA==","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-28T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-03-01T00:00:00.0000000","TimeZone":"Eastern Standard Time"}}],"@odata.deltaLink":"https:\/\/outlook.office.com\/api\/v2.0\/me\/calendarview?startDateTime=2019-02-24T00%3a00%3a00&endDateTime=2019-03-10T00%3a00%3a00&%24deltatoken=FgM6J9OmX_H0AAAehQ74BAAAA"}';
    }
}
