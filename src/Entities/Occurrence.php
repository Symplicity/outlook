<?php

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\RecurrenceEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ResponseBodyInterface;
use Symplicity\Outlook\Utilities\EventTypes;

class Occurrence implements ReaderEntityInterface
{
    protected $eventType;
    protected $id;
    protected $date;
    protected $eTag;
    protected $seriesMasterId;

    public function hydrate(array $data) : ReaderEntityInterface
    {
        $this->setEventType($data['Type']);
        $this->setId($data['Id']);
        $this->setETag($data['@odata.etag']);
        $this->setSeriesMasterId($data['SeriesMasterId']);

        $this->setDate([
            'start' => $data['Start']['DateTime'],
            'end' => $data['End']['DateTime'],
            'timezone' => $data['Start']['TimeZone'],
        ]);

        return $this;
    }

    public function getId() : string
    {
        return $this->id;
    }

    public function getDate() : DateEntityInterface
    {
        return $this->date;
    }

    public function getETag() : string
    {
        return $this->eTag;
    }

    public function getWebLink(): string
    {
        return '';
    }

    public function getTitle(): ?string
    {
        return null;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getBody(): ?ResponseBodyInterface
    {
        return null;
    }

    public function getLocation(): ?Location
    {
        return null;
    }

    public function isAllDay(): bool
    {
        return false;
    }

    public function getSensitivityStatus(): string
    {
        return '';
    }

    public function getVisibility(): string
    {
        return '';
    }

    public function getRecurrence(): ?RecurrenceEntityInterface
    {
        return null;
    }

    public function getOrganizer(): ?Organizer
    {
        return null;
    }

    public function getEventType(): EventTypes
    {
        return $this->eventType;
    }

    public function getSeriesMasterId(): ?string
    {
        return $this->seriesMasterId;
    }

    private function setEventType(string $eventType) : void
    {
        $this->eventType = EventTypes::Occurrence();
        if ($value = EventTypes::search($eventType)) {
            $this->eventType = EventTypes::$value();
        }
    }

    private function setId($id): void
    {
        $this->id = $id;
    }

    private function setDate($date): void
    {
        $this->date = new DateEntity($date);
    }

    private function setETag($eTag): void
    {
        $this->eTag = $eTag;
    }

    public function setSeriesMasterId(string $seriesMasterId): void
    {
        $this->seriesMasterId = $seriesMasterId;
    }
}
