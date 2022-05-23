<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Http;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Http\ConnectionToken;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\Request as outlookRequest;

class ConnectionTokenTest extends TestCase
{
    private $connection;
    private $handler;

    public function setUp(): void
    {
        $this->handler = new TestHandler();
        $logger = new Logger('outlook-calendar', [$this->handler]);
        $this->connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs(['logger' => $logger])
            ->setMethods(['createClientWithRetryHandler', 'createClient', 'upsertRetryDelay'])
            ->getMock();
    }

    public function testTryRefreshHeaderToken()
    {
        $logger = new Logger('outlook-calendar', [$this->handler]);
        $requestObj = new outlookRequest('123', ['connection' => $this->connection, 'requestOptions' => null]);
        $connectionToken = new ConnectionToken($logger, $requestObj);
        $this->assertEmpty($connectionToken->tryRefreshHeaderToken());

        $requestObj = new outlookRequest('123', ['connection' => $this->connection, 'requestOptions' => null]);
        $requestArgs = [
            'url' => 'test.com',
            'token' => 'test',
        ];
        $connectionToken = new ConnectionToken($logger, $requestObj, $requestArgs);
        $this->assertEmpty($connectionToken->tryRefreshHeaderToken());

        $requestObj = $this->createMock(outlookRequest::class);
        $requestObj->method('getHeadersWithToken')
             ->willReturn(['foo']);
        $requestArgs = [
            'url' => 'test.com',
            'token' => [
                'clientID' => '123',
                'clientSecret' => '456',
                'refreshToken' => '789',
                'outlookProxyUrl' => 'https://test.com/',
            ],
            'logger' => $logger,
        ];

        $connectionToken = $this->getMockBuilder(ConnectionToken::class)
            ->setConstructorArgs([$logger, $requestObj, $requestArgs])
            ->setMethods(['getNewAccessToken'])
            ->getMock();

        $connectionToken->expects($this->any())
            ->method('getNewAccessToken')
            ->willReturn('123456789');
        $this->assertEquals(['foo'], $connectionToken->tryRefreshHeaderToken());
    }

    public function testGetNewAccessToken()
    {
        $logger = new Logger('outlook-calendar', [$this->handler]);
        $requestObj = new outlookRequest('123', ['connection' => $this->connection, 'requestOptions' => null]);
        $connectionToken = new ConnectionToken($logger, $requestObj);
        $this->assertEmpty($connectionToken->getNewAccessToken());

        $requestObj = new outlookRequest('123', ['connection' => $this->connection, 'requestOptions' => null]);
        $requestArgs = [
            'url' => 'test.com',
            'token' => 'test',
        ];
        $connectionToken = new ConnectionToken($logger, $requestObj, $requestArgs);
        $this->assertEmpty($connectionToken->getNewAccessToken());
    }

    public function testShouldRefreshToken()
    {
        $logger = new Logger('outlook-calendar', [$this->handler]);
        $requestObj = new outlookRequest('123', ['connection' => $this->connection, 'requestOptions' => null]);
        $connectionToken = new ConnectionToken($logger, $requestObj);
        $this->assertFalse($connectionToken->shouldRefreshToken());

        $requestArgs = [
            'token' => [
                'token_received_on' => date('Y-m-d H:i:s', strtotime("-50 minutes")),
                'expires_in' => 3600,
            ],
        ];
        $connectionToken = new ConnectionToken($logger, $requestObj, $requestArgs);
        $this->assertFalse($connectionToken->shouldRefreshToken());

        $requestArgs = [
            'url' => 'test.com',
            'token' => [
                'token_received_on' => date('Y-m-d H:i:s', strtotime("-59 minutes")),
                'expires_in' => 3600,
            ],
        ];
        $connectionToken = new ConnectionToken($logger, $requestObj, $requestArgs);
        $this->assertFalse($connectionToken->shouldRefreshToken());

        $requestArgs = [
            'url' => 'test.com',
            'token' => [
                'token_received_on' => date('Y-m-d H:i:s', strtotime("-60 minutes")),
                'expires_in' => 3600,
            ],
        ];
        $connectionToken = new ConnectionToken($logger, $requestObj, $requestArgs);
        $this->assertTrue($connectionToken->shouldRefreshToken());
    }
}
