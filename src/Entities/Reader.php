<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\RecurrenceEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ResponseBodyInterface;
use Symplicity\Outlook\Utilities\EventTypes;

class Reader implements ReaderEntityInterface
{
    protected $id;
    protected $webLink;
    protected $title;
    protected $description;
    protected $body;
    protected $date;
    protected $allDay = false;
    protected $location;
    protected $eTag;
    protected $visibility;
    protected $recurrence;
    protected $private;
    protected $organizer;
    protected $eventType;
    protected $seriesMasterId;

    public function hydrate(array $data = []) : self
    {
        $this->setEventType($data['Type']);
        $this->setId($data['Id']);
        $this->setWebLink($data['WebLink']);
        $this->setTitle($data['Subject']);
        $this->setDescription($data['BodyPreview']);
        $this->setBody($data['Body']);

        $this->setDate([
            'start' => $data['Start']['DateTime'],
            'end' => $data['End']['DateTime'],
            'originalTimezone' => $data['OriginalStartTimeZone'],
            'timezone' => $data['Start']['TimeZone'],
            'modified' => $data['LastModifiedDateTime']
        ]);

        $this->setAllDay($data['IsAllDay']);
        $this->setLocation($data['Location']);
        $this->setETag($data['@odata.etag']);
        $this->setVisibility($data['Importance']);
        $this->setRecurrence($data);
        $this->setPrivate($data['Sensitivity']);
        $this->setOrganizer($data['Organizer']);
        $this->setSeriesMasterId($data['SeriesMasterId'] ?? null);

        return $this;
    }

    public function toArray()
    {
        return get_object_vars($this);
    }

    // Mark: Getters
    public function getId() : string
    {
        return $this->id;
    }

    public function getWebLink() : string
    {
        return $this->webLink;
    }

    public function getTitle() : ?string
    {
        return $this->title;
    }

    public function getBody() : ResponseBodyInterface
    {
        return $this->body;
    }

    public function getDescription() : ?string
    {
        return $this->description;
    }

    public function getDate() : DateEntityInterface
    {
        return $this->date;
    }

    public function isAllDay() : bool
    {
        return $this->allDay;
    }

    public function getLocation() : ?Location
    {
        return $this->location;
    }

    public function getETag() : string
    {
        return $this->eTag;
    }

    public function getVisibility() : string
    {
        return $this->visibility;
    }

    public function getRecurrence() : ?RecurrenceEntityInterface
    {
        return $this->recurrence;
    }

    public function getSensitivityStatus() : string
    {
        return $this->private;
    }

    public function getOrganizer() : ?Organizer
    {
        return $this->organizer;
    }

    public function getEventType() : EventTypes
    {
        return $this->eventType;
    }

    public function getSeriesMasterId(): ?string
    {
        return $this->seriesMasterId;
    }

    // Mark: Setters
    protected function setId(string $id): void
    {
        $this->id = $id;
    }

    protected function setWebLink(string $webLink): void
    {
        $this->webLink = $webLink;
    }

    protected function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    protected function setBody(array $body): void
    {
        $this->body = new ResponseBody($body);
    }

    protected function setDescription(?string $description) : void
    {
        $this->description = $description;
    }

    protected function setDate(array $date): void
    {
        $this->date = new DateEntity($date);
    }

    protected function setAllDay(bool $allDay): void
    {
        $this->allDay = $allDay;
    }

    protected function setLocation(array $location): void
    {
        $this->location = new Location($location);
    }

    protected function setETag(string $eTag): void
    {
        $this->eTag = $eTag;
    }

    protected function setVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }

    protected function setRecurrence(array $data): void
    {
        if (isset($data['Type'], $data['Recurrence'])
            && $data['Type'] == EventTypes::Master) {
            $this->recurrence = new Recurrence($data['Recurrence']);
        }
    }

    protected function setPrivate(string $private): void
    {
        $this->private = $private;
    }

    protected function setOrganizer(array $organizer): void
    {
        $this->organizer = new Organizer($organizer);
    }

    protected function setEventType(string $eventType) : void
    {
        $this->eventType = EventTypes::Single();
        if ($value = EventTypes::search($eventType)) {
            $this->eventType = EventTypes::$value();
        }
    }

    public function setSeriesMasterId(?string $seriesMasterId): void
    {
        $this->seriesMasterId = $seriesMasterId;
    }
}
