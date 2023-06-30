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
use GuzzleHttp\Psr7\Utils;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Http\Request as outlookRequest;
use Symplicity\Outlook\Utilities\RequestType;

class ConnectionTest extends TestCase
{
    private $connection;
    private $handler;

    public function setUp(): void
    {
        $this->handler = new TestHandler();
        $logger = new Logger('outlook-calendar', [$this->handler]);
        $this->connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs(['logger' => $logger])
            ->onlyMethods(['createClientWithRetryHandler', 'createClient', 'upsertRetryDelay'])
            ->getMock();
    }

    public function testGet()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['test'])),
            new Response(202, ['Content-Length' => 0]),
            new Response(400, ['Content-Length' => 0], Utils::streamFor('Client Error')),
            new Response(401, ['Content-Length' => 0], Utils::streamFor('Dates not valid')),
            new Response(401, ['Content-Length' => 0], Utils::streamFor('Dates not valid')),
            new Response(202, ['Content-Length' => 0]),
            new RequestException('Error Communicating with Server', new Request('GET', 'test.com')),
        ]);

        $handler = HandlerStack::create($mock);
        $retryHandler = $this->connection->createRetryHandler();
        $handler->push(Middleware::retry($retryHandler, function (int $numberOfRetries) {
            return 10 * $numberOfRetries;
        }));

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

    public function testUpsertRetryDelay()
    {
        $connectionHandler = new Connection(new Logger('outlook-calendar', [$this->handler]));
        $this->assertInstanceOf(Client::class, $connectionHandler->createClientWithRetryHandler());
        $this->assertInstanceOf(Client::class, $connectionHandler->createClient());
        $retryHandler = $connectionHandler->upsertRetryDelay();
        $response = $retryHandler->call($connectionHandler, 3, new Response(429, ['Content-Length' => 0, 'Retry-After' => 2], Utils::streamFor('Client Error')));
        $this->assertEquals(2000, $response);

        $response = $retryHandler->call($connectionHandler, 1, new Response(429, ['Content-Length' => 0], Utils::streamFor('Client Error')));
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

        $retryDelay = $connectionHandler->retryDelay();
        $this->assertIsCallable($retryDelay);
        $response = $retryDelay->call($connectionHandler, $retries);
        $this->assertEquals(1000 * $retries, $response);
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
            [new Request(RequestType::Get, 'outlook.com'), new Response(200, ['Content-Length' => 0], Utils::streamFor(''))],
            [new Request(RequestType::Get, 'outlook.com'), new Response(401, ['Content-Length' => 0], Utils::streamFor('Client Error')), 2, true],
            [new Request(RequestType::Get, 'outlook.com'), new Response(401, ['Content-Length' => 0], Utils::streamFor('Client Error')), 4, false],
            [new Request(RequestType::Post, 'outlook.com'), new Response(401, ['Content-Length' => 0], Utils::streamFor('Client Error')), 2, false],
            [new Request(RequestType::Post, 'outlook.com'), new Response(429, ['Content-Length' => 0], Utils::streamFor('Client Error')), 2, true],
            [new Request(RequestType::Put, 'outlook.com'), new Response(429, ['Content-Length' => 0], Utils::streamFor('Client Error')), 2, true],
            [new Request(RequestType::Delete, 'outlook.com'), new Response(429, ['Content-Length' => 0], Utils::streamFor('Client Error')), 2, true],
            [new Request(RequestType::Delete, 'outlook.com'), new Response(429, ['Content-Length' => 0], Utils::streamFor('Client Error')), 11, false]
        ];
    }
}
