<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\BatchErrorEntity;

class BatchErrorEntityTest extends TestCase
{
    public function testError()
    {
        $data = [
            'id' => 'bar1',
            'status' => 400,
            'headers' => [
                'etag' => 'W/"123=="',
                'location' => 'https://outlook.office.com/api/v2.0/Users(\'123@345\')/Events(\'ABC==\')',
                'odata-version' => '4.0',
                'content-type' => 'application/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8',
            ],
            'body' => [
                'error' => [
                    'code' => 'InvalidParams',
                    'message' => 'Invalid params passed to outlook',
                ],
            ],
        ];

        $errorEntity = new BatchErrorEntity($data);
        $this->assertEquals('bar1', $errorEntity->getId());
        $this->assertEquals('400', $errorEntity->getStatusCode());
        $this->assertEquals('InvalidParams', $errorEntity->getErrorCode());
        $this->assertEquals('Invalid params passed to outlook', $errorEntity->getReason());

        $errorEntity = new BatchErrorEntity([]);
        $this->assertEquals(null, $errorEntity->getId());
        $this->assertEquals(0, $errorEntity->getStatusCode());
        $this->assertEquals(BatchErrorEntity::UNKNOWN_ERROR_CODE, $errorEntity->getErrorCode());
        $this->assertEquals(null, $errorEntity->getReason());
    }
}
