<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use DateTimeInterface;
use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Graph\Generated\Models\Extension as Extension;
use Microsoft\Graph\Generated\Models\FreeBusyStatus;
use Microsoft\Graph\Generated\Models\Importance;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Location as Location;
use Microsoft\Graph\Generated\Models\Recipient;
use Microsoft\Graph\Generated\Models\Sensitivity;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\RecurrenceEntityInterface;

class Reader implements ReaderEntityInterface
{
    protected ?string $id = null;

    protected ?string $webLink = null;

    protected ?string $title = null;

    protected ?string $description = null;

    protected ?ItemBody $body = null;

    protected DateEntityInterface $date;

    protected bool $allDay = false;

    protected ?Location $location = null;

    protected ?string $eTag = null;

    protected ?Importance $visibility = null;

    protected ?RecurrenceEntityInterface $recurrence = null;

    protected ?Sensitivity $private = null;

    protected ?Recipient $organizer = null;

    protected ?EventType $eventType = null;

    protected ?string $seriesMasterId = null;

    protected ?FreeBusyStatus $freeBusy;

    /** @var array<Extension> $extensions */
    protected ?array $extensions = [];

    public function hydrate(?Event $event = null): ReaderEntityInterface
    {
        $this->setId($event->getId());
        $this->setEventType($event->getType());
        $this->setWebLink($event->getWebLink());
        $this->setETag($event->getAdditionalData()['@odata.etag'] ?? null);
        $this->setTitle($event->getSubject());
        $this->setDescription($event->getBodyPreview());
        $this->setBody($event->getBody());
        $this->setAllDay($event->getIsAllDay() ?? false);
        $this->setLocation($event->getLocation());
        $this->setVisibility($event->getImportance());
        $this->setPrivate($event->getSensitivity());
        $this->setOrganizer($event->getOrganizer());
        $this->setSeriesMasterId($event->getSeriesMasterId());
        $this->setFreeBusy($event->getShowAs());
        $this->setExtensions($event->getExtensions());
        $this->setRecurrence($event);
        $this->setDate([
            'start' => $event->getStart()?->getDateTime(),
            'end' => $event->getEnd()?->getDateTime(),
            'timezone' => $event->getStart()?->getTimeZone(),
            'modified' => $event->getLastModifiedDateTime()
        ]);

        return $this;
    }

    public function deleted(?Event $data = null): self
    {
        $this->setId($data->getId());
        return $this;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    // Mark: Getters
    public function getId(): ?string
    {
        return $this->id;
    }

    public function getWebLink(): ?string
    {
        return $this->webLink;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getBody(): ?ItemBody
    {
        return $this->body;
    }

    public function getDate(): DateEntityInterface
    {
        return $this->date;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function getETag(): string
    {
        return $this->eTag;
    }

    public function getVisibility(): ?Importance
    {
        return $this->visibility;
    }

    public function getRecurrence(): ?RecurrenceEntityInterface
    {
        return $this->recurrence;
    }

    public function getOrganizer(): ?Recipient
    {
        return $this->organizer;
    }

    public function getEventType(): ?EventType
    {
        return $this->eventType;
    }

    public function getSeriesMasterId(): ?string
    {
        return $this->seriesMasterId;
    }

    public function getExtensions(): ?array
    {
        return $this->extensions;
    }

    public function getSensitivityStatus(): ?Sensitivity
    {
        return $this->private;
    }

    public function getFreeBusyStatus(): ?FreeBusyStatus
    {
        return $this->freeBusy;
    }

    // Mark: Setters
    public function setSeriesMasterId(?string $seriesMasterId): void
    {
        $this->seriesMasterId = $seriesMasterId;
    }

    protected function setId(string $id): void
    {
        $this->id = $id;
    }

    protected function setWebLink(?string $webLink): void
    {
        $this->webLink = $webLink;
    }

    protected function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    protected function setBody(?ItemBody $body): void
    {
        $this->body = $body;
    }

    protected function setDescription(?string $description): void
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

    protected function setLocation(?Location $location): void
    {
        $this->location = $location;
    }

    protected function setETag(?string $eTag): void
    {
        $this->eTag = $eTag;
    }

    protected function setVisibility(?Importance $visibility): void
    {
        $this->visibility = $visibility;
    }

    protected function setRecurrence(Event $event): void
    {
        if ($event->getRecurrence() !== null
            && $event->getType() === EventType::SERIES_MASTER) {
            $this->recurrence = new Recurrence($event->getRecurrence());
        }
    }

    protected function setPrivate(?Sensitivity $private): void
    {
        $this->private = $private;
    }

    protected function setOrganizer(?Recipient $organizer): void
    {
        $this->organizer = $organizer;
    }

    protected function setEventType(?EventType $eventType): void
    {
        $this->eventType = $eventType;
    }

    public function setFreeBusy(?FreeBusyStatus $freeBusy): void
    {
        $this->freeBusy = $freeBusy;
    }

    protected function setExtensions(?array $extensions = []): ReaderEntityInterface
    {
        $this->extensions = $extensions;
        return $this;
    }
}
