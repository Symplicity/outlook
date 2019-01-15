<?php

namespace Symplicity\Outlook\Interfaces\Entity;

use Symplicity\Outlook\Entities\Location;
use Symplicity\Outlook\Entities\Organizer;
use Symplicity\Outlook\Utilities\EventTypes;

interface ReaderEntityInterface
{
    public function getId() : string;
    public function getWebLink() : string;
    public function getTitle() : ?string;
    public function getDescription() : ?string;
    public function getBody() : ResponseBodyInterface;
    public function getDate() : DateEntityInterface;
    public function getLocation() : ?Location;
    public function getETag() : string;
    public function isAllDay() : bool;
    public function getSensitivityStatus() : string;
    public function getVisibility() : string;
    public function getRecurrence() : ?RecurrenceEntityInterface;
    public function getOrganizer() : ?Organizer;
    public function getEventType() : EventTypes;
    public function getSeriesMasterId(): ?string;
}
