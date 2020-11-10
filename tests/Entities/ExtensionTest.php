<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\Extension;

class ExtensionTest extends TestCase
{
    /**
     * @dataProvider getExtensionData
     * @param array $data
     */
    public function testGetter(array $data)
    {
        $extension = new Extension($data);
        $this->assertEquals('Microsoft.OutlookServices.OpenTypeExtension.com.symplicity.outlook', $extension->getId());
        $this->assertEquals('com.symplicity.outlook', $extension->getExtensionName());
        $this->assertEquals('0xE1123', $extension->policyId);
        $this->assertEquals(null, $extension->test123);
        $this->assertEquals('#Microsoft.OutlookServices.OpenTypeExtension', $extension->getODataType());
        $this->assertEquals('https://outlook.office.com/api/v2.0/Users(\'ABC\')/Events(\'BCD==\')/Extensions(\'Microsoft.OutlookServices.OpenTypeExtension.com.symplicity.outlook\')', $extension->getODataId());
    }

    public function getExtensionData()
    {
        return [
            [[
                "@odata.type" => "#Microsoft.OutlookServices.OpenTypeExtension",
                "@odata.id" => "https://outlook.office.com/api/v2.0/Users('ABC')/Events('BCD==')/Extensions('Microsoft.OutlookServices.OpenTypeExtension.com.symplicity.outlook')",
                "Id" => "Microsoft.OutlookServices.OpenTypeExtension.com.symplicity.outlook",
                "ExtensionName" => "com.symplicity.outlook",
                "policyId" => '0xE1123'
            ]]
        ];
    }
}
