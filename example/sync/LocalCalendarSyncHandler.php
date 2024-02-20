<?php

declare(strict_types=1);

use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\DateTimeTimeZone;
use Microsoft\Graph\Generated\Models\Event as MsEvent;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\OpenTypeExtension;
use Symplicity\Outlook\Calendar;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Models\Event;
use Symplicity\Outlook\Utilities\CalendarView\CalendarViewParams;

interface DatabaseStorageInterface
{
    public function save(array $data);
    public function update(string $id, array $data);
    public function delete(string $id);
    public function get(string $id);
}

class LocalCalendarSyncHandler extends Calendar
{
    // Persistent Database
    private ?DatabaseStorageInterface $database = null;

    public function sync(): void
    {
        /**
         * Getting calendar items
         * - Initial Sync without delta token
         */
        $params = new CalendarViewParams();

        // For initial pull, remove the delta token method call
        $params
            ->setDeltaToken('{{delta_token_from last pull request}}')
            ->setStartDateTime('2023-11-30T00:00:00-05:00')
            ->setEndDateTime('2023-12-06T23:59:59-05:00')
            ->setTimezone('Eastern Standard Time');

        try {
            $that = $this;

            // Push events to outlook
            $this->push();

            // This pulls events from the calendar 50 at a time. Each event is parsed and sent to saveEventLocal method
            $this->pull($params, fn ($deltaLinkUrl) => $that->saveDeltaToken($deltaLinkUrl));
        } catch (Exception $e) {
            $this->logger?->error('Getting/Pushing events from/to outlook failed', [
                $e->getMessage(),
                $e->getCode()
            ]);
        }
    }

    public function saveEventLocal(ReaderEntityInterface $reader): void
    {
        // save items to the database. Check the eTag to verify if the item has changed
        $this->database?->save([
            'event_id' => $reader->getId(),
            'title' => $reader->getTitle(),
            'body' => $reader->getBody()?->getContent(),
            'sensitivity' => $reader->getSensitivityStatus()->value(),
            'free_busy' => $reader->getFreeBusyStatus()->value()
        ]);
    }

    public function deleteEventLocal(?string $eventId): void
    {
        if ($this->database?->get($eventId)) {
            $this->database?->delete($eventId);
        }
    }

    public function getLocalEvents(): array
    {
        // Set up a return for all events that you want pushed to Outlook calendar.

        // Post event to outlook
        $start = new DateTimeTimeZone();
        $start->setTimeZone('Eastern Standard Time');
        $start->setDateTime('2023-11-28 15:00:00');

        $end = new DateTimeTimeZone();
        $end->setTimeZone('Eastern Standard Time');
        $end->setDateTime('2023-11-28 16:00:00');

        $body = new ItemBody();
        $body->setContentType(new BodyType(BodyType::HTML));
        $body->setContent('<p>This is a test event</p>');

        $event1 = new Event();
        $event1->setSubject('Test1');
        $event1->setStart($start);
        $event1->setEnd($end);
        $event1->setBody($body);

        // Delete event from outlook
        $event2 = new Event();
        $event2->setIsDelete();
        $event2->setId('ABC==');

        // Patch event to outlook
        $body = new ItemBody();
        $body->setContent('Update event with extension');

        $event3 = new Event();
        $event3->setSubject('Update Event');
        $event3->setId('ADB==');
        $event3->setStart($start);
        $event3->setEnd($end);
        $event3->setBody($body);
        $event3->setExtensions([$this->getExtension()]);

        return [$event1, $event2, $event3];
    }

    public function handleBatchResponse(?Generator $responses = null): void
    {
        foreach ($responses as $response) {
            if (isset($response['event'], $response['status']) && $response['event'] instanceof MsEvent) {
                if ($response['status'] === 201) {
                    // Save to a mapping table
                    $this->database->save([
                        'id' => $response['id'],
                    ]);
                } else {
                    $this->database->update($response['id'], []);
                }
            }
        }
    }

    public function saveDeltaToken(string $deltaLink): void
    {
        $parsedUrl = parse_url($deltaLink, PHP_URL_QUERY);
        parse_str($parsedUrl, $queryComponents);
        $token = $queryComponents['$deltaToken'] ?? null;
        // Save delta token
        $this->database->save($token);
    }

    public function getExtension(): OpenTypeExtension
    {
        $extension = new OpenTypeExtension();
        $extension->setExtensionName('com.symplicity.test');
        $extension->setAdditionalData([
            'internalId' => '1232133'
        ]);

        return $extension;
    }
}
