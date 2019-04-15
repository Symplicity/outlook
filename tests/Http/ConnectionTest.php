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
            new Response(200, [], json_encode(['test'])),
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
