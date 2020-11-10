<?php

namespace Symplicity\Outlook\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\Organizer;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\LocationInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\RecurrenceEntityInterface;
use Symplicity\Outlook\Utilities\DayOfTheWeek;
use Symplicity\Outlook\Utilities\EventTypes;
use Symplicity\Outlook\Utilities\PatternType;
use Symplicity\Outlook\Utilities\RangeType;
use Symplicity\Outlook\Utilities\RecurrenceIndex;

class ReaderTest extends TestCase
{
    public function testHydrate()
    {
        $jsonData = $this->getJsonData();
        $reader = (new Reader())->hydrate(\GuzzleHttp\json_decode($jsonData, true));
        $this->assertInstanceOf(ReaderEntityInterface::class, $reader);
        $this->assertInstanceOf(LocationInterface::class, $reader->getLocation());
        $this->assertInstanceOf(DateEntityInterface::class, $reader->getDate());
        $this->assertInstanceOf(ResponseBody::class, $reader->getBody());
        $this->assertInstanceOf(RecurrenceEntityInterface::class, $reader->getRecurrence());
        $this->assertInstanceOf(Organizer::class, $reader->getOrganizer());
        $this->assertEquals(EventTypes::Master, $reader->getEventType());
        $this->assertEquals('W/"ghc/foo//pA=="', $reader->getETag());
        $this->assertNotEmpty($reader->getId());
        $this->assertNotEmpty($reader->getWebLink());
        $this->assertNotEmpty($reader->getTitle());
        $this->assertNotEmpty($reader->getDescription());
        $this->assertNotEmpty($reader->getBody());
        $this->assertNotEmpty($reader->getLocation());
        $this->assertNotEmpty($reader->isAllDay());
        $this->assertNotEmpty($reader->getSensitivityStatus());
        $this->assertNotEmpty($reader->getVisibility());
        $this->assertNotEmpty($reader->getRecurrence());
        $this->assertNotEmpty($reader->getOrganizer());
        $this->assertNotEmpty($reader->getDate()->getStartDate());
        $this->assertNotEmpty($reader->getDate()->getEndDate());
        $this->assertNotEmpty($reader->getDate()->getModifiedDate());
        $this->assertNotEmpty($reader->getDate()->getTimezone());
        $this->assertTrue($reader->getBody()->isHTML());
        $this->assertFalse($reader->getBody()->isText());
        $this->assertEquals('foo@bar.com', $reader->getOrganizer()->getEmail());
        $this->assertEquals('Outlook Test', $reader->getOrganizer()->getName());
        $this->assertEquals(PatternType::Daily, $reader->getRecurrence()->getType());
        $this->assertEquals(1, $reader->getRecurrence()->getInterval());
        $this->assertEquals(RecurrenceIndex::first, $reader->getRecurrence()->getIndex());
        $this->assertEquals(RangeType::EndDate, $reader->getRecurrence()->getRangeType());
        $this->assertEquals(RangeType::EndDate, $reader->getRecurrence()->getRangeType());
        $this->assertEquals(0, $reader->getRecurrence()->getNumberOfOccurrences());
        $this->assertEquals(0, $reader->getRecurrence()->getMonth());
        $this->assertEquals(DayOfTheWeek::Sunday, $reader->getRecurrence()->getFirstDayOfWeek()->getValue());
        $this->assertEquals([], $reader->getRecurrence()->getDaysOfWeek());
        $this->assertEquals(0, $reader->getRecurrence()->getDayOfMonth());
        $this->assertInstanceOf(DateEntityInterface::class, $reader->getRecurrence()->getRangeDates());
        $this->assertEquals('2019-02-25', $reader->getRecurrence()->getRangeDates()->getStartDate());
        $this->assertEquals('2019-02-28', $reader->getRecurrence()->getRangeDates()->getEndDate());
        $this->assertEquals('Eastern Standard Time', $reader->getRecurrence()->getRangeDates()->getTimezone());
        $this->assertEquals(null, $reader->getRecurrence()->getRangeDates()->getModifiedDate());
        $this->assertTrue(is_array($reader->toArray()));
    }

    public function getJsonData()
    {
        return '{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'foo\')\/Events(\'x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=\')","@odata.etag":"W\/\"ghc\/foo\/\/pA==\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","CreatedDateTime":"2019-02-01T18:05:03.7354577-05:00","LastModifiedDateTime":"2019-02-04T23:58:49.478552-05:00","ChangeKey":"foo\/\/pA==","Categories":[],"OriginalStartTimeZone":"Eastern Standard Time","OriginalEndTimeZone":"Eastern Standard Time","iCalUId":"foo","ReminderMinutesBeforeStart":15,"IsReminderOn":true,"HasAttachments":false,"Subject":"FooBar","BodyPreview":"CCCCCCC","Importance":"Normal","Sensitivity":"Normal","IsAllDay":true,"IsCancelled":false,"IsOrganizer":false,"ResponseRequested":true,"SeriesMasterId":null,"ShowAs":"Free","Type":"SeriesMaster","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=foo%3D&exvsurl=1&path=\/calendar\/item","OnlineMeetingUrl":null,"ResponseStatus":{"Response":"Accepted","Time":"2019-02-01T18:05:25.680242-05:00"},"Body":{"ContentType":"HTML","Content":"test"},"Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"India","PostalCode":""},"Coordinates":{"Latitude":27.6031,"Longitude":88.6468}},"Locations":[{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"US","PostalCode":""},"Coordinates":{"Latitude":32.6031,"Longitude":999.6468}}],"Recurrence":{"Pattern":{"Type":"Daily","Interval":1,"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First"},"Range":{"Type":"EndDate","StartDate":"2019-02-25","EndDate":"2019-02-28","RecurrenceTimeZone":"Eastern Standard Time","NumberOfOccurrences":0}},"Attendees":[{"Type":"Required","Status":{"Response":"None","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}},{"Type":"Required","Status":{"Response":"Accepted","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Insight Test","Address":"test"}}],"Organizer":{"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}}}';
    }
}
