<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Http;

use GuzzleHttp\Client;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Symplicity\Outlook\Entities\Delete;
use Symplicity\Outlook\Entities\ODateTime;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Entities\Writer;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\Utils\BatchResponseInterface;
use Symplicity\Outlook\Utilities\RequestType;

class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    private $connection;
    private $handler;

    public function setUp()
    {
        $this->handler = new TestHandler();
        $logger = new Logger('outlook-calendar', [$this->handler]);
        $this->connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs(['logger' => $logger])
            ->setMethods(['createClientWithRetryHandler', 'createClient'])
            ->getMock();
    }

    /**
     * @throws \Symplicity\Outlook\Exception\ConnectionException
     */
    public function testGet()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['test'])),
            new Response(202, ['Content-Length' => 0]),
            new Response(400, ['Content-Length' => 0], stream_for('Client Error')),
            new Response(401, ['Content-Length' => 0], stream_for('Dates not valid')),
            new Response(401, ['Content-Length' => 0], stream_for('Dates not valid')),
            new Response(202, ['Content-Length' => 0]),
            new RequestException('Error Communicating with Server', new Request('GET', 'test.com')),
        ]);

        $handler = HandlerStack::create($mock);
        $retryHandler = $this->connection->createRetryHandler();
        $handler->push(Middleware::retry($retryHandler, $this->connection->retryDelay()));
        $requestOptions = new RequestOptions('test', RequestType::Get());

        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->any())
            ->method('createClientWithRetryHandler')
            ->willReturn($client);

        $response = $this->connection->get('test', $requestOptions);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertFalse($this->handler->hasWarningRecords());

        $this->connection->get('test.com', $requestOptions);
        $this->assertFalse($this->handler->hasWarningRecords());

        try {
            $this->connection->get('test.com', $requestOptions);
        } catch (\Exception $exception) {
        } finally {
            // Not retrying for 400
            $this->assertFalse($this->handler->hasWarningRecords());
        }

        try {
            $this->connection->get('test.com', $requestOptions);
        } catch (\Exception $exception) {
        } finally {
            $this->assertTrue($this->handler->hasWarningRecords());
            $this->assertTrue($this->handler->hasRecordThatMatches('/Retrying/', Logger::WARNING));
            $this->assertCount(2, $this->handler->getRecords());
        }

        try {
            $this->connection->get('test.com', $requestOptions);
        } catch (\Exception $exception) {
        } finally {
            $this->assertTrue($this->handler->hasWarningRecords());
            $this->assertTrue($this->handler->hasRecordThatMatches('/Retrying/', Logger::WARNING));
            $this->assertCount(2, $this->handler->getRecords());
        }
    }

    public function testPost()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['test'])),
        ]);

        $requestOptions = new RequestOptions('test', new RequestType(RequestType::Post));

        $this->createHandler($mock);
        $response = $this->connection->post('test', $requestOptions);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBatch()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'foo\')\/Events(\'x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=\')","@odata.etag":"W\/\"ghc\/foo\/\/pA==\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","CreatedDateTime":"2019-02-01T18:05:03.7354577-05:00","LastModifiedDateTime":"2019-02-04T23:58:49.478552-05:00","ChangeKey":"foo\/\/pA==","Categories":[],"OriginalStartTimeZone":"Eastern Standard Time","OriginalEndTimeZone":"Eastern Standard Time","iCalUId":"foo","ReminderMinutesBeforeStart":15,"IsReminderOn":true,"HasAttachments":false,"Subject":"FooBar","BodyPreview":"CCCCCCC","Importance":"Normal","Sensitivity":"Normal","IsAllDay":true,"IsCancelled":false,"IsOrganizer":false,"ResponseRequested":true,"SeriesMasterId":null,"ShowAs":"Free","Type":"SeriesMaster","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=foo%3D&exvsurl=1&path=\/calendar\/item","OnlineMeetingUrl":null,"ResponseStatus":{"Response":"Accepted","Time":"2019-02-01T18:05:25.680242-05:00"},"Body":{"ContentType":"HTML","Content":"test"},"Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"India","PostalCode":""},"Coordinates":{"Latitude":27.6031,"Longitude":88.6468}},"Locations":[{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"US","PostalCode":""},"Coordinates":{"Latitude":32.6031,"Longitude":999.6468}}],"Recurrence":{"Pattern":{"Type":"Daily","Interval":1,"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First"},"Range":{"Type":"EndDate","StartDate":"2019-02-25","EndDate":"2019-02-28","RecurrenceTimeZone":"Eastern Standard Time","NumberOfOccurrences":0}},"Attendees":[{"Type":"Required","Status":{"Response":"None","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}},{"Type":"Required","Status":{"Response":"Accepted","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Insight Test","Address":"test"}}],"Organizer":{"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}}}'),
            new Response(202, ['Content-Length' => 0]),
            new Response(401, ['Content-Length' => 0], stream_for('Client Error')),
            new RequestException('Error Communicating with Server', new Request('GET', 'test.com')),
        ]);

        $requestOptions = new RequestOptions('test', new RequestType(RequestType::Post));
        $events = [];

        foreach (['foo', 'bar', 'foo1', 'bar1'] as $id) {
            $events[] = (new Writer())->setId($id)
                ->setBody(new ResponseBody(['Content' => 'test', 'ContentType' => 'HTML']))
                ->setSubject('ABC')
                ->setStartDate(new ODateTime(new \DateTime('now'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('now'), 'Eastern Standard Time'))
                ->setInternalEventType('PHP');
        }

        $requestOptions->addBody($events);
        $this->createHandler($mock);

        $response = $this->connection->batch($requestOptions);
        $this->assertCount(4, $response);
        foreach ($response as $key => $value) {
            /** @var BatchResponseInterface $oResponse */
            $oResponse = $value['response'];
            $this->assertInstanceOf(BatchResponseInterface::class, $oResponse);
            $this->assertTrue(is_array($value['item']));
            $this->assertArrayHasKey('eventType', $value['item']);
            $this->assertTrue(in_array($oResponse->getStatus(), [PromiseInterface::FULFILLED, PromiseInterface::REJECTED]));
            $this->assertTrue(in_array($oResponse->getStatusCode(), [200, 202, 401, 0]));
        }
    }

    public function testBatchDelete()
    {
        $mock = new MockHandler([
            new Response(204, [], json_encode(['test'])),
            new RequestException('Error Communicating with Server', new Request('GET', 'test.com')),
        ]);

        $requestOptions = new RequestOptions('test', new RequestType(RequestType::Delete));

        $events = [];

        foreach (['foo', 'bar'] as $id) {
            $events[] = new Delete('123', $id);
        }

        $requestOptions->addBody($events);
        $this->createHandler($mock);

        $response = $this->connection->batchDelete($requestOptions);
        $this->assertCount(2, $response);
        foreach ($response as $key => $value) {
            /** @var BatchResponseInterface $oResponse */
            $oResponse = $value['response'];
            $this->assertInstanceOf(BatchResponseInterface::class, $oResponse);
            $this->assertTrue(is_array($value['item']));
            $this->assertArrayHasKey('eventType', $value['item']);
            $this->assertArrayHasKey('delete', $value['item']);
            $this->assertTrue(in_array($oResponse->getStatus(), [PromiseInterface::FULFILLED, PromiseInterface::REJECTED]));
            $this->assertTrue(in_array($oResponse->getStatusCode(), [204, 0]));
        }
    }

    public function createHandler(\Countable $mock)
    {
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $this->connection->expects($this->any())
            ->method('createClient')
            ->willReturn($client);
    }
}
