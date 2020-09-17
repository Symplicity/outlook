<?php

namespace Symplicity\Outlook\Tests\Http;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Utilities\RequestType;

class RequestOptionsTest extends TestCase
{
    public function testHeaders()
    {
        $requestOptions = new RequestOptions('api/outlook.php', new RequestType(RequestType::Get), [
            'headers' => ['foo' => 'bar'],
            'queryParams' => [1 => 2, 'delta' => 'foo=='],
            'token' => 'abc'
        ]);

        $requestOptions->addDefaultHeaders();
        $headers = $requestOptions->getHeaders();
        $res = $requestOptions->toArray();
        $this->assertArrayHasKey('url', $res);

        $this->assertArrayHasKey('foo', $headers);
        $this->assertArrayHasKey('client-request-id', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer abc', $headers['Authorization']);
        $clientId = $headers['client-request-id'];

        $requestOptions->resetUUID();
        $this->assertNotEquals($clientId, $requestOptions->getHeaders()['client-request-id']);

        $requestOptions = new RequestOptions('api/outlook.php', new RequestType(RequestType::Get), [
            'headers' => ['foo' => 'bar'],
            'queryParams' => [1 => 2],
        ]);

        $rawHeaders = $requestOptions->getRawHeaders();
        $this->assertTrue(is_array($rawHeaders));
        $this->assertEquals('foo:bar', $rawHeaders[0]);

        $this->expectExceptionMessage('Missing Token');
        $requestOptions->addDefaultHeaders();
    }
}
