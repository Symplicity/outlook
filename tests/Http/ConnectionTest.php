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
use function GuzzleHttp\Psr7\stream_for;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\RequestOptions;
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
        $handler->push(Middleware::retry($this->connection->createRetryHandler(), $this->connection->retryDelay()));
        $requestOptions = new RequestOptions('test', RequestType::Get());

        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->any())
            ->method('createClientWithRetryHandler')
            ->willReturn($client);

        $this->connection->get('test', $requestOptions);
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
            $this->connection->get('test.com',$requestOptions);
        } catch (\Exception $exception) {

        } finally {
            $this->assertTrue($this->handler->hasWarningRecords());
            $this->assertTrue($this->handler->hasRecordThatMatches('/Retrying/', Logger::WARNING));
            $this->assertCount(2, $this->handler->getRecords());
        }
    }
}