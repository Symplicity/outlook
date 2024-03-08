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

    protected ?string $title = null;

    protected ?string $description = null;

    protected ?ItemBody $body = null;

    protected DateEntityInterface $date;

    protected string $eTag;

    protected ?string $seriesMasterId = null;

    protected bool $allDay = false;

    /** @var array<Extension> */
    protected array $extensions = [];

    protected bool $cancelled = false;

    private \Closure|Event|null $originalEvent = null;

    public function hydrate(?Event $event = null): ReaderEntityInterface
    {
        $this->setEventType($event?->getType());
        $this->setTitle($event?->getSubject());
        $this->setDescription($event?->getBodyPreview());
        $this->setBody($event?->getBody());
        $this->setId($event?->getId());
        $this->setETag($event?->getAdditionalData()['@odata.etag'] ?? null);
        $this->setSeriesMasterId($event?->getSeriesMasterId());
        $this->setAllDay($event?->getIsAllDay() ?? false);

        $this->setDate([
            'start' => $event?->getStart()?->getDateTime(),
            'end' => $event?->getEnd()?->getDateTime(),
            'timezone' => $event?->getStart()->getTimeZone(),
        ]);
        $this->setCancelled($event?->getIsCancelled() ?? false);
        $this->setExtensions($event?->getExtensions() ?? []);
        $this->originalEvent = fn() => $event;
        return $this;
    }

    public function getOriginalEvent(): Event
    {
        $originalEvent = $this->originalEvent?->bindTo($this); // @phpstan-ignore-line
        return $originalEvent(); // @phpstan-ignore-line
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDate(): DateEntityInterface
    {
        return $this->date;
    }

    public function getETag(): ?string
    {
        return $this->eTag;
    }

    public function getWebLink(): string
    {
        return '';
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

    public function getEventType(): ?EventType
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

    /** @return Extension[] */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function setSeriesMasterId(?string $seriesMasterId): void
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

    /** @param array<string, string | null> $date */
    private function setDate(array $date): void
    {
        $this->date = new DateEntity($date);
    }

    private function setETag(?string $eTag): void
    {
        $this->eTag = $eTag ?? '';
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

    /** @param Extension[] $extensions */
    private function setExtensions(array $extensions = []): void
    {
        $this->extensions = $extensions;
    }

    private function setAllDay(bool $allDay): void
    {
        $this->allDay = $allDay;
    }

    public function setCancelled(bool $cancelled): void
    {
        $this->cancelled = $cancelled;
    }
}
