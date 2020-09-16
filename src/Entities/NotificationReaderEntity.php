<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\NotificationReaderEntityInterface;
use Symplicity\Outlook\Utilities\ChangeType;

class NotificationReaderEntity implements \JsonSerializable, NotificationReaderEntityInterface
{
    protected $type;
    protected $outlookId;
    protected $subscriptionId;
    protected $subscriptionExpirationDateTime;
    protected $sequenceNumber;
    /** @var ChangeType */
    protected $changeType;
    protected $resource;
    protected $oDataType;
    protected $oDataId;
    protected $etag;
    protected $id;

    public function __construct(array $data = [])
    {
        $this->type = $data['@odata.type'] ?? null;
        $this->outlookId = $data['Id'] ?? null;
        $this->subscriptionId = $data['SubscriptionId'] ?? null;
        $this->subscriptionExpirationDateTime = $data['SubscriptionExpirationDateTime'] ?? null;
        $this->sequenceNumber = $data['SequenceNumber'] ?? null;
        $this->resource = $data['Resource'] ?? null;
        $this->oDataType = $data['ResourceData']['@odata.type'] ?? null;
        $this->oDataId = $data['ResourceData']['@odata.id'] ?? null;
        $this->etag = $data['ResourceData']['@odata.etag'] ?? null;
        $this->id = $data['ResourceData']['Id'] ?? null;
        $this->setChangeType($data['ChangeType'] ?? null);
    }

    public function jsonSerialize(): array
    {
        return [
            'res' => $this->resource,
            'id' => $this->id,
            'subId' => $this->subscriptionId,
            'cT' => $this->changeType,
            'seq' => $this->sequenceNumber
        ];
    }

    public function has(string $var): bool
    {
        if (isset($this->$var)) {
            return true;
        }

        return false;
    }

    public function setSubscriptionId(?string $subscriptionId): NotificationReaderEntityInterface
    {
        $this->subscriptionId = $subscriptionId;
        return $this;
    }

    public function setChangeType(?string $changeType): NotificationReaderEntityInterface
    {
        $this->changeType = ChangeType::unknown;
        if ($value = ChangeType::search($changeType)) {
            $this->changeType = ChangeType::$value();
        }

        return $this;
    }

    public function setResource(?string $resource): NotificationReaderEntityInterface
    {
        $this->resource = $resource;
        return $this;
    }

    public function setId(?string $id): NotificationReaderEntityInterface
    {
        $this->id = $id;
        return $this;
    }

    public function setSequenceNumber(?int $sequenceNumber): NotificationReaderEntityInterface
    {
        $this->sequenceNumber = $sequenceNumber;
        return $this;
    }

    // Mark Getters
    public function getType(): ?string
    {
        return $this->type;
    }

    public function getOutlookId(): ?string
    {
        return $this->outlookId;
    }

    public function getSubscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    public function getSubscriptionExpirationDateTime(): ?string
    {
        return $this->subscriptionExpirationDateTime;
    }

    public function getSequenceNumber(): ?int
    {
        return $this->sequenceNumber;
    }

    public function getChangeType(): ?string
    {
        return $this->changeType->getValue();
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function getODataType(): ?string
    {
        return $this->oDataType;
    }

    public function getODataId(): ?string
    {
        return $this->oDataId;
    }

    public function getEtag(): ?string
    {
        return $this->etag;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}