<?php

namespace Symplicity\Outlook\Entities;

use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Graph\Generated\Models\Extension as Extension;
use Microsoft\Graph\Generated\Models\FreeBusyStatus;
use Microsoft\Graph\Generated\Models\Importance;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Location;
use Microsoft\Graph\Generated\Models\Recipient;
use Microsoft\Graph\Generated\Models\Sensitivity;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\RecurrenceEntityInterface;

class Occurrence implements ReaderEntityInterface
{
    protected ?EventType $eventType = null;

    protected ?string $id = null;

    protected DateEntityInterface $date;

    protected string $eTag;

    protected ?string $seriesMasterId = null;

    protected bool $allDay = false;

    /** @var array<Extension> */
    protected array $extensions = [];

    public function hydrate(?Event $event = null): ReaderEntityInterface
    {
        $this->setEventType($event->getType());
        $this->setId($event->getId());
        $this->setETag($event->getAdditionalData()['@odata.etag'] ?? null);
        $this->setSeriesMasterId($event->getSeriesMasterId());
        $this->setAllDay($event->getIsAllDay() ?? false);

        $this->setDate([
            'start' => $event->getStart()?->getDateTime(),
            'end' => $event->getEnd()?->getDateTime(),
            'timezone' => $event->getOriginalStartTimeZone(),
        ]);

        $this->setExtensions($event->getExtensions() ?? []);
        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDate(): DateEntityInterface
    {
        return $this->date;
    }

    public function getETag(): string
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

    public function getBody(): ?ItemBody
    {
        return null;
    }

    public function getLocation(): ?Location
    {
        return null;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function getSensitivityStatus(): ?Sensitivity
    {
        return null;
    }

    public function getVisibility(): ?Importance
    {
        return null;
    }

    public function getRecurrence(): ?RecurrenceEntityInterface
    {
        return null;
    }

    public function getOrganizer(): ?Recipient
    {
        return null;
    }

    public function getEventType(): EventType
    {
        return $this->eventType;
    }

    public function getSeriesMasterId(): ?string
    {
        return $this->seriesMasterId;
    }

    public function getFreeBusyStatus(): ?FreeBusyStatus
    {
        return null;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function setSeriesMasterId(string $seriesMasterId): void
    {
        $this->seriesMasterId = $seriesMasterId;
    }

    private function setEventType(?EventType $type): void
    {
        $this->eventType = $type;
    }

    private function setId(?string $id): void
    {
        $this->id = $id;
    }

    private function setDate(array $date): void
    {
        $this->date = new DateEntity($date);
    }

    private function setETag(?string $eTag): void
    {
        $this->eTag = $eTag ?? '';
    }

    private function setExtensions(array $extensions = []): ReaderEntityInterface
    {
        $this->extensions = $extensions;
        return $this;
    }

    private function setAllDay(bool $allDay): void
    {
        $this->allDay = $allDay;
    }
}
