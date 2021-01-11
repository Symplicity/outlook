<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use function GuzzleHttp\Psr7\stream_for;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Http\Message\StreamInterface;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Interfaces\Entity\TokenInterface;
use Symplicity\Outlook\Token;

class TokenTest extends TestCase
{
    protected $tokenHandler;
    protected $connection;

    public function setUp(): void
    {
        $logger = new Logger('outlook_calendar');
        $logger->pushHandler(new NullHandler());

        $this->tokenHandler = $this->getMockBuilder(Token::class)
            ->setConstructorArgs(['foo', 'bar', ['logger' => $logger]])
            ->onlyMethods(['getConnectionHandler'])
            ->getMock();

        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createClient'])
            ->addMethods(['request'])
            ->getMock();
    }

    /**
     * @dataProvider getStream
     * @param StreamInterface $stream
     * @param null|\Exception $exception
     */
    public function testRequest(StreamInterface $stream, ?\Exception $exception)
    {
        $code = $exception === null ? 200 : $exception->getCode();
        $mock = new MockHandler([
            new Response($code, [], $stream),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->once())->method('createClient')->willReturn($client);
        $this->tokenHandler->expects($this->once())->method('getConnectionHandler')->willReturn($this->connection);
        if ($exception !== null) {
            $this->expectExceptionCode($code);
        }

        $token = $this->tokenHandler->request('123', 'symplicity.com');
        $this->assertInstanceOf(TokenInterface::class, $token);
        $this->assertNotEmpty($token->getAccessToken());
        $this->assertNotEmpty($token->getRefreshToken());
        $this->assertNotEmpty($token->getExpiresIn());
        $this->assertEquals('foobar@bar.com', $token->getEmailAddress());
        $this->assertEquals('Foo Bar', $token->getDisplayName());
        $this->assertEquals('code', $token->getType());
        $this->assertInstanceOf(\DateTimeInterface::class, $token->tokenReceivedOn());
        $this->assertEquals('abc', $token->getIdToken());
        $this->assertEquals('Foo Bar', (string) $token);
    }

    /**
     * @dataProvider getStream
     * @param StreamInterface $stream
     * @param null|\Exception $exception
     */
    public function testRefresh(StreamInterface $stream, ?\Exception $exception)
    {
        $code = $exception === null ? 200 : $exception->getCode();
        $mock = new MockHandler([
            new Response($code, [], $stream),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->connection->expects($this->once())->method('createClient')->willReturn($client);
        $this->tokenHandler->expects($this->once())->method('getConnectionHandler')->willReturn($this->connection);
        if ($exception !== null) {
            $this->expectExceptionCode($code);
        }

        $token = $this->tokenHandler->refresh('123', 'symplicity.com');
        $this->assertInstanceOf(TokenInterface::class, $token);
        $this->assertNotEmpty($token->getAccessToken());
        $this->assertNotEmpty($token->getRefreshToken());
        $this->assertNotEmpty($token->getExpiresIn());
    }

    public function testAuthorizationUrl()
    {
        $authUrl = $this->tokenHandler->getAuthorizationUrl(['abc'], 'test.com');
        $this->assertEquals('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=foo&redirect_uri=test.com&response_type=code&scope=openid+offline_access+https%3A%2F%2Foutlook.office.com%2Fcalendars.readwrite&state=%5B%22abc%22%5D', $authUrl);

        $authUrl = $this->tokenHandler->getAuthorizationUrl(['123'], 'test.com');
        $this->assertEquals('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=foo&redirect_uri=test.com&response_type=code&scope=openid+offline_access+https%3A%2F%2Foutlook.office.com%2Fcalendars.readwrite&state=%5B%22123%22%5D', $authUrl);
    }

    public function getStream()
    {
        return [
            [
                stream_for('{
                  "token_type": "code",
                  "access_token": "abc",
                  "refresh_token": "bcf",
                  "expires_in": 10800,
                  "id_token": "abc",
                  "userInfo": {
                      "EmailAddress" : "foobar@bar.com",
                      "DisplayName": "Foo Bar"
                  }
                }'), null
            ],
            [
                stream_for('test'), new \RuntimeException('Wrong Info', 400)
            ]
        ];
    }
}
