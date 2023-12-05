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
        $data = '{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"7DBtS36oekqlFVL\/lW3rKQAACGSF4A==\"","id":"AAA==","createdDateTime":"2023-11-30T14:36:55.5257905Z","lastModifiedDateTime":"2023-11-30T14:36:56.9024398Z","changeKey":"FVL\/lW3rKQAACGSF4A==","transactionId":null,"originalStartTimeZone":"Eastern Standard Time","originalEndTimeZone":"Eastern Standard Time","iCalUId":"foo_uid","reminderMinutesBeforeStart":15,"isReminderOn":true,"hasAttachments":false,"subject":"Foo test","bodyPreview":"Testing Reader Interface","importance":"normal","sensitivity":"normal","isAllDay":false,"isCancelled":false,"isOrganizer":true,"responseRequested":true,"seriesMasterId":null,"showAs":"busy","type":"singleInstance","webLink":"https:\/\/outlook.office365.com\/owa\/?itemid=AAA===1&path=\/calendar\/item","onlineMeetingUrl":null,"isOnlineMeeting":false,"onlineMeetingProvider":"unknown","allowNewTimeProposals":true,"occurrenceId":null,"isDraft":false,"hideAttendees":false,"body":{"contentType":"html","content":"<html><head><meta http-equiv=\"Content-Type\" content=\"text\/html; charset=utf-8\">\n<meta name=\"Generator\" content=\"Microsoft Exchange Server\">\n<!-- converted from text -->\n<style><!-- .EmailQuote { margin-left: 1pt; padding-left: 4pt; border-left: #800000 2px solid; } --><\/style><\/head>\n<body>\n<font size=\"2\"><span style=\"font-size:11pt;\"><div class=\"PlainText\">Testing Reader Interface<\/div><\/span><\/font>\n<\/body>\n<\/html>\n"},"start":{"dateTime":"2023-12-05T18:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-05T19:00:00.0000000","timeZone":"UTC"},"location":{"displayName":"Sikkim","locationType":"default","uniqueIdType":"unknown"},"recurrence":null,"organizer":{"emailAddress":{"name":"Outlook Test","address":"foo@bar.com"}}}';

        $json = new JsonParseNode(json_decode($data, true));
        return $json->getObjectValue([Event::class, 'createFromDiscriminatorValue']);
    }

    /**
     * @throws \Exception
     */
    public function getRecurringEventData(): Event
    {
        $data = '{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==\"","id":"TPY=","createdDateTime":"2023-12-05T06:17:55.551725Z","lastModifiedDateTime":"2023-12-05T06:17:56.9028469Z","changeKey":"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==","transactionId":"eea2822c-5583-8a5a-a074-2f3f0d75f042","originalStartTimeZone":"Eastern Standard Time","originalEndTimeZone":"Eastern Standard Time","iCalUId":"0100000000000000001000000098F5720C81F7EF4EA03A9B578D28E7DF","reminderMinutesBeforeStart":15,"isReminderOn":true,"hasAttachments":false,"subject":"R - 1","bodyPreview":"test","importance":"normal","sensitivity":"normal","isAllDay":false,"isCancelled":false,"isOrganizer":true,"responseRequested":true,"seriesMasterId":null,"showAs":"busy","type":"seriesMaster","webLink":"https:\/\/outlook.office365.com\/owa\/?itemid=TPY==1&path=\/calendar\/item","onlineMeetingUrl":null,"isOnlineMeeting":false,"onlineMeetingProvider":"unknown","allowNewTimeProposals":true,"occurrenceId":null,"isDraft":false,"hideAttendees":false,"responseStatus":{"response":"organizer","time":"0001-01-01T00:00:00Z"},"start":{"dateTime":"2023-12-05T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-05T07:30:00.0000000","timeZone":"UTC"},"location":{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"},"locations":[{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"}],"recurrence":{"pattern":{"type":"daily","interval":1,"month":0,"dayOfMonth":0,"firstDayOfWeek":"sunday","index":"first"},"range":{"type":"endDate","startDate":"2023-12-05","endDate":"2023-12-07","recurrenceTimeZone":"Eastern Standard Time","numberOfOccurrences":0}},"organizer":{"emailAddress":{"name":"Outlook Test","address":"foo@bar.com"}}}';

        $json = new JsonParseNode(json_decode($data, true));
        return $json->getObjectValue([Event::class, 'createFromDiscriminatorValue']);
    }
}
