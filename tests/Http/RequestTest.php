<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Http;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use function GuzzleHttp\Psr7\stream_for;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\Request;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\Http\RequestOptionsInterface;
use Symplicity\Outlook\Interfaces\Http\ResponseIteratorInterface;
use Symplicity\Outlook\Utilities\RequestType;

class RequestTest extends TestCase
{
    public function testGetEvents()
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();

        $request = new Request('foo', [
            'requestOptions' => function () {
                return new RequestOptions('test.com', RequestType::Get(), [
                    'token' => 'foo'
                ]);
            },
            'connection' => $connection
        ]);

        $connection->expects($this->once())->method('get')->willReturn(new Response(200, ['foo' => 'bar'], stream_for('test')));
        $response = $request->getEvents('test.com', ['headers' => ['foo' => 'bar']]);
        $this->assertInstanceOf(ResponseIteratorInterface::class, $response->getResponseIterator());
        $this->assertInstanceOf(\Closure::class, $response->getRequestOptions());

        /** @var RequestOptionsInterface $requestOptions */
        $requestOptions = $response->getRequestOptions()->call($this);
        $this->assertInstanceOf(RequestOptionsInterface::class, $requestOptions);
        $this->assertEquals(RequestType::Get, $requestOptions->getMethod());
    }

    public function testGetHeadersWithToken()
    {
         $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['get', 'batch'])
            ->getMock();

        $constructorArgs = [
            'requestOptions' => function () {
                return new RequestOptions('test.com', RequestType::Get(), [
                    'token' => 'foo'
                ]);
            },
            'connection' => $connection
        ];
        $request = new Request('foo', $constructorArgs);

        $this->assertEmpty($request->getHeadersWithToken('test.com'));
    }

    public function testGetHeaders()
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['get', 'batch'])
            ->getMock();

        $request = new Request('foo', [
            'requestOptions' => function () {
                return new RequestOptions('test.com', RequestType::Get(), [
                    'token' => 'foo'
                ]);
            },
            'connection' => $connection
        ]);

        $this->assertNotEmpty($request->getHeaders('test.com', [
            'headers' => [],
            'timezone' => RequestOptions::DEFAULT_TIMEZONE,
            'preferenceHeaders' => [],
            'token' => '123'
        ]));

        $this->assertNotEmpty($request->getHeaders('test.com', []));

        $this->assertNotEmpty($request->getHeaders('', []));
    }
}
