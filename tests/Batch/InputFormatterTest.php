<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Batch;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Batch\InputFormatter;
use Symplicity\Outlook\Batch\Stream;
use Symplicity\Outlook\Entities\Delete;
use Symplicity\Outlook\Entities\ODateTime;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Entities\Writer;
use Symplicity\Outlook\Utilities\RequestType;

class InputFormatterTest extends TestCase
{
    public function testPostFormat()
    {
        $handler = new TestHandler();
        $logger = new Logger('outlook-calendar', [$handler]);

        $writer = (new Writer())->setId('123')
            ->setBody(new ResponseBody(['Content' => 'test', 'ContentType' => 'HTML']))
            ->setSubject('ABC')
            ->method(new RequestType(RequestType::Post()))
            ->setStartDate(new ODateTime(new \DateTime('now'), 'Eastern Standard Time'))
            ->setEndDate(new ODateTime(new \DateTime('now'), 'Eastern Standard Time'))
            ->setInternalEventType('PHPUnit');

        $writerJson = json_encode($writer);
        $formatter = new InputFormatter($logger);
        $expected = [
            'name' => '123',
            'contents' => "POST /api/v2.0/Me/events?\$select=Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime HTTP/1.1\r\nContent-Type: application/json\r\n\r\n{$writerJson}\r\n",
            'headers' => [
                'Content-Type' => 'application/http',
                'Content-Transfer-Encoding' => 'binary',
                'Content-ID' => '123',
            ],
        ];

        $this->assertJsonStringEqualsJsonString(json_encode($expected), json_encode($formatter->format($writer)));

        // Error Test
        $streamHandler = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs([$writer])
            ->setMethods(['create'])
            ->getMock();

        $streamHandler->expects($this->once())->method('create')->willThrowException(new \RuntimeException('Unable to stream'));
        (new InputFormatter($logger, $streamHandler))->format($writer);
        $this->assertTrue($handler->hasErrorThatContains('unable to generate stream from data provided'));
    }

    public function testPutFormat()
    {
        $handler = new TestHandler();
        $logger = new Logger('outlook-calendar', [$handler]);

        $writer = (new Writer())->setId('123')
            ->setGuid('ABC==')
            ->setBody(new ResponseBody(['Content' => 'test', 'ContentType' => 'HTML']))
            ->setSubject('ABC')
            ->method(new RequestType(RequestType::Put()))
            ->setStartDate(new ODateTime(new \DateTime('now'), 'Eastern Standard Time'))
            ->setEndDate(new ODateTime(new \DateTime('now'), 'Eastern Standard Time'))
            ->setInternalEventType('PHPUnit');

        $writerJson = json_encode($writer);
        $formatter = new InputFormatter($logger);
        $expected = [
            'name' => '123',
            'contents' => "PUT /api/v2.0/Me/events/ABC==?\$select=Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime HTTP/1.1\r\nContent-Type: application/json\r\n\r\n{$writerJson}\r\n",
            'headers' => [
                'Content-Type' => 'application/http',
                'Content-Transfer-Encoding' => 'binary',
                'Content-ID' => '123',
            ],
        ];

        $this->assertJsonStringEqualsJsonString(json_encode($expected), json_encode($formatter->format($writer)));
    }

    public function testDeleteFormat()
    {
        $handler = new TestHandler();
        $logger = new Logger('outlook-calendar', [$handler]);

        $delete = new Delete('ABC==', '123');

        $deleteJSON = json_encode($delete);
        $formatter = new InputFormatter($logger);
        $expected = [
            'name' => '123',
            'contents' => "DELETE /api/v2.0/me/events/ABC==?\$select=Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime HTTP/1.1\r\nContent-Type: application/json\r\n\r\n{}\r\n",
            'headers' => [
                'Content-Type' => 'application/http',
                'Content-Transfer-Encoding' => 'binary',
                'Content-ID' => '123',
            ],
        ];

        $this->assertJsonStringEqualsJsonString(json_encode($expected), json_encode($formatter->format($delete)));
    }
}
