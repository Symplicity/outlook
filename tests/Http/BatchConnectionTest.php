<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Batch\Response as BatchResponse;
use Symplicity\Outlook\Entities\BatchErrorEntity;
use Symplicity\Outlook\Entities\BatchResponseDeleteEntity;
use Symplicity\Outlook\Entities\BatchResponseReader;
use Symplicity\Outlook\Entities\Delete;
use Symplicity\Outlook\Entities\ODateTime;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Entities\Writer;
use Symplicity\Outlook\Exception\BatchBoundaryMissingException;
use Symplicity\Outlook\Exception\BatchLimitExceededException;
use Symplicity\Outlook\Exception\BatchRequestEmptyException;
use Symplicity\Outlook\Http\Batch;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\Batch\FormatterInterface;
use Symplicity\Outlook\Interfaces\Entity\BatchWriterEntityInterface;
use Symplicity\Outlook\Utilities\RequestType;

class BatchConnectionTest extends TestCase
{
    private $connection;
    private $handler;

    public function setUp()
    {
        $this->handler = new TestHandler();
        $logger = new Logger('outlook-calendar', [$this->handler]);
        $this->connection = $this->getMockBuilder(Batch::class)
            ->setConstructorArgs(['logger' => $logger])
            ->setMethods(['createClientWithRetryHandler', 'createClient', 'upsertRetryDelay'])
            ->getMock();
    }

    public function testBatch()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"responses":[{"id":"foo","status":201,"headers":{"etag":"W\/\"123==\"","location":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","odata-version":"4.0","content-type":"application\/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8"},"body":{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/Events(Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime)\/$entity","@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","@odata.etag":"W\/\"123==\"","Id":"test==","LastModifiedDateTime":"2020-11-09T14:40:50.8444665-05:00","Subject":"ABC","SeriesMasterId":null,"Type":"SingleInstance","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=ANC%3D%3D&exvsurl=1&path=\/calendar\/item"}},{"id":"bar","status":201,"headers":{"etag":"W\/\"456==\"","location":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'CDE==\')","odata-version":"4.0","content-type":"application\/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8"},"body":{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/Events(Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime)\/$entity","@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'CDE==\')","@odata.etag":"W\/\"aHQ+t811Ok+IYnQ4RgjubgACguszQg==\"","Id":"CDE==","LastModifiedDateTime":"2020-11-09T14:40:51.1413001-05:00","Subject":"ABC","SeriesMasterId":null,"Type":"SingleInstance","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=cde&path=\/calendar\/item"}},{"id":"foo1","status":201,"headers":{"etag":"W\/\"123==\"","location":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","odata-version":"4.0","content-type":"application\/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8"},"body":{"@odata.context":"https:\/\/outlook.office.com\/api\/v2.0\/$metadata#Me\/Events(Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime)\/$entity","@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","@odata.etag":"W\/\"123==\"","Id":"test==","LastModifiedDateTime":"2020-11-09T14:40:50.8444665-05:00","Subject":"ABC","SeriesMasterId":null,"Type":"SingleInstance","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=ANC%3D%3D&exvsurl=1&path=\/calendar\/item"}}, {"id":"bar1","status":400,"headers":{"etag":"W\/\"123==\"","location":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'123@345\')\/Events(\'ABC==\')","odata-version":"4.0","content-type":"application\/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8"},"body":{"error": {"code": "InvalidParams", "message": "Invalid params passed"}}}, {"id":"2323","status":204,"headers":[]}]}'),
            new Response(429, ['Content-Length' => 0, 'Retry-After' => 2], stream_for('Client Error')),
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
        $this->assertEquals('odata.continue-on-error', $requestOptions->getHeaders()['Prefer']);
        $this->assertEquals('application/json', $requestOptions->getHeaders()['Accept']);
        $this->assertRegExp('/multipart\/mixed; boundary=batch_/', $requestOptions->getHeaders()['Content-Type']);

        $this->createRetryHandler($mock);

        $this->connection->expects($this->exactly(2))->method('upsertRetryDelay');
        $response = $this->connection->batch($requestOptions);
        $this->assertInstanceOf(BatchResponse::class, $response);
        foreach ($response as $key => $value) {
            /** @var BatchResponseInterface $oResponse */
            $oResponse = $value['response'];
            if ($key == 'bar1') {
                $this->assertInstanceOf(BatchErrorEntity::class, $oResponse);
                $this->assertTrue(isset($value['item']['statusCode']));
                $this->assertTrue($value['item']['statusCode'] >= 400);
            } elseif ($key == '2323') {
                $this->assertInstanceOf(BatchResponseDeleteEntity::class, $oResponse);
                $this->assertTrue($value['item']['statusCode'] == 204);
            } else {
                $this->assertInstanceOf(BatchResponseReader::class, $oResponse);
                $this->assertNotNull($oResponse->getLastModifiedDateTime());
                $this->assertTrue(in_array($value['item']['statusCode'], [200, 201, 204]));
            }

            $this->assertTrue(is_array($value['item']));
            $this->assertArrayHasKey('eventType', $value['item']);
        }

        $response = $this->connection->batch($requestOptions);
        $this->assertNull($response);
    }

    public function testBatchBoundaryMissingException()
    {
        $requestOptions = new RequestOptions('test', new RequestType(RequestType::Post), ['token' => 'ABC12==']);
        $this->expectExceptionObject(new BatchBoundaryMissingException('batch boundary id is missing'));
        $this->connection->batch($requestOptions);
    }

    public function testBatchLimitExceededException()
    {
        $events = [];
        for ($i = 0; $i < 22; $i++) {
            $events[] = (new Writer())->setId("foo{$i}")
                ->setBody(new ResponseBody(['Content' => 'test', 'ContentType' => 'HTML']))
                ->setSubject('ABC');
        }

        $requestOptions = new RequestOptions('test', new RequestType(RequestType::Post), ['token' => 'ABC12==']);
        $requestOptions->addBatchHeaders();
        $requestOptions->addBody($events);
        $this->expectExceptionObject(new BatchLimitExceededException('batch maximum limit of 20 items was exceeded'));
        $this->connection->batch($requestOptions);
    }

    public function testBatchBody()
    {
        $events = [];
        $customBatchFormmatter = new class() implements FormatterInterface {
            public function format(BatchWriterEntityInterface $writer): array
            {
                return [
                    'name' => 'foo1',
                    'contents' => [],
                    'headers' => [
                        'Content-Type' => static::CONTENT_TYPE,
                        'Content-Transfer-Encoding' => static::CONTENT_TRANSFER_ENCODING,
                        'Content-ID' => 'foo1'
                    ]
                ];
            }
        };

        $requestOptions = new RequestOptions('test', new RequestType(RequestType::Post), ['token' => 'ABC12==']);
        $requestOptions->addBatchHeaders();
        $requestOptions->addBody($events);
        $this->expectExceptionObject(new BatchRequestEmptyException('Batch request is empty'));
        $this->connection->batch($requestOptions, [
            'batchInputFormatter' => $customBatchFormmatter
        ]);
    }

    public function createRetryHandler(\Countable $mock)
    {
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $this->connection->expects($this->any())
            ->method('createClientWithRetryHandler')
            ->willReturn($client);
    }
}
