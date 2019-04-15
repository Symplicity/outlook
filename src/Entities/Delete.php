<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;

class Delete implements DeleteInterface
{
    private $guid;
    private $internalId;
    private $internalEventType;

    public function __construct(string $guid, string $internalId)
    {
        $this->guid = $guid;
        $this->internalId = $internalId;
    }

    // Fluent Setter
    public function setInternalEventType(?string $type) : DeleteInterface
    {
        $this->internalEventType = $type;
        return $this;
    }

    public function getGuid() : string
    {
        return $this->guid;
    }

    public function getInternalId() : string
    {
        return $this->internalId;
    }

    public function getInternalEventType() : ?string
    {
        return $this->internalEventType;
    }

    public function getUrl() : string
    {
        return '/me/events/' . $this->guid;
    }
}
