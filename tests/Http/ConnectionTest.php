<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Http;

use GuzzleHttp\Client;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symplicity\Outlook\Entities\BatchErrorEntity;
use Symplicity\Outlook\Entities\BatchResponseDeleteEntity;
use Symplicity\Outlook\Entities\BatchResponseReader;
use function GuzzleHttp\Psr7\stream_for;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symplicity\Outlook\Entities\Delete;
use Symplicity\Outlook\Entities\ODateTime;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Entities\Writer;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\Utils\BatchResponseInterface;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Batch\Response as BatchResponse;

class ConnectionTest extends TestCase
{
    private $connection;
    private $handler;

    public function setUp()
    {
        $this->handler = new TestHandler();
        $logger = new Logger('outlook-calendar', [$this->handler]);
        $this->connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs(['logger' => $logger])
            ->setMethods(['createClientWithRetryHandler', 'createClient', 'upsertRetryDelay'])
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
            $this->assertTrue($this->handler->hasWarningRecords());
        }

        try {
            $this->connection->get('test.com', $requestOptions);
        } catch (\Exception $exception) {
        } finally {
            $this->assertTrue($this->handler->hasWarningRecords());
            $this->assertTrue($this->handler->hasRecordThatMatches('/Get Request Failed/', Logger::WARNING));
            $this->assertTrue($this->handler->hasRecordThatMatches('/Retrying/', Logger::WARNING));
            $this->assertCount(3, $this->handler->getRecords());
        }

        try {
            $this->connection->get('test.com', $requestOptions);
        } catch (\Exception $exception) {
        } finally {
            $this->assertTrue($this->handler->hasWarningRecords());
            $this->assertTrue($this->handler->hasRecordThatMatches('/Get Request Failed/', Logger::WARNING));
            $this->assertTrue($this->handler->hasRecordThatMatches('/Retrying/', Logger::WARNING));
            $this->assertCount(4, $this->handler->getRecords());
        }
    }

    public function testPost()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['test'])),
        ]);

        $requestOptions = new RequestOptions('test', new RequestType(RequestType::Post));

        $this->createHandler($mock);
        $response = $this->connection->upsert('test', $requestOptions);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBatch()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"responses":[{"id":"foo","status":201,"headers":{"etag":"W\/\"123==\"","location":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","odata-version":"4.0","content-type":"application\/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8"},"body":{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/Events(Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime)\/$entity","@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","@odata.etag":"W\/\"123==\"","Id":"test==","LastModifiedDateTime":"2020-11-09T14:40:50.8444665-05:00","Subject":"ABC","SeriesMasterId":null,"Type":"SingleInstance","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=ANC%3D%3D&exvsurl=1&path=\/calendar\/item"}},{"id":"bar","status":201,"headers":{"etag":"W\/\"456==\"","location":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'CDE==\')","odata-version":"4.0","content-type":"application\/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8"},"body":{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/Events(Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime)\/$entity","@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'CDE==\')","@odata.etag":"W\/\"aHQ+t811Ok+IYnQ4RgjubgACguszQg==\"","Id":"CDE==","LastModifiedDateTime":"2020-11-09T14:40:51.1413001-05:00","Subject":"ABC","SeriesMasterId":null,"Type":"SingleInstance","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=cde&path=\/calendar\/item"}},{"id":"foo1","status":201,"headers":{"etag":"W\/\"123==\"","location":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","odata-version":"4.0","content-type":"application\/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8"},"body":{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/Events(Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime)\/$entity","@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","@odata.etag":"W\/\"123==\"","Id":"test==","LastModifiedDateTime":"2020-11-09T14:40:50.8444665-05:00","Subject":"ABC","SeriesMasterId":null,"Type":"SingleInstance","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=ANC%3D%3D&exvsurl=1&path=\/calendar\/item"}}, {"id":"bar1","status":400,"headers":{"etag":"W\/\"123==\"","location":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","odata-version":"4.0","content-type":"application\/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8"},"body":{"error": {"code": "InvalidParams", "message": "Invalid params passed"}}}, {"id":"2323","status":204,"headers":[]}]}'),
            new Response(202, ['Content-Length' => 0]),
            new Response(401, ['Content-Length' => 0], stream_for('Client Error')),
            new Response(429, ['Content-Length' => 0, 'Retry-After' => 2], stream_for('Client Error')),
            new RequestException('Error Communicating with Server', new Request('GET', 'test.com')),
        ]);

        $requestOptions = new RequestOptions('test', new RequestType(RequestType::Post), ['token' => 'ABC12==']);
        $events = [];

        foreach (['foo', 'bar', 'foo1', 'bar1'] as $id) {
            $events[] = (new Writer())->setId($id)
                ->setBody(new ResponseBody(['Content' => 'test', 'ContentType' => 'HTML']))
                ->setSubject('ABC')
                ->method(new RequestType(RequestType::Post()))
                ->setStartDate(new ODateTime(new \DateTime('now'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('now'), 'Eastern Standard Time'))
                ->setInternalEventType('PHP');
        }

        $events[] = new Delete('ABC==', '2323');

        $requestOptions->addBody($events);
        $requestOptions->addBatchHeaders();
        $this->createRetryHandler($mock);

        $this->connection->expects($this->once())
            ->method('upsertRetryDelay');

        $response = $this->connection->batch($requestOptions);
        $this->assertInstanceOf(BatchResponse::class, $response);
        foreach ($response as $key => $value) {
            /** @var BatchResponseInterface $oResponse */
            $oResponse = $value['response'];
            if ($key == 'bar1') {
                $this->assertInstanceOf(BatchErrorEntity::class, $oResponse);
            } elseif ($key == '2323') {
                $this->assertInstanceOf(BatchResponseDeleteEntity::class, $oResponse);
            } else {
                $this->assertInstanceOf(BatchResponseReader::class, $oResponse);
                $this->assertTrue(in_array($value['item']['statusCode'], [200, 201, 204]));
            }

            $this->assertTrue(is_array($value['item']));
            $this->assertArrayHasKey('eventType', $value['item']);
        }
    }

    public function testUpsertRetryDelay()
    {
        $connectionHandler = new Connection(new Logger('outlook-calendar', [$this->handler]));
        $this->assertInstanceOf(Client::class, $connectionHandler->createClientWithRetryHandler());
        $this->assertInstanceOf(Client::class, $connectionHandler->createClient());
        $retryHandler = $connectionHandler->upsertRetryDelay();
        $response = $retryHandler->call($connectionHandler, 3, new Response(429, ['Content-Length' => 0, 'Retry-After' => 2], stream_for('Client Error')));
        $this->assertEquals(2000, $response);

        $response = $retryHandler->call($connectionHandler, 1, new Response(429, ['Content-Length' => 0], stream_for('Client Error')));
        $this->assertEquals(1000, $response);
    }

    /**
     * @dataProvider getRetryHandlerData
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param int $retries
     * @param bool $expected
     */
    public function testRetryHandler(RequestInterface $request, ?ResponseInterface $response, int $retries = 1, bool $expected = false)
    {
        $this->handler->clear();
        $connectionHandler = new Connection(new Logger('outlook-calendar', [$this->handler]));
        $retryHandler = $connectionHandler->createRetryHandler();
        $this->assertIsCallable($retryHandler);
        $response = $retryHandler->call($connectionHandler, $retries, $request, $response);
        $this->assertEquals($expected, $response);
        if ($expected) {
            $this->assertTrue($this->handler->hasWarningThatContains('Retrying'));
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

    public function createRetryHandler(\Countable $mock)
    {
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $this->connection->expects($this->any())
            ->method('createClientWithRetryHandler')
            ->willReturn($client);
    }

    public function getRetryHandlerData(): array
    {
        return [
            [new Request(RequestType::Get, 'outlook.com'), new Response(200, ['Content-Length' => 0], stream_for(''))],
            [new Request(RequestType::Get, 'outlook.com'), new Response(401, ['Content-Length' => 0], stream_for('Client Error')), 2, true],
            [new Request(RequestType::Get, 'outlook.com'), new Response(401, ['Content-Length' => 0], stream_for('Client Error')), 4, false],
            [new Request(RequestType::Post, 'outlook.com'), new Response(401, ['Content-Length' => 0], stream_for('Client Error')), 2, false],
            [new Request(RequestType::Post, 'outlook.com'), new Response(429, ['Content-Length' => 0], stream_for('Client Error')), 2, true],
            [new Request(RequestType::Put, 'outlook.com'), new Response(429, ['Content-Length' => 0], stream_for('Client Error')), 2, true],
            [new Request(RequestType::Delete, 'outlook.com'), new Response(429, ['Content-Length' => 0], stream_for('Client Error')), 2, true],
            [new Request(RequestType::Delete, 'outlook.com'), new Response(429, ['Content-Length' => 0], stream_for('Client Error')), 11, false]
        ];
    }
}
