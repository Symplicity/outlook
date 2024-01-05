<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\resources;

use Generator;
use Microsoft\Graph\Generated\Models\DateTimeTimeZone;
use Microsoft\Graph\Generated\Models\Event as MsEvent;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Graph\Generated\Models\FreeBusyStatus;
use Microsoft\Graph\Generated\Models\Location;
use Microsoft\Graph\Generated\Models\Recipient;
use PHPUnit\Framework\Assert;
use Symplicity\Outlook\Calendar;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Models\Event;
use Symplicity\Outlook\Tests\CalendarTest;

class OutlookTestHandler extends Calendar
{
    private ?Assert $testCase = null;
    private bool $isInstancesCall = false;
    private ?string $seriesMasterId = null;

    public function saveEventLocal(ReaderEntityInterface $reader): void
    {
        if ($this->isInstancesCall) {
            $this->testCase?->assertSame($this->seriesMasterId, $reader->getSeriesMasterId());
            $this->testCase?->assertTrue($reader->getEventType()->value() === EventType::OCCURRENCE);
            return;
        } elseif ($reader->getEventType()->value() === EventType::OCCURRENCE) {
            $this->testCase?->assertNotEmpty($reader->getId());
            $this->testCase?->assertNotEmpty($reader->getSeriesMasterId());
            return;
        }

        if ($reader->getEventType()->value() === EventType::SERIES_MASTER) {
            $this->testCase?->assertNotEmpty($reader->getRecurrence());
        }

        $this->testCase?->assertInstanceOf(ReaderEntityInterface::class, $reader);
        $this->testCase?->assertInstanceOf(Location::class, $reader->getLocation());
        $this->testCase?->assertInstanceOf(DateEntityInterface::class, $reader->getDate());
        $this->testCase?->assertNotEmpty($reader->getId());
        $this->testCase?->assertNotEmpty($reader->getWebLink());
        $this->testCase?->assertNotEmpty($reader->getTitle());
        $this->testCase?->assertNotEmpty($reader->getDescription());
        $this->testCase?->assertNotEmpty($reader->getLocation());
        $this->testCase?->assertFalse($reader->isAllDay());
        $this->testCase?->assertNotEmpty($reader->getSensitivityStatus());
        $this->testCase?->assertNotEmpty($reader->getVisibility());
        $this->testCase?->assertInstanceOf(Recipient::class, $reader->getOrganizer());
        $this->testCase?->assertNotEmpty($reader->getDate()->getStartDate());
        $this->testCase?->assertNotEmpty($reader->getDate()->getEndDate());
        $this->testCase?->assertNotEmpty($reader->getDate()->getModifiedDate());
        $this->testCase?->assertNotEmpty($reader->getDate()->getTimezone());
        $this->testCase?->assertSame(FreeBusyStatus::BUSY, $reader->getFreeBusyStatus()->value());
        $this->testCase?->assertSame('test', $reader->getDescription());
        $this->testCase?->assertEquals('foo@symplicity.com', $reader->getOrganizer()?->getEmailAddress()?->getAddress());
        $this->testCase?->assertEquals('Foo Test', $reader->getOrganizer()?->getEmailAddress()?->getName());
    }

    public function deleteEventLocal(?string $eventId): void
    {
    }

    public function getLocalEvents(): array
    {
        $start = new DateTimeTimeZone();
        $start->setTimeZone('Eastern Standard Time');
        $start->setDateTime('2023-12-05 13:00:00');

        $end = new DateTimeTimeZone();
        $end->setTimeZone('Eastern Standard Time');
        $end->setDateTime('2023-12-05 14:00:00');

        $event1 = new Event();
        $event1->setSubject('1');
        $event1->setStart($start);
        $event1->setEnd($end);

        $event2 = new Event();
        $event2->setSubject('2');
        $event2->setStart($start);
        $event2->setEnd($end);

        $event3 = new Event();
        $event3->setSubject('Changing Subject');
        $event3->setId('Foo==');
        $event3->setStart($start);
        $event3->setEnd($end);

        $event4 = new Event();
        $event4->setIsDelete();
        $event4->setId('ABC===');

        return [
            $event1,
            $event2,
            $event3,
            $event4
        ];
    }

    public function handleBatchResponse(?Generator $responses = null): void
    {
        foreach ($responses as $resp) {
            $this->testCase->assertArrayHasKey('guid', $resp['info']);
            if ($resp['info']['status'] === 204) {
                $this->testCase->assertEmpty($resp['event']);
                $this->testCase->assertSame('123-del', $resp['info']['id']);
                return;
            }

            $this->testCase->assertArrayHasKey('event', $resp);
            $this->testCase->assertArrayHasKey('info', $resp);
            $this->testCase->assertInstanceOf(MsEvent::class, $resp['event']);
            $this->testCase->assertSame(201, $resp['info']['status']);
            $this->testCase->assertSame('123', $resp['info']['id']);
        }
    }

    public function setTestCase(CalendarTest $testCase): OutlookTestHandler
    {
        $this->testCase = $testCase;
        return $this;
    }

    public function setIsInstancesCall(): OutlookTestHandler
    {
        $this->isInstancesCall = true;
        return $this;
    }

    public function setSeriesMasterId(?string $seriesMasterId): OutlookTestHandler
    {
        $this->seriesMasterId = $seriesMasterId;
        return $this;
    }

    public function reset(): void
    {
        $this->isInstancesCall = false;
        $this->seriesMasterId = null;
    }

    public static function getSingleInstanceInJsonFormat(): string
    {
        return '{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"7DBtS36oekqlFVL\/lW3rKQAACGSF4A==\"","id":"AAA==","createdDateTime":"2023-11-30T14:36:55.5257905Z","lastModifiedDateTime":"2023-11-30T14:36:56.9024398Z","changeKey":"FVL\/lW3rKQAACGSF4A==","transactionId":null,"originalStartTimeZone":"Eastern Standard Time","originalEndTimeZone":"Eastern Standard Time","iCalUId":"foo_uid","reminderMinutesBeforeStart":15,"isReminderOn":true,"hasAttachments":false,"subject":"Foo test","bodyPreview":"Testing Reader Interface","importance":"normal","sensitivity":"normal","isAllDay":false,"isCancelled":false,"isOrganizer":true,"responseRequested":true,"seriesMasterId":null,"showAs":"busy","type":"singleInstance","webLink":"https:\/\/outlook.office365.com\/owa\/?itemid=AAA===1&path=\/calendar\/item","onlineMeetingUrl":null,"isOnlineMeeting":false,"onlineMeetingProvider":"unknown","allowNewTimeProposals":true,"occurrenceId":null,"isDraft":false,"hideAttendees":false,"body":{"contentType":"html","content":"<html><head><meta http-equiv=\"Content-Type\" content=\"text\/html; charset=utf-8\">\n<meta name=\"Generator\" content=\"Microsoft Exchange Server\">\n<!-- converted from text -->\n<style><!-- .EmailQuote { margin-left: 1pt; padding-left: 4pt; border-left: #800000 2px solid; } --><\/style><\/head>\n<body>\n<font size=\"2\"><span style=\"font-size:11pt;\"><div class=\"PlainText\">Testing Reader Interface<\/div><\/span><\/font>\n<\/body>\n<\/html>\n"},"start":{"dateTime":"2023-12-05T18:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-05T19:00:00.0000000","timeZone":"UTC"},"location":{"displayName":"Sikkim","locationType":"default","uniqueIdType":"unknown"},"recurrence":null,"organizer":{"emailAddress":{"name":"Outlook Test","address":"foo@bar.com"}}}';
    }

    public static function getSeriesMasterInstanceInJsonFormat(): string
    {
        return '{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==\"","id":"TPY=","createdDateTime":"2023-12-05T06:17:55.551725Z","lastModifiedDateTime":"2023-12-05T06:17:56.9028469Z","changeKey":"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==","transactionId":"eea2822c-5583-8a5a-a074-2f3f0d75f042","originalStartTimeZone":"Eastern Standard Time","originalEndTimeZone":"Eastern Standard Time","iCalUId":"0100000000000000001000000098F5720C81F7EF4EA03A9B578D28E7DF","reminderMinutesBeforeStart":15,"isReminderOn":true,"hasAttachments":false,"subject":"R - 1","bodyPreview":"test","importance":"normal","sensitivity":"normal","isAllDay":false,"isCancelled":false,"isOrganizer":true,"responseRequested":true,"seriesMasterId":null,"showAs":"busy","type":"seriesMaster","webLink":"https:\/\/outlook.office365.com\/owa\/?itemid=TPY==1&path=\/calendar\/item","onlineMeetingUrl":null,"isOnlineMeeting":false,"onlineMeetingProvider":"unknown","allowNewTimeProposals":true,"occurrenceId":null,"isDraft":false,"hideAttendees":false,"responseStatus":{"response":"organizer","time":"0001-01-01T00:00:00Z"},"start":{"dateTime":"2023-12-05T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-05T07:30:00.0000000","timeZone":"UTC"},"location":{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"},"locations":[{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"}],"recurrence":{"pattern":{"type":"daily","interval":1,"month":0,"dayOfMonth":0,"firstDayOfWeek":"sunday","index":"first"},"range":{"type":"endDate","startDate":"2023-12-05","endDate":"2023-12-07","recurrenceTimeZone":"Eastern Standard Time","numberOfOccurrences":0}},"organizer":{"emailAddress":{"name":"Outlook Test","address":"foo@bar.com"}}}';
    }

    public static function getEventInstancesInJsonFormat(): string
    {
        return '{"@odata.context":"https:\/\/graph.microsoft.com\/v1.0\/$metadata#users(\'123\')\/events(\'1==\')\/instances","value":[{"@odata.etag":"W\/\"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==\"","id":"1==","createdDateTime":"2023-12-05T06:17:55.551725Z","lastModifiedDateTime":"2023-12-05T06:17:56.9028469Z","changeKey":"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==","categories":[],"transactionId":"eea2822c-5583-8a5a-a074-2f3f0d75f042","originalStartTimeZone":"Eastern Standard Time","originalEndTimeZone":"Eastern Standard Time","iCalUId":"A==","reminderMinutesBeforeStart":15,"isReminderOn":true,"hasAttachments":false,"subject":"R - 1","bodyPreview":"test","importance":"normal","sensitivity":"normal","isAllDay":false,"isCancelled":false,"isOrganizer":true,"responseRequested":true,"seriesMasterId":"foo==","showAs":"busy","type":"occurrence","webLink":"https:\/\/outlook.office365.com\/owa\/?itemid=1==&exvsurl=1&path=\/calendar\/item","onlineMeetingUrl":null,"isOnlineMeeting":false,"onlineMeetingProvider":"unknown","allowNewTimeProposals":true,"occurrenceId":"OID.1==.2023-12-05","isDraft":false,"hideAttendees":false,"responseStatus":{"response":"organizer","time":"0001-01-01T00:00:00Z"},"start":{"dateTime":"2023-12-05T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-05T07:30:00.0000000","timeZone":"UTC"},"location":{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"},"locations":[{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"}],"recurrence":null,"attendees":[],"organizer":{"emailAddress":{"name":"Foo Test","address":"foo@symplicity.com"}},"onlineMeeting":null},{"@odata.etag":"W\/\"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==\"","id":"2==","createdDateTime":"2023-12-05T06:17:55.551725Z","lastModifiedDateTime":"2023-12-05T06:17:56.9028469Z","changeKey":"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==","categories":[],"transactionId":"eea2822c-5583-8a5a-a074-2f3f0d75f042","originalStartTimeZone":"Eastern Standard Time","originalEndTimeZone":"Eastern Standard Time","iCalUId":"B","reminderMinutesBeforeStart":15,"isReminderOn":true,"hasAttachments":false,"subject":"R - 1","bodyPreview":"test","importance":"normal","sensitivity":"normal","isAllDay":false,"isCancelled":false,"isOrganizer":true,"responseRequested":true,"seriesMasterId":"foo==","showAs":"busy","type":"occurrence","webLink":"https:\/\/outlook.office365.com\/owa\/?itemid=2&exvsurl=1&path=\/calendar\/item","onlineMeetingUrl":null,"isOnlineMeeting":false,"onlineMeetingProvider":"unknown","allowNewTimeProposals":true,"occurrenceId":"OID.2==.2023-12-06","isDraft":false,"hideAttendees":false,"responseStatus":{"response":"organizer","time":"0001-01-01T00:00:00Z"},"start":{"dateTime":"2023-12-06T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-06T07:30:00.0000000","timeZone":"UTC"},"location":{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"},"locations":[{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"}],"recurrence":null,"attendees":[],"organizer":{"emailAddress":{"name":"Foo TEST","address":"foo@symplicity.com"}},"onlineMeeting":null},{"@odata.etag":"W\/\"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==\"","id":"3==","createdDateTime":"2023-12-05T06:17:55.551725Z","lastModifiedDateTime":"2023-12-05T06:17:56.9028469Z","changeKey":"7DBtS36oekqlFVL\/lW3rKQAAC3er5w==","categories":[],"transactionId":"eea2822c-5583-8a5a-a074-2f3f0d75f042","originalStartTimeZone":"Eastern Standard Time","originalEndTimeZone":"Eastern Standard Time","iCalUId":"C==","reminderMinutesBeforeStart":15,"isReminderOn":true,"hasAttachments":false,"subject":"R - 1","bodyPreview":"test","importance":"normal","sensitivity":"normal","isAllDay":false,"isCancelled":false,"isOrganizer":true,"responseRequested":true,"seriesMasterId":"foo==","showAs":"busy","type":"occurrence","webLink":"https:\/\/outlook.office365.com\/owa\/?itemid=3==&exvsurl=1&path=\/calendar\/item","onlineMeetingUrl":null,"isOnlineMeeting":false,"onlineMeetingProvider":"unknown","allowNewTimeProposals":true,"occurrenceId":"OID.3===.2023-12-07","isDraft":false,"hideAttendees":false,"responseStatus":{"response":"organizer","time":"0001-01-01T00:00:00Z"},"start":{"dateTime":"2023-12-07T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-07T07:30:00.0000000","timeZone":"UTC"},"location":{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"},"locations":[{"displayName":"Sikkim","locationType":"default","uniqueId":"Sikkim","uniqueIdType":"private"}],"recurrence":null,"attendees":[],"organizer":{"emailAddress":{"name":"Foo TEST","address":"foo@symplicity.com"}},"onlineMeeting":null}]}';
    }
}
