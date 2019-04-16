<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Entities;

use Symplicity\Outlook\Entities\Occurrence;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Utilities\EventTypes;

class OccurrenceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getOccurrences
     * @param string $jsonData
     */
    public function testHydrate(string $jsonData)
    {
        $occurrence = (new Occurrence())->hydrate(\GuzzleHttp\json_decode($jsonData, true));
        $this->assertInstanceOf(ReaderEntityInterface::class, $occurrence);
        $this->assertNotEmpty($occurrence->getId());
        $this->assertNotEmpty($occurrence->getETag());
        $this->assertInstanceOf(DateEntityInterface::class, $occurrence->getDate());
        $this->assertNotEmpty($occurrence->getSeriesMasterId());
        $this->assertEmpty($occurrence->getTitle());
        $this->assertEmpty($occurrence->getDescription());
        $this->assertEmpty($occurrence->getBody());
        $this->assertEmpty($occurrence->getLocation());
        $this->assertEmpty($occurrence->isAllDay());
        $this->assertEmpty($occurrence->getSensitivityStatus());
        $this->assertEmpty($occurrence->getVisibility());
        $this->assertEmpty($occurrence->getRecurrence());
        $this->assertEmpty($occurrence->getOrganizer());
        $this->assertEquals(EventTypes::Occurrence, $occurrence->getEventType());
    }

    public function getOccurrences()
    {
        return [
            ['{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1==\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"}}'],
            ['{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'129f7fa4-61ce-4b9f-\')\/Events(\'AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MAFRAAgI1pxGhEEAAEYAAAAAQT-FGzVQ0E2D76JKXt4TogcAghc-oDgwvEWAzon06Zf8fQAAAAABDQAAghc-\')","@odata.etag":"W\/\"DwAAABYAAACCFz+gODC8RYDOifTpl\/x9AAAHn\/+k\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLT=","SeriesMasterId":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAA=","Type":"Occurrence","Start":{"DateTime":"2019-02-27T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-28T00:00:00.0000000","TimeZone":"Eastern Standard Time"}}']

        ];
    }
}
