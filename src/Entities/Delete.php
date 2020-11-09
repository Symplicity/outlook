<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\BatchWriterEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;
use Symplicity\Outlook\Utilities\RequestType;

class Delete implements DeleteInterface, BatchWriterEntityInterface
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

    public function getMethod(): string
    {
        return RequestType::Delete;
    }

    public function getId(): ?string
    {
        return $this->internalId;
    }
}
