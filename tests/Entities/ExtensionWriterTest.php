<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\ExtensionWriter;
use Symplicity\Outlook\Exception\WriteError;

class ExtensionWriterTest extends TestCase
{
    public function testJsonSerialize()
    {
        $extWriter = $this->getMockForAbstractClass(ExtensionWriter::class, [[
            '@odata.type' => 'test',
            'ExtensionName' => 'Microsoft.test'
        ]]);

        $this->assertEquals('Microsoft.test', $extWriter->getExtensionName());
        $this->assertJsonStringEqualsJsonString('{"@odata.type":"test","ExtensionName":"Microsoft.test"}', json_encode($extWriter));

        $extWriter = $this->getMockForAbstractClass(ExtensionWriter::class, [[
            'ExtensionName' => 'Microsoft.test'
        ]]);

        $this->expectException(WriteError::class);
        $extWriter->jsonSerialize();
    }
}
