<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Exception\ResponseIteratorException;

class ResponseIteratorExceptionTest extends TestCase
{
    public function testResponse()
    {
        $exception = new ResponseIteratorException('ABC');
        $exception->setResponse(['ab' => 'test']);

        $this->assertArrayHasKey('ab', $exception->getResponse());
    }
}
