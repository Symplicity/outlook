<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Utilities\CalendarView;

use Microsoft\Kiota\Serialization\Json\JsonSerializationWriter;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Utilities\CalendarView\PageResult;

class PageResultTest extends TestCase
{
    public function testResult()
    {
        $pageResult = new PageResult();
        $pageResult->setOdataDeltaLink('https://graph.microsoft.com/v1.0/me/calendarView/delta?$deltaToken=-deltaToken==');
        $result = $pageResult->getFieldDeserializers();
        $this->assertArrayHasKey('@odata.deltaLink', $result);

        $serializationWriter = new JsonSerializationWriter();
        $pageResult->serialize($serializationWriter);
        $serializedContent = $serializationWriter->getSerializedContent()->getContents();
        $this->assertMatchesRegularExpression('/@odata.deltaLink/', $serializedContent);
        $this->assertMatchesRegularExpression('/-deltaToken==/', $serializedContent);
    }
}
