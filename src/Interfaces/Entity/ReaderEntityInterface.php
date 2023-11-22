<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Graph\Generated\Models\FreeBusyStatus;
use Microsoft\Graph\Generated\Models\Importance;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Location as Location;
use Microsoft\Graph\Generated\Models\Recipient;
use Microsoft\Graph\Generated\Models\Sensitivity;

interface ReaderEntityInterface
{
    public function hydrate(?Event $event = null): ReaderEntityInterface;

    public function getId(): ?string;

    public function getWebLink(): ?string;

    public function getTitle(): ?string;

    public function getDescription(): ?string;

    public function getBody(): ?ItemBody;

    public function getDate(): DateEntityInterface;

    public function getLocation(): ?Location;

    public function getETag(): string;

    public function isAllDay(): bool;

    public function getSensitivityStatus(): ?Sensitivity;

    public function getVisibility(): ?Importance;

    public function getRecurrence(): ?RecurrenceEntityInterface;

    public function getOrganizer(): ?Recipient;

    public function getEventType(): ?EventType;

    public function getSeriesMasterId(): ?string;

    public function getFreeBusyStatus(): ?FreeBusyStatus;

    public function getExtensions(): array;
}
