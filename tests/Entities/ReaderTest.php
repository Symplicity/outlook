<?php

namespace Symplicity\Outlook\Tests\Entities;

use Microsoft\Graph\Generated\Models\DayOfWeek;
use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Graph\Generated\Models\FreeBusyStatus;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Location;
use Microsoft\Graph\Generated\Models\Recipient;
use Microsoft\Graph\Generated\Models\RecurrencePatternType;
use Microsoft\Graph\Generated\Models\RecurrenceRangeType;
use Microsoft\Graph\Generated\Models\WeekIndex;
use Microsoft\Kiota\Serialization\Json\JsonParseNode;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Tests\resources\OutlookTestHandler;

class ReaderTest extends TestCase
{
    public function testHydrateSingleInstance()
    {
        try {
            $event = $this->getSingleInstanceJsonData();
            $reader = new Reader();
            $reader->hydrate($event);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertInstanceOf(ReaderEntityInterface::class, $reader);
        $this->assertInstanceOf(Location::class, $reader->getLocation());
        $this->assertInstanceOf(DateEntityInterface::class, $reader->getDate());
        $this->assertInstanceOf(ItemBody::class, $reader->getBody());
        $this->assertEquals(EventType::SINGLE_INSTANCE, $reader->getEventType()->value());
        $this->assertEquals('W/"7DBtS36oekqlFVL/lW3rKQAACGSF4A=="', $reader->getETag());
        $this->assertNotEmpty($reader->getId());
        $this->assertNotEmpty($reader->getWebLink());
        $this->assertNotEmpty($reader->getTitle());
        $this->assertNotEmpty($reader->getDescription());
        $this->assertNotEmpty($reader->getBody());
        $this->assertNotEmpty($reader->getLocation());
        $this->assertFalse($reader->isAllDay());
        $this->assertNotEmpty($reader->getSensitivityStatus());
        $this->assertNotEmpty($reader->getVisibility());
        $this->assertEmpty($reader->getRecurrence());
        $this->assertInstanceOf(Recipient::class, $reader->getOrganizer());
        $this->assertNotEmpty($reader->getDate()->getStartDate());
        $this->assertNotEmpty($reader->getDate()->getEndDate());
        $this->assertNotEmpty($reader->getDate()->getModifiedDate());
        $this->assertNotEmpty($reader->getDate()->getTimezone());
        $this->assertSame(FreeBusyStatus::BUSY, $reader->getFreeBusyStatus()->value());
        $this->assertMatchesRegularExpression('/Testing Reader Interface/', $reader->getBody()?->getContent());
        $this->assertEquals('foo@bar.com', $reader->getOrganizer()?->getEmailAddress()?->getAddress());
        $this->assertEquals('Outlook Test', $reader->getOrganizer()?->getEmailAddress()?->getName());
        $this->assertTrue(is_array($reader->toArray()));


        $originalEvent = $reader->getOriginalEvent();
        $this->assertInstanceOf(Event::class, $originalEvent);
        $this->assertSame(FreeBusyStatus::BUSY, $originalEvent->getShowAs()->value());
        $this->assertSame(EventType::SINGLE_INSTANCE, $originalEvent->getType()->value());
    }

    public function testHydrateRecurringInstance()
    {
        try {
            $event = $this->getRecurringEventData();
            $reader = new Reader();
            $reader->hydrate($event);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertInstanceOf(ReaderEntityInterface::class, $reader);
        $this->assertInstanceOf(Location::class, $reader->getLocation());
        $this->assertInstanceOf(DateEntityInterface::class, $reader->getDate());
        $this->assertNull($reader->getBody());
        $this->assertEquals(EventType::SERIES_MASTER, $reader->getEventType()->value());
        $this->assertSame(FreeBusyStatus::BUSY, $reader->getFreeBusyStatus()->value());
        $this->assertEquals('foo@bar.com', $reader->getOrganizer()?->getEmailAddress()?->getAddress());
        $this->assertEquals('Outlook Test', $reader->getOrganizer()?->getEmailAddress()?->getName());
        $this->assertEquals(RecurrencePatternType::DAILY, $reader->getRecurrence()?->getType()?->value());
        $this->assertEquals(1, $reader->getRecurrence()->getInterval());
        $this->assertEquals(WeekIndex::FIRST, $reader->getRecurrence()?->getIndex()?->value());
        $this->assertEquals(RecurrenceRangeType::END_DATE, $reader->getRecurrence()?->getRangeType()?->value());
        $this->assertEquals(0, $reader->getRecurrence()->getNumberOfOccurrences());
        $this->assertEquals(0, $reader->getRecurrence()->getMonth());
        $this->assertEquals(DayOfWeek::SUNDAY, $reader->getRecurrence()?->getFirstDayOfWeek()?->value());
        $this->assertEquals([], $reader->getRecurrence()->getDaysOfWeek());
        $this->assertEquals(0, $reader->getRecurrence()->getDayOfMonth());
        $this->assertInstanceOf(DateEntityInterface::class, $reader->getRecurrence()->getRangeDates());
        $this->assertEquals('2023-12-05', $reader->getRecurrence()->getRangeDates()->getStartDate());
        $this->assertEquals('2023-12-07', $reader->getRecurrence()->getRangeDates()->getEndDate());
        $this->assertEquals('Eastern Standard Time', $reader->getRecurrence()->getRangeDates()->getTimezone());
        $this->assertEquals(null, $reader->getRecurrence()->getRangeDates()->getModifiedDate());
    }

    /**
     * @throws \Exception
     */
    public function getSingleInstanceJsonData(): Event
    {
        $data = OutlookTestHandler::getSingleInstanceInJsonFormat();
        $json = new JsonParseNode(json_decode($data, true));
        return $json->getObjectValue([Event::class, 'createFromDiscriminatorValue']);
    }

    /**
     * @throws \Exception
     */
    public function getRecurringEventData(): Event
    {
        $data = OutlookTestHandler::getSeriesMasterInstanceInJsonFormat();
        $json = new JsonParseNode(json_decode($data, true));
        return $json->getObjectValue([Event::class, 'createFromDiscriminatorValue']);
    }
}
