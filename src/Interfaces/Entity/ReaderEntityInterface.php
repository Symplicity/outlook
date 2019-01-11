<?php

namespace Symplicity\Outlook\Interfaces\Entity;

use Symplicity\Outlook\Entities\Location;
use Symplicity\Outlook\Entities\Organizer;
use Symplicity\Outlook\Utilities\EventTypes;

/**
 * Interface ReaderInterface
 * @property-read string $id
 * @property-read string $webLink
 * @property-read ?string $title
 * @property-read string $description
 * @property-read ResponseBodyInterface $body
 * @property-read DateEntityInterface $date
 * @property-read bool $allDay
 * @property-read Location $location
 * @property-read string $eTag
 * @property-read Organizer $organizer
 * @property-read string $private
 * @property-read string $visibility
 * @property-read EventTypes $eventType
 * @property-read RecurrenceEntityInterface $recurrence
 */
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
}
